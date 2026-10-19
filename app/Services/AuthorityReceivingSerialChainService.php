<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use Illuminate\Support\Facades\DB;

/** Plans a serial correction through unambiguous completed transfer receipts. */
class AuthorityReceivingSerialChainService
{
    public function plan(int $businessId, int $rootBatchId, string $serial, array $receivingPairs = []): array
    {
        $pending = [$rootBatchId];
        $visited = [];
        $changes = [];
        $snapshots = [];
        $transactionIds = [];
        $allocationRepairs = [];
        $originalSerial = null;
        while ($pending) {
            $id = array_shift($pending);
            if (isset($visited[$id])) { continue; }
            if (count($visited) >= 500) {
                $this->blocked($rootBatchId, 'The linked chain is too large for one correction.');
            }
            $visited[$id] = true;
            $batch = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('p.id', $id)->orderBy('p.id')->lockForUpdate()
                ->first(['p.*', 't.business_id', 't.location_id', 't.type as receipt_type', 't.status as receipt_status',
                    't.transaction_date as receipt_date', 't.ref_no']);
            if (!$batch || (int) $batch->business_id !== $businessId || $batch->receipt_status !== 'received'
                || $batch->receipt_type !== 'purchase_transfer' || (float) $batch->quantity <= 0) {
                $this->blocked($id, 'A linked receiving batch is missing, outside this business or not received.');
            }
            if ($id === $rootBatchId) { $originalSerial = (string) $batch->lot_number; }
            if (trim((string) $batch->lot_number) !== '' && (string) $batch->lot_number !== $serial
                && (string) $batch->lot_number !== $originalSerial) {
                $this->blocked($id, 'A linked receiving batch already has a different serial.');
            }
            if ((float) $batch->quantity_returned > 0 || (float) $batch->quantity_adjusted > 0 || (float) $batch->mfg_quantity_used > 0) {
                $this->blocked($id, 'A linked batch has returns, adjustments or production usage.');
            }
            $mappings = DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $id)
                ->orderBy('id')->lockForUpdate()->get();
            $movements = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                ->where(fn ($q) => $q->where('s.lot_no_line_id', $id)->orWhereIn('s.id', $mappings->pluck('sell_line_id')->filter()))
                ->orderBy('s.id')->lockForUpdate()->get(['s.*', 't.business_id', 't.location_id', 't.type as movement_type',
                    't.status as movement_status', 't.transaction_date as movement_date', 't.ref_no', 't.invoice_no', 't.return_parent_id']);
            if ($mappings->contains(fn ($mapping) => empty($mapping->sell_line_id))
                || $mappings->pluck('sell_line_id')->diff($movements->pluck('id'))->isNotEmpty()) {
                $this->blocked($id, 'A linked allocation has a return or no valid sale/transfer line.');
            }
            $allocations = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $movements->pluck('id'))
                ->orderBy('id')->lockForUpdate()->get();
            $snapshots[] = [$batch, $mappings, $movements, $allocations];
            foreach ($movements as $movement) {
                if ($movement->movement_type === 'sell' && (int) $movement->lot_no_line_id !== $id) {
                    $repair = $this->planSaleAllocationRepair($batch, $movement, $allocations->where('sell_line_id', $movement->id), $snapshots);
                    $allocationRepairs[(int) $movement->id] = $repair;
                    $transactionIds[] = (int) $movement->transaction_id;
                }
            }
            $movements = $movements->reject(fn ($movement) => isset($allocationRepairs[(int) $movement->id]));
            $this->verifySaleReturns($batch, $movements, $allocations);
            if ((float) $movements->where('movement_type', '!=', 'sell_return')->sum(fn ($row) => $row->quantity - $row->quantity_returned) > (float) $batch->quantity + 0.0001) {
                $this->blocked($id, 'The linked movements consume more than the receiving quantity.');
            }
            if ((string) $batch->lot_number !== $serial) {
                $changes[] = ['batch_id' => $id, 'transaction_id' => (int) $batch->transaction_id,
                    'reference' => $batch->ref_no, 'before' => $batch->lot_number, 'after' => $serial,
                    'quantity' => (float) $batch->quantity, 'date' => (string) $batch->receipt_date,
                    'documents' => $movements->map(fn ($row) => ['id' => (int) $row->transaction_id,
                        'type' => $row->movement_type, 'reference' => $row->invoice_no ?: $row->ref_no])->unique('id')->values()->all()];
            }
            foreach ($movements as $movement) {
                $mapped = $allocations->where('sell_line_id', $movement->id);
                if ($movement->movement_type === 'sell_return') {
                    $transactionIds[] = (int) $movement->transaction_id;
                    continue; // Verified against its original invoice above; no stock allocation is rewritten.
                }
                if ((int) $movement->business_id !== $businessId || $movement->movement_status !== 'final'
                    || !in_array($movement->movement_type, ['sell', 'sell_transfer'], true)
                    || (int) $movement->location_id !== (int) $batch->location_id
                    || (int) $movement->product_id !== (int) $batch->product_id || (int) $movement->variation_id !== (int) $batch->variation_id
                    || (int) $movement->lot_no_line_id !== $id || (float) $movement->quantity <= 0
                    || ($movement->movement_type !== 'sell' && (float) $movement->quantity_returned > 0) || $mapped->isEmpty()
                    || $mapped->contains(fn ($mapping) => (int) $mapping->purchase_line_id !== $id)
                    || abs((float) $mapped->sum('quantity') - (float) $movement->quantity) > 0.0001) {
                    $this->blocked($id, 'Linked line #'.$movement->id.' has incomplete or conflicting product, location or source allocations.');
                }
                if ($movement->movement_type === 'sell_transfer'
                    && \Carbon::parse($batch->receipt_date)->gt(\Carbon::parse($movement->movement_date))) {
                    $this->blocked($id, 'A linked transfer predates its own source receipt.');
                }
                $transactionIds[] = (int) $movement->transaction_id;
                if ($movement->movement_type !== 'sell_transfer') { continue; }
                $siblings = DB::table('transaction_sell_lines')->where('transaction_id', $movement->transaction_id)
                    ->where('product_id', $batch->product_id)->where('variation_id', $batch->variation_id)
                    ->orderBy('id')->lockForUpdate()->get();
                $receipts = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase_transfer')
                    ->where('transfer_parent_id', $movement->transaction_id)->orderBy('id')->lockForUpdate()->get();
                $destinations = DB::table('purchase_lines')->whereIn('transaction_id', $receipts->pluck('id'))
                    ->where('product_id', $batch->product_id)->where('variation_id', $batch->variation_id)
                    ->orderBy('id')->lockForUpdate()->get();
                $snapshots[] = [$siblings, $receipts, $destinations];
                $destinationId = $this->matchReceivingBatch($batch, $movement, $siblings, $receipts, $destinations, $serial, $snapshots, $receivingPairs, $visited);
                if (isset($visited[$destinationId])) {
                    $this->blocked($id, 'The connected transfers form a cycle.');
                }
                $pending[] = $destinationId;
            }
        }

        return ['root_batch_id' => $rootBatchId, 'changes' => $changes, 'snapshots' => $snapshots,
            'transaction_ids' => array_values(array_unique($transactionIds)), 'allocation_repairs' => array_values($allocationRepairs)];
    }

    /** Returns must corroborate the original invoice and its allocated returned quantity. */
    private function verifySaleReturns($batch, $movements, $allocations): void
    {
        $sales = $movements->where('movement_type', 'sell');
        $returns = $movements->where('movement_type', 'sell_return');
        foreach ($returns as $return) {
            if ((int) $return->business_id !== (int) $batch->business_id || $return->movement_status !== 'final'
                || (int) $return->location_id !== (int) $batch->location_id
                || (int) $return->product_id !== (int) $batch->product_id || (int) $return->variation_id !== (int) $batch->variation_id
                || (int) $return->lot_no_line_id !== (int) $batch->id || (float) $return->quantity <= 0
                || (float) $return->quantity_returned != 0 || !$return->return_parent_id
                || !$sales->contains('transaction_id', $return->return_parent_id)
                || $allocations->contains('sell_line_id', $return->id)) {
                $this->blocked((int) $batch->id, 'Return line #'.$return->id.' does not match a verified original invoice.');
            }
        }
        foreach ($sales as $sale) {
            $mapped = $allocations->where('sell_line_id', $sale->id);
            if ((float) $sale->quantity_returned < 0 || (float) $sale->quantity_returned > (float) $sale->quantity
                || $mapped->contains(fn ($row) => (float) $row->quantity < 0 || (float) $row->qty_returned < 0
                    || (float) $row->qty_returned > (float) $row->quantity)
                || abs((float) $mapped->sum('qty_returned') - (float) $sale->quantity_returned) > 0.0001) {
                $this->blocked((int) $batch->id, 'Invoice line #'.$sale->id.' has conflicting returned allocations.');
            }
        }
        foreach ($sales->groupBy('transaction_id') as $invoiceId => $lines) {
            if (abs((float) $lines->sum('quantity_returned') - (float) $returns->where('return_parent_id', $invoiceId)->sum('quantity')) > 0.0001) {
                $this->blocked((int) $batch->id, 'Invoice #'.$invoiceId.' and its return records have different returned quantities.');
            }
        }
    }

    /** A sale linked only by its wrong deduction must follow its own recorded selection. */
    private function planSaleAllocationRepair($batch, $movement, $mapped, array &$snapshots): array
    {
        $source = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('p.id', $movement->lot_no_line_id)->orderBy('p.id')->lockForUpdate()
            ->select('p.*', 't.business_id', 't.location_id', 't.status as receipt_status', 't.type as receipt_type', 't.transaction_date as receipt_date')
            ->selectRaw(app(\App\Utils\TransactionUtil::class)->lotQuantityAvailableSql('p', (int) $movement->id).' as available')->first();
        $oldIds = $mapped->pluck('purchase_line_id')->filter()->unique();
        $oldSources = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->whereIn('p.id', $oldIds)->orderBy('p.id')->lockForUpdate()->get(['p.*', 't.business_id', 't.location_id']);
        $snapshots[] = [$source, $oldSources];
        if (!$source || (int) $movement->business_id !== (int) $batch->business_id || $movement->movement_status !== 'final'
            || (int) $movement->location_id !== (int) $batch->location_id
            || (int) $movement->product_id !== (int) $batch->product_id || (int) $movement->variation_id !== (int) $batch->variation_id
            || (float) $movement->quantity <= 0 || (float) $movement->quantity_returned > 0 || $mapped->isEmpty()
            || $mapped->contains(fn ($row) => (float) $row->qty_returned > 0 || (float) $row->quantity < 0)
            || abs((float) $mapped->sum('quantity') - (float) $movement->quantity) > 0.0001
            || (int) $source->business_id !== (int) $batch->business_id || (int) $source->location_id !== (int) $batch->location_id
            || (int) $source->product_id !== (int) $batch->product_id || (int) $source->variation_id !== (int) $batch->variation_id
            || $source->receipt_status !== 'received' || !in_array($source->receipt_type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
            || \Carbon::parse($source->receipt_date)->gt(\Carbon::parse($movement->movement_date))
            || $oldSources->count() !== $oldIds->count()
            || $oldSources->contains(fn ($row) => (int) $row->business_id !== (int) $batch->business_id
                || (int) $row->location_id !== (int) $batch->location_id || (int) $row->product_id !== (int) $batch->product_id
                || (int) $row->variation_id !== (int) $batch->variation_id)) {
            $this->blocked((int) $batch->id, 'Invoice line #'.$movement->id.' has an invalid selected receipt, returned quantity or conflicting allocation.');
        }
        if (!\App\Utils\BusinessUtil::allowsOverselling((int) $batch->business_id) && (float) $source->available + 0.0001 < (float) $movement->quantity) {
            $this->blocked((int) $batch->id, 'The selected receipt for invoice line #'.$movement->id.' has insufficient stock.');
        }

        return ['transaction_id' => (int) $movement->transaction_id, 'sell_line_id' => (int) $movement->id,
            'reference' => $movement->invoice_no ?: $movement->ref_no, 'selected_batch_id' => (int) $source->id,
            'serial' => $source->lot_number, 'quantity' => (float) $movement->quantity,
            'old_source_ids' => $oldIds->map(fn ($id) => (int) $id)->values()->all(),
            'allocations_before' => $mapped->values()->all()];
    }

    /** Pair repeated SKUs using known serials first, then a uniquely determined remainder. */
    private function matchReceivingBatch($batch, $movement, $siblings, $receipts, $destinations, string $serial, array &$snapshots, array $receivingPairs, array $visited): int
    {
        $reference = $movement->ref_no ?: '#'.$movement->transaction_id;
        if ($receipts->count() !== 1 || $receipts->first()->status !== 'received'
            || (int) $receipts->first()->location_id === (int) $batch->location_id
            || $siblings->count() !== $destinations->count()
            || abs((float) $siblings->sum('quantity') - (float) $destinations->sum('quantity')) > 0.0001) {
            $this->blocked((int) $batch->id, 'Transfer '.$reference.' has incomplete or unequal receiving records.');
        }
        $sources = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->whereIn('p.id', $siblings->pluck('lot_no_line_id'))->orderBy('p.id')->lockForUpdate()
            ->get(['p.*', 't.business_id', 't.location_id', 't.status as receipt_status']);
        $allocations = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $siblings->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get();
        $snapshots[] = [$sources, $allocations];
        $serials = [];
        foreach ($siblings as $sibling) {
            $source = $sources->firstWhere('id', $sibling->lot_no_line_id);
            $mapped = $allocations->where('sell_line_id', $sibling->id);
            if (!$source || (int) $source->business_id !== (int) $batch->business_id
                || (int) $source->location_id !== (int) $batch->location_id || $source->receipt_status !== 'received'
                || (int) $source->product_id !== (int) $batch->product_id || (int) $source->variation_id !== (int) $batch->variation_id
                || (float) $sibling->quantity <= 0 || (float) $sibling->quantity_returned > 0 || $mapped->isEmpty()
                || $mapped->contains(fn ($row) => (int) $row->purchase_line_id !== (int) $source->id || (float) $row->qty_returned > 0)
                || abs((float) $mapped->sum('quantity') - (float) $sibling->quantity) > 0.0001) {
                $this->blocked((int) $batch->id, 'Transfer '.$reference.' has conflicting source allocations on line #'.$sibling->id.'.');
            }
            // Pair the existing records before renaming them. A blank source uses the proposed fill.
            $alreadyMatchesTarget = (int) $sibling->id === (int) $movement->id
                && $destinations->contains(fn ($row) => (string) $row->lot_number === $serial
                    && abs((float) $row->quantity - (float) $sibling->quantity) < 0.0001);
            $serials[$sibling->id] = $alreadyMatchesTarget || (isset($visited[(int) $source->id]) && (int) $source->id !== (int) $batch->id)
                || ((int) $source->id === (int) $batch->id && trim((string) $source->lot_number) === '')
                ? $serial : (string) $source->lot_number;
        }
        $compatible = fn ($row, $sibling) => trim((string) $row->lot_number) === ''
            || (string) $row->lot_number === $serials[$sibling->id]
            || ((int) $sibling->id === (int) $movement->id && (string) $row->lot_number === $serial);
        $assigned = [];
        $used = [];
        foreach ($siblings as $sibling) {
            if (!isset($receivingPairs[$sibling->id])) { continue; }
            $choice = $destinations->firstWhere('id', (int) $receivingPairs[$sibling->id]);
            if (!$choice || isset($used[$choice->id])
                || abs((float) $choice->quantity - (float) $sibling->quantity) > 0.0001
                || !$compatible($choice, $sibling)) {
                $this->blocked((int) $batch->id, 'The verified receiving choice for line #'.$sibling->id.' does not match its transfer, serial or quantity.');
            }
            $assigned[$sibling->id] = (int) $choice->id;
            $used[$choice->id] = true;
        }
        foreach ($siblings as $sibling) {
            if (isset($assigned[$sibling->id]) || trim($serials[$sibling->id]) === '') { continue; }
            $exact = $destinations->filter(fn ($row) => (string) $row->lot_number === $serials[$sibling->id]
                && abs((float) $row->quantity - (float) $sibling->quantity) < 0.0001);
            if ($exact->count() === 1) {
                $id = (int) $exact->first()->id;
                if (isset($used[$id])) { continue; }
                $assigned[$sibling->id] = $id;
                $used[$id] = true;
            }
        }
        do {
            $progress = false;
            foreach ($siblings as $sibling) {
                if (isset($assigned[$sibling->id])) { continue; }
                $candidates = $destinations->filter(fn ($row) => !isset($used[$row->id])
                    && $compatible($row, $sibling)
                    && abs((float) $row->quantity - (float) $sibling->quantity) < 0.0001);
                if ($candidates->count() === 1) {
                    $id = (int) $candidates->first()->id;
                    $assigned[$sibling->id] = $id;
                    $used[$id] = true;
                    $progress = true;
                }
            }
        } while ($progress && count($assigned) < $siblings->count());
        if (count($assigned) !== $siblings->count()) {
            // Unknown sibling serials may remain unknown when the current pairing is proved
            // and the untouched remainder is entirely blank with the same quantities.
            $unassigned = $siblings->reject(fn ($row) => isset($assigned[$row->id]));
            $remaining = $destinations->reject(fn ($row) => isset($used[$row->id]));
            $blankRemainder = $unassigned->every(fn ($row) => trim($serials[$row->id]) === '')
                && $remaining->every(fn ($row) => trim((string) $row->lot_number) === '')
                && $unassigned->map(fn ($row) => number_format((float) $row->quantity, 4, '.', ''))->sort()->values()->all()
                    === $remaining->map(fn ($row) => number_format((float) $row->quantity, 4, '.', ''))->sort()->values()->all();
            if (isset($assigned[$movement->id]) && $blankRemainder) { return $assigned[$movement->id]; }
            $candidates = $destinations->filter(fn ($row) => (!isset($used[$row->id]) || ($assigned[$movement->id] ?? null) === (int) $row->id)
                && $compatible($row, $movement)
                && abs((float) $row->quantity - (float) $movement->quantity) < 0.0001);
            if (!isset($assigned[$movement->id]) && $candidates->count() > 1) {
                throw new PurchaseSellMismatch('Transfer '.$reference.' has ambiguous receiving records; no unique pairing can be verified. '
                    .'Choose the verified receiving batch for line #'.$movement->id.' and serial '.$serial.'.', [
                        'kind' => 'receiving_pairing_required', 'receiving_batch_id' => (int) $batch->id,
                        'linked_sell_line_id' => (int) $movement->id, 'linked_transfer_id' => (int) $movement->transaction_id,
                        'linked_reference' => $reference, 'source_serial' => $serial,
                        'candidates' => $candidates->map(fn ($row) => ['id' => (int) $row->id,
                            'serial' => $row->lot_number, 'quantity' => (float) $row->quantity])->values()->all(),
                    ]);
            }
            $this->blocked((int) $batch->id, 'Transfer '.$reference.' has ambiguous receiving records or a different serial; no unique pairing can be verified.');
        }

        return $assigned[$movement->id];
    }

    private function blocked(int $batchId, string $reason): void
    {
        throw new PurchaseSellMismatch('Receiving batch #'.$batchId.': '.$reason.' Reconcile the linked records before filling this serial.', [
            'kind' => 'receiving_batch_dependencies', 'receiving_batch_id' => $batchId,
        ]);
    }
}

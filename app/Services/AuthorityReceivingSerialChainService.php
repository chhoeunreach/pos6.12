<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use Illuminate\Support\Facades\DB;

/** Plans a missing serial fill through unambiguous completed transfer receipts. */
class AuthorityReceivingSerialChainService
{
    public function plan(int $businessId, int $rootBatchId, string $serial): array
    {
        $pending = [$rootBatchId];
        $visited = [];
        $changes = [];
        $snapshots = [];
        $transactionIds = [];
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
            if (trim((string) $batch->lot_number) !== '' && (string) $batch->lot_number !== $serial) {
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
                    't.status as movement_status', 't.transaction_date as movement_date', 't.ref_no']);
            if ($mappings->contains(fn ($mapping) => empty($mapping->sell_line_id) || (float) $mapping->qty_returned > 0)
                || $mappings->pluck('sell_line_id')->diff($movements->pluck('id'))->isNotEmpty()) {
                $this->blocked($id, 'A linked allocation has a return or no valid sale/transfer line.');
            }
            $allocations = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $movements->pluck('id'))
                ->orderBy('id')->lockForUpdate()->get();
            $snapshots[] = [$batch, $mappings, $movements, $allocations];
            if ((float) $movements->sum('quantity') > (float) $batch->quantity + 0.0001) {
                $this->blocked($id, 'The linked movements consume more than the receiving quantity.');
            }
            if ((string) $batch->lot_number !== $serial) {
                $changes[] = ['batch_id' => $id, 'transaction_id' => (int) $batch->transaction_id,
                    'reference' => $batch->ref_no, 'before' => $batch->lot_number, 'after' => $serial,
                    'quantity' => (float) $batch->quantity, 'date' => (string) $batch->receipt_date];
            }
            foreach ($movements as $movement) {
                $mapped = $allocations->where('sell_line_id', $movement->id);
                if ((int) $movement->business_id !== $businessId || $movement->movement_status !== 'final'
                    || !in_array($movement->movement_type, ['sell', 'sell_transfer'], true)
                    || (int) $movement->location_id !== (int) $batch->location_id
                    || (int) $movement->product_id !== (int) $batch->product_id || (int) $movement->variation_id !== (int) $batch->variation_id
                    || (int) $movement->lot_no_line_id !== $id || (float) $movement->quantity <= 0
                    || (float) $movement->quantity_returned > 0 || $mapped->isEmpty()
                    || $mapped->contains(fn ($mapping) => (int) $mapping->purchase_line_id !== $id || (float) $mapping->qty_returned > 0)
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
                $destinationId = $this->matchReceivingBatch($batch, $movement, $siblings, $receipts, $destinations, $serial, $snapshots);
                if (isset($visited[$destinationId])) {
                    $this->blocked($id, 'The connected transfers form a cycle.');
                }
                $pending[] = $destinationId;
            }
        }

        return ['root_batch_id' => $rootBatchId, 'changes' => $changes, 'snapshots' => $snapshots,
            'transaction_ids' => array_values(array_unique($transactionIds))];
    }

    /** Pair repeated SKUs using known serials first, then a uniquely determined remainder. */
    private function matchReceivingBatch($batch, $movement, $siblings, $receipts, $destinations, string $serial, array &$snapshots): int
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
            $serials[$sibling->id] = (int) $source->id === (int) $batch->id ? $serial : (string) $source->lot_number;
            if (trim($serials[$sibling->id]) === '') {
                $this->blocked((int) $batch->id, 'Transfer '.$reference.' has another unknown source serial on line #'.$sibling->id.'.');
            }
        }
        $assigned = [];
        $used = [];
        foreach ($siblings as $sibling) {
            $exact = $destinations->filter(fn ($row) => (string) $row->lot_number === $serials[$sibling->id]
                && abs((float) $row->quantity - (float) $sibling->quantity) < 0.0001);
            if ($exact->count() === 1) {
                $id = (int) $exact->first()->id;
                if (isset($used[$id])) {
                    $this->blocked((int) $batch->id, 'Transfer '.$reference.' has ambiguous receiving serial matches.');
                }
                $assigned[$sibling->id] = $id;
                $used[$id] = true;
            }
        }
        do {
            $progress = false;
            foreach ($siblings as $sibling) {
                if (isset($assigned[$sibling->id])) { continue; }
                $candidates = $destinations->filter(fn ($row) => !isset($used[$row->id])
                    && (trim((string) $row->lot_number) === '' || (string) $row->lot_number === $serials[$sibling->id])
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

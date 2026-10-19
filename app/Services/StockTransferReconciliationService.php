<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

class StockTransferReconciliationService
{
    public function __construct(private LotStockAuditService $audit)
    {
    }

    public function generate(int $businessId, int $transferId): array
    {
        $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->findOrFail($transferId);
        $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
            ->where('transfer_parent_id', $transferId)->firstOrFail();
        $lines = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->orderBy('id')->get();
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lines->pluck('id'))->orderBy('id')->get();
        $ids = $lines->pluck('lot_no_line_id')->merge($mappings->pluck('purchase_line_id'))->filter()->unique()->sort()->values();
        $lots = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->whereIn('p.id', $ids)->orderBy('p.id')
            ->get(['p.*', 't.transaction_date as receipt_date', 't.location_id', 't.status as receipt_status']);
        $blocked = [];
        if ($transfer->status !== 'final' || $receipt->status !== 'received') {
            $blocked[] = 'Only a completed transfer with a received destination can be reconciled.';
        }
        if ($lots->count() !== $ids->count()) {
            $blocked[] = 'A source batch is missing or belongs to another business.';
        }
        $relatedLines = DB::table('transaction_sell_lines as s')->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->where(function ($q) use ($ids) {
                $q->whereIn('s.lot_no_line_id', $ids)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $ids)->select('sell_line_id'));
            })->orderBy('s.id')->get(['s.*', 't.business_id', 't.status', 't.type', 't.ref_no', 't.invoice_no', 't.transaction_date', 't.location_id as movement_location_id']);
        $relatedMappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $relatedLines->pluck('id'))->orderBy('id')->get();
        $report = $this->audit->audit($businessId, null, null, $relatedLines->pluck('transaction_id')->push($transferId)->unique()->all());
        if (DB::table('transaction_sell_lines_purchase_lines as m')->leftJoin('transaction_sell_lines as s', 'm.sell_line_id', '=', 's.id')
            ->whereIn('m.purchase_line_id', $ids)->whereNull('s.id')->exists()) {
            $blocked[] = 'A stock allocation has no source movement. Review the orphaned allocation before rebuilding counters.';
        }
        $reviewItems = [];
        // SKU totals must agree even when there is no serial or a permitted overselling allocation uses receipt ID 0.
        $receivingLines = DB::table('purchase_lines')->where('transaction_id', $receipt->id)->orderBy('id')->get();
        $skuKey = fn ($line) => $line->product_id.':'.$line->variation_id;
        $sentQuantities = $lines->groupBy($skuKey)->map(fn ($group) => (float) $group->sum('quantity'));
        $receivedQuantities = $receivingLines->groupBy($skuKey)->map(fn ($group) => (float) $group->sum('quantity'));
        foreach ($sentQuantities->keys()->merge($receivedQuantities->keys())->unique() as $key) {
            $sentQuantity = $sentQuantities->get($key, 0);
            $receivedQuantity = $receivedQuantities->get($key, 0);
            if (abs($sentQuantity - $receivedQuantity) > 0.0001) {
                $reason = 'Product / SKU '.$key.': sent '.$sentQuantity.', received '.$receivedQuantity.'. Receiving quantities must match the completed transfer.';
                $blocked[] = $reason;
                $reviewItems['receiving-'.$key] = ['reference' => $transfer->ref_no ?: '#'.$transferId, 'line_id' => null,
                    'date' => (string) $transfer->transaction_date, 'status' => 'Receipt quantity mismatch', 'reasons' => [$reason],
                    'url' => action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$transferId])];
            }
        }
        $addLineReview = function (int $lineId, string $reason) use (&$reviewItems, $relatedLines, $businessId) {
            $line = $relatedLines->firstWhere('id', $lineId);
            if (!$line || (int) $line->business_id !== $businessId) { return; }
            $key = 'line-'.$lineId;
            if (!isset($reviewItems[$key])) {
                $url = $line->type === 'sell_transfer'
                    ? action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$line->transaction_id]).'?correct_line='.$lineId
                    : ($line->type === 'sell' && auth()->user()?->can('sell.view')
                        ? action([\App\Http\Controllers\SellController::class, 'show'], [$line->transaction_id]) : null);
                $reviewItems[$key] = ['reference' => $line->ref_no ?: ($line->invoice_no ?: '#'.$line->transaction_id),
                    'line_id' => $lineId, 'date' => $line->transaction_date, 'status' => $line->status,
                    'url' => $url, 'reasons' => []];
            }
            $reviewItems[$key]['reasons'][] = $reason;
        };
        foreach ($relatedLines as $line) {
            $mapped = $relatedMappings->where('sell_line_id', $line->id);
            if ($mapped->isEmpty() && $line->status !== 'final') {
                continue;
            }
            $reason = $this->allocationIssue($businessId, $line, $mapped, $report['transfer_issues'], $allowOverselling);
            if ($reason !== null) {
                $blocked[] = 'Line #'.$line->id.': '.$reason;
                $addLineReview((int) $line->id, $reason);
            }
        }
        $lineIssues = array_values(array_filter($report['sale_line_issues'], fn ($issue) => $relatedLines->contains('id', $issue['sell_line_id'])));
        foreach ($lineIssues as $issue) {
            foreach ($issue['reasons'] as $reason) {
                $blocked[] = 'Line #'.$issue['sell_line_id'].': '.$reason;
                $addLineReview((int) $issue['sell_line_id'], $reason);
            }
        }
        $transferIssues = array_values(array_filter($report['transfer_issues'], fn ($issue) => (int) $issue['transaction_id'] === $transferId
            || $relatedLines->where('type', 'sell_transfer')->contains('transaction_id', $issue['transaction_id'])));
        foreach ($transferIssues as $issue) {
            $blocked[] = $issue['ref_no'].': '.$issue['reason'];
            $reviewItems['transfer-'.$issue['transaction_id']] = ['reference' => $issue['ref_no'], 'line_id' => null,
                'date' => null, 'status' => 'Receipt mismatch', 'reasons' => [$issue['reason']],
                'url' => action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$issue['transaction_id']])];
        }
        foreach ($lines as $line) {
            $mapped = $mappings->where('sell_line_id', $line->id);
            if (abs((float) $mapped->sum('quantity') - (float) $line->quantity) > 0.0001
                || abs((float) $mapped->sum('qty_returned') - (float) $line->quantity_returned) > 0.0001) {
                $blocked[] = 'Line #'.$line->id.': source allocations are incomplete.';
                $addLineReview((int) $line->id, 'Source allocations are incomplete.');
            }
        }
        $updates = [];
        foreach ($report['stock_issues'] as $issue) {
            if (!$ids->contains((int) $issue->id)) {
                continue;
            }
            $lot = $lots->firstWhere('id', $issue->id);
            $sold = (float) $lot->quantity - (float) $lot->quantity_returned - (float) $lot->quantity_adjusted
                - (float) $lot->mfg_quantity_used - (float) $issue->expected_available;
            if ((! $allowOverselling && (float) $issue->expected_available < -0.0001) || $sold < -0.0001 || $lot->receipt_status !== 'received') {
                $blocked[] = 'Batch #'.$issue->id.' has a shortage or an uncompleted receipt; a counter reset cannot repair it.';
                $reviewItems['batch-'.$issue->id] = ['reference' => 'Batch #'.$issue->id.' / '.$lot->lot_number,
                    'line_id' => null, 'date' => $lot->receipt_date, 'status' => $lot->receipt_status,
                    'reasons' => ['History available: '.$issue->expected_available.'. Verify receipts and physical stock; a counter reset cannot repair a shortage.'],
                    'url' => $lot->lot_number ? ssi_route('ssi.lot.history', $lot->lot_number) : null];
            } elseif (abs((float) $lot->quantity_sold - $sold) > 0.0001) {
                $updates[] = ['purchase_line_id' => (int) $lot->id, 'serial' => $lot->lot_number,
                    'quantity_sold_before' => (float) $lot->quantity_sold, 'quantity_sold_after' => $sold];
            }
        }
        $blocked = array_values(array_unique($blocked));
        $plan = [
            'business_id' => $businessId, 'transfer_id' => $transferId, 'reference' => $transfer->ref_no,
            'allow_overselling' => $allowOverselling,
            'status' => $blocked ? 'verification_required' : ($updates ? 'ready' : 'consistent'),
            'blockers' => $blocked, 'line_issues' => $lineIssues, 'transfer_issues' => $transferIssues,
            'review_items' => array_values($reviewItems),
            'counter_repairs' => $blocked ? [] : $updates,
            'requires_physical_verification' => (bool) $blocked,
            'next_steps' => $blocked ? [
                'Confirm delivered serials and actual transfer dates against physical stock and delivery records.',
                'Resolve missing receipts, duplicate movements and source/receiving serial differences with an audited correction.',
                'Generate this plan again after those records agree.',
            ] : ['Only the listed quantity_sold counters will change. Serials, dates, allocations and location quantities will remain unchanged.'],
        ];
        // Include the reviewed evidence so a changed movement invalidates the proposal.
        $plan['token'] = hash_hmac('sha256', json_encode([$plan, $lines, $mappings, $lots, $relatedLines, $relatedMappings,
            $receivingLines], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return $plan;
    }

    public function apply(int $businessId, int $transferId, string $token): array
    {
        return DB::transaction(function () use ($businessId, $transferId, $token) {
            $this->assertTransactionalTables();
            $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->lockForUpdate()->findOrFail($transferId);
            Transaction::where('business_id', $businessId)->where('transfer_parent_id', $transfer->id)->lockForUpdate()->get();
            $lines = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->lockForUpdate()->get();
            $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lines->pluck('id'))->lockForUpdate()->get();
            $ids = $lines->pluck('lot_no_line_id')->merge($mappings->pluck('purchase_line_id'))->filter()->unique()->sort();
            DB::table('purchase_lines')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $plan = $this->generate($businessId, $transferId);
            if (!hash_equals($plan['token'], $token)) {
                throw new PurchaseSellMismatch('Stock records changed. Generate and review a new correction plan.');
            }
            if ($plan['status'] !== 'ready') {
                throw new PurchaseSellMismatch('This proposal cannot be applied automatically. Resolve its verification items first.');
            }
            foreach ($plan['counter_repairs'] as $repair) {
                DB::table('purchase_lines')->where('id', $repair['purchase_line_id'])->update(['quantity_sold' => $repair['quantity_sold_after'], 'updated_at' => now()]);
            }

            return $plan['counter_repairs'];
        });
    }

    /** Repair recorded source selections, including receipt IDs without a serial. */
    public function autoCorrectSelectedSerials(int $businessId, int $transferId): array
    {
        return DB::transaction(function () use ($businessId, $transferId) {
            $this->assertTransactionalTables();
            $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')
                ->lockForUpdate()->findOrFail($transferId);
            $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
                ->where('transfer_parent_id', $transferId)->lockForUpdate()->firstOrFail();
            $lines = $transfer->sell_lines()->with('product')->orderBy('id')->lockForUpdate()->get();
            $destinations = $receipt->purchase_lines()->orderBy('id')->lockForUpdate()->get();
            $stockLines = $lines->filter(fn ($line) => $line->product->enable_stock);
            $selected = DB::table('purchase_lines')->whereIn('id', $stockLines->pluck('lot_no_line_id')->filter())
                ->lockForUpdate()->get()->keyBy('id');
            $withoutSelectedSerial = $stockLines->isNotEmpty() && $stockLines->every(fn ($line) => empty($line->lot_no_line_id)
                || ($selected->has($line->lot_no_line_id) && trim((string) $selected->get($line->lot_no_line_id)->lot_number) === ''));
            if ($withoutSelectedSerial) {
                $recordedSources = DB::table('transaction_sell_lines_purchase_lines as m')
                    ->join('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
                    ->whereIn('m.sell_line_id', $stockLines->pluck('id'))
                    ->lockForUpdate()->get(['p.product_id', 'p.variation_id', 'p.lot_number']);
                foreach ($destinations as $destination) {
                    if (trim((string) $destination->lot_number) !== '' && !$recordedSources->contains(fn ($source) =>
                        (int) $source->product_id === (int) $destination->product_id
                        && (int) $source->variation_id === (int) $destination->variation_id
                        && (string) $source->lot_number === (string) $destination->lot_number)) {
                        throw new PurchaseSellMismatch('A receiving batch has a known serial that does not match the recorded source receipts. Verify that serial before repairing this transfer.');
                    }
                }
                // Use the original FIFO/receipt allocations, never a fabricated lot or a second stock movement.
                $receivingRepair = app(AuthorityForceRepairService::class)->reconcileReceivingForTransfers($businessId, [$transferId]);
                $checked = $this->generate($businessId, $transferId);
                $counterRepairs = [];
                if ($checked['status'] === 'ready') {
                    $counterRepairs = $this->apply($businessId, $transferId, $checked['token']);
                    $checked = $this->generate($businessId, $transferId);
                }
                if ($checked['status'] === 'consistent' && $receivingRepair['mismatched_after'] === 0) {
                    return array_merge($checked, [
                        'changes' => $receivingRepair['transfers'] ? $stockLines->map(fn ($line) => [
                            'sell_line_id' => (int) $line->id, 'kind' => 'receiving_quantity',
                        ])->values()->all() : [],
                        'related_allocation_repairs' => [], 'counter_repairs' => $counterRepairs,
                        'receiving_repairs' => $receivingRepair['transfers'],
                    ]);
                }
                if ($stockLines->contains(fn ($line) => empty($line->lot_no_line_id))) {
                    throw new PurchaseSellMismatch('The transfer has no selected lot. Its recorded source quantities must be complete before receiving can be repaired. '
                        .($checked['blockers'][0] ?? 'Verify the source receipts and allocations.'));
                }
            }
            $corrections = [];
            $used = [];
            foreach ($lines as $line) {
                if (! $line->product->enable_stock) {
                    continue;
                }
                $source = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                    ->where('t.business_id', $businessId)->where('t.location_id', $transfer->location_id)
                    ->where('p.id', $line->lot_no_line_id)->lockForUpdate()->first(['p.id', 'p.lot_number']);
                if (! $source) {
                    throw new PurchaseSellMismatch('Line #'.$line->id.' has no valid selected receipt. Choose its source receipt first.');
                }
                $candidates = $destinations->filter(fn ($row) => ! in_array($row->id, $used, true)
                    && (int) $row->product_id === (int) $line->product_id
                    && (int) $row->variation_id === (int) $line->variation_id
                    && abs((float) $row->quantity - (float) $line->quantity) < 0.0001);
                $matching = $candidates->filter(fn ($row) => (string) $row->lot_number === (string) $source->lot_number);
                $destination = $matching->count() === 1 ? $matching->first()
                    : ($candidates->count() === 1 ? $candidates->first() : null);
                if (! $destination) {
                    throw new PurchaseSellMismatch('Line #'.$line->id.' has no unique receiving batch. Select the correct receiving record first.');
                }
                $used[] = $destination->id;
                $corrections[] = ['sell_line_id' => $line->id, 'source_id' => $source->id, 'destination_id' => $destination->id];
            }

            return $this->correctManual($businessId, $transferId, $corrections,
                'Administrator Auto Correct: use the source receipts recorded on this transfer.', null, null, true, false, false, true);
        });
    }

    public function fixDeductedSerial(int $businessId, int $transferId, int $lineId, bool $administratorRepair = false): array
    {
        return DB::transaction(function () use ($businessId, $transferId, $lineId, $administratorRepair) {
            $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->lockForUpdate()->findOrFail($transferId);
            $line = $transfer->sell_lines()->where('id', $lineId)->lockForUpdate()->firstOrFail();
            $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
                ->where('transfer_parent_id', $transferId)->lockForUpdate()->firstOrFail();
            $source = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('p.id', $line->lot_no_line_id)->where('t.business_id', $businessId)->lockForUpdate()->first(['p.*']);
            if (!$source) {
                throw new PurchaseSellMismatch('Choose the correct source receipt before fixing its deduction.');
            }
            $destinations = $receipt->purchase_lines()->where('product_id', $line->product_id)->where('variation_id', $line->variation_id)
                ->where(function ($query) use ($source) {
                    if (trim((string) $source->lot_number) === '') {
                        $query->whereNull('lot_number')->orWhere('lot_number', '');
                    } else {
                        $query->where('lot_number', $source->lot_number);
                    }
                })->lockForUpdate()->get()
                ->filter(fn ($p) => abs((float) $p->quantity - (float) $line->quantity) < 0.0001);
            if ($destinations->count() !== 1) {
                throw new PurchaseSellMismatch('Selected and receiving serials do not have a unique matching record. Use Verified Serial Correction to choose the correct records.');
            }
            return $this->correctManual($businessId, $transferId, [[
                'sell_line_id' => $lineId, 'source_id' => (int) $source->id, 'destination_id' => (int) $destinations->first()->id,
            ]], 'Deduction correction based on matching recorded source and receiving receipts.', null, null, $administratorRepair, $administratorRepair, $administratorRepair, true);
        });
    }

    public function manualOptions(int $businessId, int $transferId, bool $allowExistingShortageRepair = false, bool $allowBlankSerial = false): array
    {
        $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->findOrFail($transferId);
        $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')->where('transfer_parent_id', $transferId)->firstOrFail();
        $util = app(TransactionUtil::class);
        $lines = $transfer->sell_lines()->orderBy('id')->get();
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lines->pluck('id'))->get();
        $receiptDates = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->where('t.transaction_date', '<=', $transfer->transaction_date)
            ->whereIn('p.id', $lines->pluck('lot_no_line_id')->merge($mappings->pluck('purchase_line_id'))->filter()->unique())
            ->pluck('t.transaction_date', 'p.id');
        $receivingIds = DB::table('purchase_lines')->where('transaction_id', $receipt->id)->pluck('id');
        $referenced = DB::table('transaction_sell_lines')->whereIn('lot_no_line_id', $receivingIds)->pluck('lot_no_line_id')
            ->merge(DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $receivingIds)->pluck('purchase_line_id'));
        $options = [];
        foreach ($lines as $line) {
            $otherLines = $lines->where('id', '!=', $line->id);
            $otherSourceIds = $otherLines->pluck('lot_no_line_id')->merge($mappings->whereIn('sell_line_id', $otherLines->pluck('id'))->pluck('purchase_line_id'))->filter()->unique();
            $dateFloor = $receiptDates->only($otherSourceIds->all())->sort()->last();
            $sources = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                ->where('t.business_id', $businessId)->where('t.location_id', $transfer->location_id)->where('t.status', 'received')
                ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
                ->where('p.product_id', $line->product_id)->where('p.variation_id', $line->variation_id)
                ->when(!$allowBlankSerial, fn ($query) => $query->whereNotNull('p.lot_number')->where('p.lot_number', '!=', ''))
                ->orderBy('t.transaction_date')->orderBy('p.id')
                ->select('p.id', 'p.lot_number', 'p.mfg_date', 'p.exp_date', 't.transaction_date as receipt_date')
                ->selectRaw($util->lotQuantityAvailableSql('p', (int) $line->id).' as available')->get()
                ->filter(fn ($row) => $allowOverselling || (float) $row->available > 0 || (int) $row->id === (int) $line->lot_no_line_id
                    || $mappings->where('sell_line_id', $line->id)->contains('purchase_line_id', $row->id))->values();
            $destinations = DB::table('purchase_lines')->where('transaction_id', $receipt->id)
                ->where('product_id', $line->product_id)->where('variation_id', $line->variation_id)->orderBy('id')
                ->get(['id', 'lot_number', 'quantity', 'quantity_sold', 'quantity_adjusted', 'quantity_returned', 'mfg_quantity_used', 'mfg_date', 'exp_date']);
            foreach ($destinations as $destination) {
                $destination->used = $referenced->contains($destination->id) || (float) $destination->quantity_sold > 0
                    || (float) $destination->quantity_adjusted > 0 || (float) $destination->quantity_returned > 0
                    || (float) $destination->mfg_quantity_used > 0;
            }
            $options[] = ['sell_line_id' => (int) $line->id, 'quantity' => (float) $line->quantity,
                'selected_purchase_line_id' => (int) $line->lot_no_line_id,
                'unchanged_lines_date_floor' => $dateFloor,
                'selected_serial' => optional($sources->firstWhere('id', $line->lot_no_line_id))->lot_number,
                'recommendation' => $this->recommendManualCorrection($transfer, $receipt, $line, $sources, $destinations, $mappings->where('sell_line_id', $line->id), $allowExistingShortageRepair, $allowOverselling),
                'sources' => $sources, 'destinations' => $destinations];
        }

        $shared = collect($options)->filter(fn ($row) => $row['recommendation']['prefill'])
            ->groupBy(fn ($row) => $row['recommendation']['destination_id'])->filter(fn ($rows) => $rows->count() > 1)->keys();
        foreach ($options as &$row) {
            if ($shared->contains($row['recommendation']['destination_id'])) {
                $row['recommendation']['prefill'] = false;
                $row['recommendation']['status'] = 'review_required';
                $row['recommendation']['message'] = 'More than one transfer line matches this receiving batch. Verify each receiving record.';
            }
        }
        unset($row);

        $automaticShared = collect($options)->filter(fn ($row) => !empty($row['recommendation']['automatic_match']))
            ->groupBy(fn ($row) => $row['recommendation']['automatic_match']['destination_id'])
            ->filter(fn ($rows) => $rows->count() > 1)->keys();
        foreach ($options as &$row) {
            if ($automaticShared->contains($row['recommendation']['automatic_match']['destination_id'] ?? null)) {
                $row['recommendation']['automatic_match'] = null;
            }
        }
        unset($row);

        return $options;
    }

    private function recommendManualCorrection($transfer, $receipt, $line, $sources, $destinations, $mappings, bool $allowExistingShortageRepair = false, bool $allowOverselling = false): array
    {
        $selected = $sources->firstWhere('id', $line->lot_no_line_id);
        $allocatedIds = $mappings->pluck('purchase_line_id')->filter()->unique();
        $selectedDestinations = $destinations->filter(fn ($row) => $selected && (string) $row->lot_number === (string) $selected->lot_number
            && abs((float) $row->quantity - (float) $line->quantity) < 0.0001);
        $candidates = [];
        foreach ($sources as $source) {
            $support = [];
            if ((int) $source->id === (int) $line->lot_no_line_id) {
                $support[] = 'Selected source receipt';
            } elseif ($selected && trim((string) $source->lot_number) !== '' && $source->lot_number === $selected->lot_number) {
                $support[] = 'Same serial as selected receipt, different batch';
            }
            if ($allocatedIds->contains($source->id)) {
                $support[] = 'Recorded allocation';
            }
            $matches = $destinations->filter(fn ($row) => (string) $row->lot_number === (string) $source->lot_number
                && abs((float) $row->quantity - (float) $line->quantity) < 0.0001);
            if ($support === [] && ! $selected && !empty($line->lot_no_line_id) && trim((string) $source->lot_number) !== '' && $matches->isNotEmpty()) {
                $support[] = 'Existing receiving serial and quantity';
            }
            if ($support === []) {
                continue;
            }
            if ($matches->isEmpty() && $allocatedIds->contains($source->id) && $selectedDestinations->count() === 1) {
                $matches = $selectedDestinations;
            }
            foreach ($matches as $destination) {
                $issues = [];
                $hasStock = (float) $source->available + 0.0001 >= (float) $line->quantity;
                if (!$hasStock) {
                    $issues[] = $allowOverselling ? 'Overselling is enabled. This allocation may record negative source stock.'
                        : 'Insufficient source stock; a missing receipt or competing movement must be resolved.';
                }
                if (\Carbon::parse($source->receipt_date)->gt(\Carbon::parse($transfer->transaction_date))) {
                    $issues[] = 'Receipt is later than the transfer. A separately verified delivery date is required.';
                }
                $keepReceivingBatch = $allowExistingShortageRepair && (string) $source->lot_number === (string) $destination->lot_number
                    && ! \Carbon::parse($source->receipt_date)->gt(\Carbon::parse($transfer->transaction_date))
                    && $transfer->status === 'final' && $receipt->status === 'received';
                $receivingBlocker = (string) $source->lot_number !== (string) $destination->lot_number
                    ? 'Receiving serial differs and this batch already has later usage. Its serial cannot be changed.'
                    : (\Carbon::parse($source->receipt_date)->gt(\Carbon::parse($transfer->transaction_date))
                        ? 'Source receipt is later than the recorded delivery. Dates of a used receiving batch cannot be changed.'
                        : 'Receiving batch has later usage. An administrator can preview an allocation repair that preserves its serial and dates.');
                if ($destination->used) {
                    $issues[] = $keepReceivingBatch
                        ? 'Receiving batch has later usage. Only its source allocation can be repaired; preserve the receiving batch and delivery dates.'
                        : 'Receiving batch is used or referenced and cannot be relabelled.';
                }
                if ((string) $source->lot_number !== (string) $destination->lot_number) {
                    $issues[] = 'Receiving serial differs. Verify the physical serial before relabelling.';
                } else {
                    $support[] = 'Receiving serial and quantity match';
                }
                if ((float) $line->quantity_returned > 0 || (float) $mappings->sum('qty_returned') > 0) {
                    $issues[] = 'Transfer has returns that must be reconciled first.';
                }
                $allocationAgrees = $mappings->isNotEmpty() && $mappings->every(fn ($m) => (int) $m->purchase_line_id === (int) $source->id)
                    && abs((float) $mappings->sum('quantity') - (float) $line->quantity) < 0.0001;
                if (!$allocationAgrees) {
                    $issues[] = 'Allocation is missing or disagrees with this source receipt.';
                }
                if ($transfer->status !== 'final' || $receipt->status !== 'received') {
                    $issues[] = 'Transfer is not completed and received.';
                }
                $existingShortage = $allowExistingShortageRepair && ! $allowOverselling && ! $hasStock
                    && (int) $source->id === (int) $line->lot_no_line_id
                    && (string) $source->lot_number === (string) $destination->lot_number
                    && ! \Carbon::parse($source->receipt_date)->gt(\Carbon::parse($transfer->transaction_date))
                    && $transfer->status === 'final' && $receipt->status === 'received';
                if ($existingShortage) {
                    $issues[] = 'An administrator can preview repair of this existing allocation. The source shortage must remain unchanged and will still need its original stock records corrected.';
                }
                $candidates[] = ['source_id' => (int) $source->id, 'source_serial' => $source->lot_number,
                    'destination_id' => (int) $destination->id, 'receiving_serial' => $destination->lot_number,
                    'support' => array_values(array_unique($support)), 'issues' => $issues,
                    'selectable' => ($hasStock || $allowOverselling || $existingShortage) && (!$destination->used || $keepReceivingBatch) && (float) $line->quantity_returned === 0.0
                        && (float) $mappings->sum('qty_returned') === 0.0,
                    'unavailable_reason' => !$hasStock && !$allowOverselling && !$existingShortage ? 'Insufficient source stock.' : ($destination->used && !$keepReceivingBatch
                        ? $receivingBlocker
                        : (((float) $line->quantity_returned !== 0.0 || (float) $mappings->sum('qty_returned') !== 0.0)
                            ? 'Return movements must be reconciled first.' : null)),
                    'records_agree' => $issues === [] && (int) $source->id === (int) $line->lot_no_line_id];
            }
        }
        $prefill = count($candidates) === 1 && $candidates[0]['records_agree'];
        $automatic = collect($candidates)->filter(function ($candidate) use ($selected, $sources, $transfer) {
            $source = $sources->firstWhere('id', $candidate['source_id']);

            return $candidate['selectable'] && trim((string) $candidate['source_serial']) !== '' && $candidate['source_serial'] === $candidate['receiving_serial']
                && (!$selected || (int) $selected->id === (int) $candidate['source_id'])
                && $source && ! \Carbon::parse($source->receipt_date)->gt(\Carbon::parse($transfer->transaction_date));
        })->values();

        return ['status' => $prefill ? 'records_agree' : 'review_required', 'prefill' => $prefill,
            'automatic_match' => $automatic->count() === 1
                ? ['source_id' => $automatic[0]['source_id'], 'destination_id' => $automatic[0]['destination_id']] : null,
            'source_id' => $prefill ? $candidates[0]['source_id'] : null,
            'destination_id' => $prefill ? $candidates[0]['destination_id'] : null,
            'message' => $prefill ? 'Selection, allocation and receiving record agree. Physical verification is still required.'
                : ($candidates ? 'Review the recorded defaults and compare the source and receiving records before previewing.'
                    : 'No record-supported match found. Verify the actual source receipt and receiving batch.'),
            'candidates' => $candidates];
    }

    public function previewManual(int $businessId, int $transferId, array $corrections, string $evidence, ?string $verifiedDate = null, bool $repairRelatedAllocations = false, bool $allowExistingShortageRepair = false, bool $allowBlankSerial = false, bool $allowOrphanedSourceAllocations = false): array
    {
        DB::beginTransaction();
        try {
            return $this->correctManual($businessId, $transferId, $corrections, $evidence, null, $verifiedDate, $repairRelatedAllocations, $allowExistingShortageRepair, false, $allowBlankSerial, $allowOrphanedSourceAllocations);
        } finally {
            // The preview exercises the same checks and writes, but saves nothing.
            DB::rollBack();
        }
    }

    public function applyManual(int $businessId, int $transferId, array $corrections, string $evidence, string $token, ?string $verifiedDate = null, bool $repairRelatedAllocations = false, bool $allowExistingShortageRepair = false, bool $allowBlankSerial = false, bool $allowOrphanedSourceAllocations = false): array
    {
        return DB::transaction(fn () => $this->correctManual($businessId, $transferId, $corrections, $evidence, $token, $verifiedDate, $repairRelatedAllocations, $allowExistingShortageRepair, false, $allowBlankSerial, $allowOrphanedSourceAllocations));
    }

    private function correctManual(int $businessId, int $transferId, array $corrections, string $evidence, ?string $token = null, ?string $verifiedDate = null, bool $repairRelatedAllocations = false, bool $allowExistingShortageRepair = false, bool $allowReferencedReceivingRepair = false, bool $allowBlankSerial = false, bool $allowOrphanedSourceAllocations = false): array
    {
        $this->assertTransactionalTables();
        $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        if (strlen(trim($evidence)) < 10 || empty($corrections)) {
            throw new PurchaseSellMismatch('Select verified source and receiving batches and enter supporting evidence.');
        }
        $corrections = collect($corrections)->map(fn ($row) => [
            'sell_line_id' => (int) ($row['sell_line_id'] ?? 0), 'source_id' => (int) ($row['source_id'] ?? 0),
            'destination_id' => (int) ($row['destination_id'] ?? 0),
            'change_receiving_serial' => !empty($row['change_receiving_serial']),
        ])->sortBy('sell_line_id')->values();
        if ($corrections->unique('sell_line_id')->count() !== $corrections->count()
            || $corrections->unique('destination_id')->count() !== $corrections->count()) {
            throw new PurchaseSellMismatch('Each transfer line and receiving batch may be corrected only once.');
        }
        $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->lockForUpdate()->findOrFail($transferId);
        $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
            ->where('transfer_parent_id', $transferId)->lockForUpdate()->firstOrFail();
        if ($transfer->status !== 'final' || $receipt->status !== 'received' || (int) $transfer->location_id === (int) $receipt->location_id) {
            throw new PurchaseSellMismatch('Only a completed, received transfer between different locations can be corrected.');
        }
        $effectiveDate = $verifiedDate ? \Carbon::parse($verifiedDate)->format('Y-m-d H:i:s') : (string) $transfer->transaction_date;
        if (\Carbon::parse($effectiveDate)->isFuture()) {
            throw new PurchaseSellMismatch('A completed transfer cannot be corrected to a future delivery date.');
        }
        $dateChange = $verifiedDate !== null && ($effectiveDate !== (string) $transfer->transaction_date
            || $effectiveDate !== (string) $receipt->transaction_date);
        $receivingBatches = DB::table('purchase_lines')->where('transaction_id', $receipt->id)->orderBy('id')->lockForUpdate()->get();
        if ($dateChange && ($receivingBatches->contains(fn ($row) => (float) $row->quantity_sold > 0 || (float) $row->quantity_adjusted > 0
                || (float) $row->quantity_returned > 0 || (float) $row->mfg_quantity_used > 0)
            || DB::table('transaction_sell_lines')->whereIn('lot_no_line_id', $receivingBatches->pluck('id'))->exists()
            || DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $receivingBatches->pluck('id'))->exists())) {
            throw new PurchaseSellMismatch('Transfer dates cannot change while receiving batches are used or referenced. Keep recorded dates for an allocation-only repair, or reconcile dependent movements before changing dates.',
                $this->dateDependencyDetails($businessId, $transfer, $receivingBatches));
        }
        $lines = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->orderBy('id')->lockForUpdate()->get();
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $lines->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        $changedLines = $lines->whereIn('id', $corrections->pluck('sell_line_id'));
        if ($changedLines->count() !== $corrections->count()) {
            throw new PurchaseSellMismatch('A selected correction line does not belong to this transfer.');
        }
        $sourceIds = $changedLines->pluck('lot_no_line_id')->merge($mappings->whereIn('sell_line_id', $changedLines->pluck('id'))->pluck('purchase_line_id'))
            ->merge($corrections->pluck('source_id'))->filter()->unique()->sort()->values();
        $ids = $sourceIds->merge($corrections->pluck('destination_id'))->unique()->sort()->values();
        $batches = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->whereIn('p.id', $ids)->where('t.business_id', $businessId)->orderBy('p.id')->lockForUpdate()
            ->select('p.*', 't.location_id', 't.status as receipt_status', 't.type as receipt_type', 't.transaction_date as receipt_date')
            ->selectRaw(app(TransactionUtil::class)->lotQuantityAvailableSql('p').' as history_available')->get();
        if ($batches->count() !== $ids->count()) {
            $missingIds = $ids->diff($batches->pluck('id'));
            $requiredIds = $mappings->whereIn('sell_line_id', $changedLines->pluck('id'))->pluck('purchase_line_id')
                ->merge($corrections->pluck('source_id'))->merge($corrections->pluck('destination_id'))->filter()->unique();
            // A verified manual correction may replace a deleted selection reference.
            // Actual deductions and the chosen replacement batches must still exist.
            if ($missingIds->intersect($requiredIds)->isNotEmpty()
                || $missingIds->diff($changedLines->pluck('lot_no_line_id'))->isNotEmpty()
                || DB::table('purchase_lines')->whereIn('id', $missingIds)->lockForUpdate()->exists()) {
                throw new PurchaseSellMismatch('A correction batch is missing or belongs to another business.');
            }
            $sourceIds = $sourceIds->diff($missingIds)->values();
            $ids = $ids->diff($missingIds)->values();
        }
        $related = DB::table('transaction_sell_lines as s')->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->where(function ($q) use ($ids) {
                $q->whereIn('s.lot_no_line_id', $ids)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $ids)->select('sell_line_id'));
            })->orderBy('s.id')->lockForUpdate()->get(['s.*', 't.status', 't.type', 't.business_id', 't.transaction_date', 't.location_id as movement_location_id']);
        $relatedMappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $related->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        $relatedEvidence = [];
        if ($repairRelatedAllocations) {
            $relatedBatchIds = $related->where('business_id', $businessId)->pluck('lot_no_line_id')
                ->merge($relatedMappings->whereIn('sell_line_id', $related->where('business_id', $businessId)->pluck('id'))->pluck('purchase_line_id'))
                ->filter()->unique()->sort()->values();
            $relatedEvidence[] = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                ->where('t.business_id', $businessId)->whereIn('p.id', $relatedBatchIds)->orderBy('p.id')->lockForUpdate()
                ->get(['p.*', 't.status as receipt_status', 't.location_id', 't.transaction_date as receipt_date']);
            $relatedEvidence[] = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                ->where('t.business_id', $businessId)->where('t.type', 'purchase_transfer')
                ->whereIn('t.transfer_parent_id', $related->where('business_id', $businessId)->pluck('transaction_id'))
                ->orderBy('p.id')->lockForUpdate()
                ->get(['p.*', 't.status as receipt_status', 't.location_id', 't.transaction_date as receipt_date']);
        }
        $fingerprint = hash_hmac('sha256', json_encode([$businessId, $transferId, $transfer->status, $transfer->transaction_date,
            $transfer->location_id, $receipt->id, $receipt->status, $receipt->transaction_date, $receipt->location_id,
            $corrections, trim($evidence), $effectiveDate, $lines, $mappings, $batches, $related, $relatedMappings, $receivingBatches,
            $repairRelatedAllocations, $allowExistingShortageRepair, $allowReferencedReceivingRepair, $allowOverselling, $relatedEvidence, $allowBlankSerial], JSON_THROW_ON_ERROR), (string) config('app.key'));
        if ($token !== null && !hash_equals($fingerprint, $token)) {
            throw new PurchaseSellMismatch('Stock records or correction choices changed. Preview the correction again.');
        }
        $relatedTransactionIds = $related->pluck('transaction_id')->push($transferId)->unique()->all();
        $beforeReport = $this->audit->audit($businessId, null, null, $relatedTransactionIds);
        if ($dateChange && \Carbon::parse($effectiveDate)->lt(\Carbon::parse($transfer->transaction_date))) {
            $unchangedLines = $lines->whereNotIn('id', $changedLines->pluck('id'));
            $unchangedSourceIds = $unchangedLines->pluck('lot_no_line_id')
                ->merge($mappings->whereIn('sell_line_id', $unchangedLines->pluck('id'))->pluck('purchase_line_id'))->filter()->unique();
            $blockingReceipt = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')->whereIn('p.id', $unchangedSourceIds)
                ->where('t.transaction_date', '<=', $transfer->transaction_date)->where('t.transaction_date', '>', $effectiveDate)
                ->orderByDesc('t.transaction_date')->first(['p.id', 't.transaction_date']);
            if ($blockingReceipt) {
                $blockingLine = $unchangedLines->first(fn ($row) => (int) $row->lot_no_line_id === (int) $blockingReceipt->id
                    || $mappings->where('sell_line_id', $row->id)->contains('purchase_line_id', $blockingReceipt->id));
                throw new PurchaseSellMismatch('The corrected date would precede a receipt used by unchanged line #'.$blockingLine->id
                    .' (batch #'.$blockingReceipt->id.', received '.$blockingReceipt->transaction_date.'). Keep the recorded date '
                    .$transfer->transaction_date.' or enter an evidenced delivery date on or after '.$blockingReceipt->transaction_date.'.');
            }
        }
        $changes = [];
        $dateRepair = $dateChange ? ['transfer_before' => (string) $transfer->transaction_date, 'receipt_before' => (string) $receipt->transaction_date,
            'transfer_after' => $effectiveDate, 'receipt_after' => $effectiveDate] : null;
        if ($dateChange) {
            DB::table('transactions')->whereIn('id', [$transfer->id, $receipt->id])->update(['transaction_date' => $effectiveDate, 'updated_at' => now()]);
        }
        foreach ($corrections as $correction) {
            $line = $lines->firstWhere('id', $correction['sell_line_id']);
            $source = $batches->firstWhere('id', $correction['source_id']);
            $destination = $batches->firstWhere('id', $correction['destination_id']);
            if (!$source || !$destination || (int) $source->location_id !== (int) $transfer->location_id
                || $source->receipt_status !== 'received' || !in_array($source->receipt_type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                || (int) $source->product_id !== (int) $line->product_id || (int) $source->variation_id !== (int) $line->variation_id
                || (int) $destination->transaction_id !== (int) $receipt->id || (int) $destination->product_id !== (int) $line->product_id
                || (int) $destination->variation_id !== (int) $line->variation_id
                || abs((float) $destination->quantity - (float) $line->quantity) > 0.0001 || (float) $line->quantity <= 0
                || (!$allowBlankSerial && trim((string) $source->lot_number) === '')
                || !DB::table('products')->where('id', $line->product_id)->where('business_id', $businessId)->where('enable_stock', 1)->exists()) {
                throw new PurchaseSellMismatch('Source and receiving batches must match this transfer, product and quantity.');
            }
            if ($allowBlankSerial && trim((string) $source->lot_number) === '' && trim((string) $destination->lot_number) !== '') {
                throw new PurchaseSellMismatch('The source has no serial but the receiving batch has a serial. Enter the verified missing source serial or select the correct receiving batch.');
            }
            if (\Carbon::parse($source->receipt_date)->gt(\Carbon::parse($effectiveDate))) {
                throw new PurchaseSellMismatch('The verified serial arrived after this transfer date. Source receipt #'.$source->id
                    .' arrived '.$source->receipt_date.'; delivery is '.$effectiveDate.'. Enter the actual verified delivery date on or after '
                    .$source->receipt_date.', supported by delivery records, or choose the actual earlier source receipt.', [
                        'kind' => 'receipt_date_conflict', 'source_receipt_id' => (int) $source->id,
                        'source_receipt_date' => (string) $source->receipt_date, 'delivery_date' => $effectiveDate,
                    ]);
            }
            if ((float) $line->quantity_returned > 0 || (float) $mappings->where('sell_line_id', $line->id)->sum('qty_returned') > 0) {
                throw new PurchaseSellMismatch('A transfer with returns cannot be relabelled. Reconcile its return movements first.');
            }
            $receivingUsed = (float) $destination->quantity_sold > 0 || (float) $destination->quantity_adjusted > 0
                || (float) $destination->quantity_returned > 0 || (float) $destination->mfg_quantity_used > 0
                || $related->contains('lot_no_line_id', $destination->id)
                || DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $destination->id)->exists();
            $fillMissingReceivingSerial = $allowBlankSerial && trim((string) $destination->lot_number) === ''
                && trim((string) $source->lot_number) !== '';
            if ($fillMissingReceivingSerial && $receivingUsed) {
                $fillMissingReceivingSerial = !DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                    ->where(function ($query) use ($destination) {
                        $query->where('s.lot_no_line_id', $destination->id)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                            ->where('purchase_line_id', $destination->id)->select('sell_line_id'));
                    })->where(function ($query) use ($businessId) {
                        $query->where('t.type', '!=', 'sell')->orWhere('t.business_id', '!=', $businessId);
                    })->exists();
            }
            $canRepairReferencedReceiving = $repairRelatedAllocations || $allowReferencedReceivingRepair;
            $changeReceivingSerial = $repairRelatedAllocations && $allowBlankSerial && $correction['change_receiving_serial'] && !$dateChange;
            if ($receivingUsed && $changeReceivingSerial && (string) $destination->lot_number !== (string) $source->lot_number) {
                $unsupportedUsage = (float) $destination->quantity_adjusted > 0 || (float) $destination->quantity_returned > 0
                    || (float) $destination->mfg_quantity_used > 0
                    || DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                        ->where(function ($query) use ($destination) {
                            $query->where('s.lot_no_line_id', $destination->id)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                                ->where('purchase_line_id', $destination->id)->select('sell_line_id'));
                        })->where(function ($query) use ($businessId) {
                            $query->where('t.business_id', '!=', $businessId)->orWhere('t.type', '!=', 'sell')
                                ->orWhere('t.status', '!=', 'final')->orWhere('s.quantity_returned', '>', 0);
                        })->exists();
                if ($unsupportedUsage) {
                    throw new PurchaseSellMismatch('This receiving serial / lot supplies a connected transfer, return, adjustment or production movement. Reconcile those records together before changing this serial / lot.');
                }
            }
            $preserveReceiving = $receivingUsed && $canRepairReferencedReceiving && ! $dateChange
                && ((string) $destination->lot_number === (string) $source->lot_number || $fillMissingReceivingSerial || $changeReceivingSerial);
            $receivingMfgDate = $preserveReceiving ? $destination->mfg_date : $source->mfg_date;
            $receivingExpDate = $preserveReceiving ? $destination->exp_date : $source->exp_date;
            $receivingChanges = (string) $destination->lot_number !== (string) $source->lot_number
                || $destination->mfg_date !== $receivingMfgDate || $destination->exp_date !== $receivingExpDate;
            if ($receivingUsed && (! $canRepairReferencedReceiving || ($receivingChanges && !$fillMissingReceivingSerial && !$changeReceivingSerial) || $dateChange)) {
                throw new PurchaseSellMismatch('The receiving batch is used or referenced by a later movement. Keep its serial, batch details and delivery dates unchanged to preview an administrator allocation repair.');
            }
            $changes[] = ['sell_line_id' => (int) $line->id, 'selected_batch_before' => (int) $line->lot_no_line_id,
                'selected_batch_after' => (int) $source->id, 'verified_serial' => $source->lot_number,
                'receiving_batch_id' => (int) $destination->id, 'receiving_serial_before' => $destination->lot_number,
                'receiving_serial_after' => $source->lot_number, 'quantity' => (float) $line->quantity,
                'receiving_mfg_date_before' => $destination->mfg_date, 'receiving_mfg_date_after' => $receivingMfgDate,
                'receiving_exp_date_before' => $destination->exp_date, 'receiving_exp_date_after' => $receivingExpDate,
                'receiving_batch_preserved' => $preserveReceiving && !$receivingChanges,
                'allocations_before' => $mappings->where('sell_line_id', $line->id)->values()->all()];
            DB::table('transaction_sell_lines')->where('id', $line->id)->update(['lot_no_line_id' => $source->id, 'updated_at' => now()]);
            DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->delete();
            DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line->id, 'purchase_line_id' => $source->id,
                'quantity' => $line->quantity, 'qty_returned' => 0, 'created_at' => now(), 'updated_at' => now()]);
            if ($receivingChanges) {
                DB::table('purchase_lines')->where('id', $destination->id)->update(['lot_number' => $source->lot_number,
                    'mfg_date' => $receivingMfgDate, 'exp_date' => $receivingExpDate, 'updated_at' => now()]);
            }
        }
        $relatedRepairs = ['changes' => [], 'source_ids' => [], 'preserved_source_ids' => [], 'blockers' => []];
        $receivingReconcile = ['transfers' => [], 'updated' => 0, 'inserted' => 0, 'deleted' => 0];
        if ($repairRelatedAllocations) {
            $relatedRepairs = $this->repairRelatedSelectedAllocations($businessId, $related, $relatedMappings,
                $changedLines->pluck('id')->all(), $beforeReport, $allowExistingShortageRepair, $allowBlankSerial);
            $sourceIds = $sourceIds->merge($relatedRepairs['source_ids'])->unique()->sort()->values();
            $ids = $ids->merge($relatedRepairs['source_ids'])->unique()->sort()->values();
            // With overselling the related lines were re-pointed to their recorded selected serials
            // above. Rebuild any transfer receiving that still does not corroborate those serials so
            // the correction finishes automatically instead of deferring the mismatch to manual review.
            if ($allowOverselling) {
                $reconcileIds = collect($beforeReport['transfer_issues'])->pluck('transaction_id')
                    ->map(fn ($value) => (int) $value)->unique()->values()->all();
                if ($reconcileIds) {
                    $receivingReconcile = app(\App\Services\AuthorityForceRepairService::class)->reconcileReceivingForTransfers($businessId, $reconcileIds);
                }
            }
        }
        $report = $this->audit->audit($businessId, null, null, $relatedTransactionIds);
        foreach ($report['sale_line_issues'] as $issue) {
            if ($related->contains('id', $issue['sell_line_id'])) {
                $lineId = (int) $issue['sell_line_id'];
                $blocker = $relatedRepairs['blockers'][$lineId] ?? null;
                $url = ssi_route('ssi.mismatch.index').'?'.http_build_query(['view' => 'stock_errors', 'line_id' => $lineId]);
                throw new PurchaseSellMismatch('Related line #'.$lineId.' still needs reconciliation: '
                    .($blocker ?: implode(' ', $issue['reasons'])), [
                        'kind' => 'related_allocation', 'sell_line_id' => $lineId,
                        'reason' => $blocker ?: implode(' ', $issue['reasons']), 'url' => $url,
                    ]);
            }
        }
        foreach ($report['transfer_issues'] as $issue) {
            if ((int) $issue['transaction_id'] !== $transferId) {
                continue;
            }
            $beforeIssue = collect($beforeReport['transfer_issues'])->first(fn ($old) => (int) $old['transaction_id'] === $transferId
                && ($old['product_id'] ?? null) === ($issue['product_id'] ?? null)
                && ($old['variation_id'] ?? null) === ($issue['variation_id'] ?? null)
                && $old['selected_serial'] === $issue['selected_serial']);
            $beforeDifference = $beforeIssue ? $beforeIssue['source_quantity'] - $beforeIssue['matching_received_quantity'] : 0;
            $afterDifference = $issue['source_quantity'] - $issue['matching_received_quantity'];
            if (abs($afterDifference) > abs($beforeDifference) + 0.0001 || $beforeDifference * $afterDifference < -0.0001) {
                throw new PurchaseSellMismatch('This correction would worsen another line\'s receiving serial balance. Select the receiving batch belonging to this line.');
            }
        }
        $unchangedSources = collect($changes)->filter(fn ($change) => $change['selected_batch_before'] === $change['selected_batch_after']
            && $change['receiving_serial_before'] === $change['receiving_serial_after']
            && $change['receiving_mfg_date_before'] === $change['receiving_mfg_date_after']
            && $change['receiving_exp_date_before'] === $change['receiving_exp_date_after'])->pluck('selected_batch_after')
            ->merge($relatedRepairs['preserved_source_ids'])->unique();
        $remainingShortages = [];
        foreach ($report['stock_issues'] as $issue) {
            if ($ids->contains((int) $issue->id) && (float) $issue->expected_available < -0.0001) {
                $before = collect($beforeReport['stock_issues'])->firstWhere('id', (int) $issue->id);
                if ($allowOverselling) {
                    $original = $batches->firstWhere('id', (int) $issue->id);
                    $remainingShortages[] = $this->sourceShortageDetails($businessId, (int) $issue->id,
                        (float) ($before->expected_available ?? $original->history_available ?? 0), (float) $issue->expected_available, true);
                    continue;
                }
                if ($allowExistingShortageRepair && ! $dateChange && $unchangedSources->contains((int) $issue->id)
                    && $before && (float) $before->expected_available < -0.0001
                    && abs((float) $issue->expected_available - (float) $before->expected_available) < 0.0001) {
                    $remainingShortages[] = $this->sourceShortageDetails($businessId, (int) $issue->id,
                        (float) $before->expected_available, (float) $issue->expected_available);
                    continue;
                }
                throw new PurchaseSellMismatch('Batch #'.$issue->id.' would have negative stock. Resolve its competing movements first.');
            }
        }
        $verifiedMappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $related->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($related as $line) {
            $mapped = $verifiedMappings->where('sell_line_id', $line->id);
            if ($changedLines->contains('id', $line->id) || ($mapped->isEmpty() && $line->status !== 'final')) {
                continue;
            }
            $reason = $this->allocationIssue($businessId, $line, $mapped, $report['transfer_issues'], $allowOverselling);
            if ($reason !== null) {
                $reference = DB::table('transactions')->where('business_id', $businessId)->where('id', $line->transaction_id)
                    ->first(['ref_no', 'invoice_no']);
                $url = $line->type === 'sell_transfer'
                    ? action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$line->transaction_id]).'?correct_line='.$line->id
                    : ($line->type === 'sell' && auth()->user()?->can('sell.view')
                        ? action([\App\Http\Controllers\SellController::class, 'show'], [$line->transaction_id]) : null);
                throw new PurchaseSellMismatch('Related line #'.$line->id.' has an unverified allocation: '.$reason, [
                    'kind' => 'related_allocation', 'sell_line_id' => (int) $line->id, 'reason' => $reason,
                    'reference' => $reference?->ref_no ?: ($reference?->invoice_no ?: '#'.$line->transaction_id),
                    'status' => (int) $line->business_id === $businessId ? $line->status : 'not accessible',
                    'date' => (int) $line->business_id === $businessId ? $line->transaction_date : null,
                    'url' => (int) $line->business_id === $businessId ? $url : null,
                ]);
            }
        }
        if (! $allowOrphanedSourceAllocations) {
            if (DB::table('transaction_sell_lines_purchase_lines as m')->leftJoin('transaction_sell_lines as s', 'm.sell_line_id', '=', 's.id')
                ->whereIn('m.purchase_line_id', $sourceIds)->whereNull('s.id')->exists()) {
                throw new PurchaseSellMismatch('An orphaned source allocation must be reconciled first.');
            }
        } else {
            // Authority corrections bypass the block, but only truly dangling rows (no sell line and
            // not an adjustment allocation) are removed; adjustment rows keep their legitimate link.
            $dangling = DB::table('transaction_sell_lines_purchase_lines as m')->leftJoin('transaction_sell_lines as s', 'm.sell_line_id', '=', 's.id')
                ->whereIn('m.purchase_line_id', $sourceIds)
                ->whereNull('s.id')
                ->whereNotNull('m.sell_line_id')
                ->select('m.id');
            if (\Illuminate\Support\Facades\Schema::hasColumn('transaction_sell_lines_purchase_lines', 'stock_adjustment_line_id')) {
                $dangling->whereNull('m.stock_adjustment_line_id');
            }
            $danglingIds = $dangling->pluck('m.id');
            if ($danglingIds->isNotEmpty()) {
                DB::table('transaction_sell_lines_purchase_lines')->whereIn('id', $danglingIds)->delete();
            }
        }
        $util = app(TransactionUtil::class);
        $counters = DB::table('purchase_lines as p')->whereIn('p.id', $sourceIds)->orderBy('p.id')->select('p.id', 'p.quantity_sold')
            ->selectRaw($util->lotQuantitySoldSql('p', true).' as verified_sold')
            ->selectRaw($util->lotQuantityAvailableSql('p').' as available')->get();
        $repairs = [];
        foreach ($counters as $counter) {
            if (((float) $counter->available < -0.0001 && ! collect($remainingShortages)->contains('purchase_line_id', (int) $counter->id))
                || (float) $counter->verified_sold < -0.0001) {
                throw new PurchaseSellMismatch('Batch #'.$counter->id.' would have negative stock. Choose the actual available source receipt; no stock will be invented.');
            }
            $repairs[] = ['purchase_line_id' => (int) $counter->id, 'quantity_sold_before' => (float) $counter->quantity_sold,
                'quantity_sold_after' => (float) $counter->verified_sold];
            DB::table('purchase_lines')->where('id', $counter->id)->update(['quantity_sold' => $counter->verified_sold, 'updated_at' => now()]);
        }

        return ['status' => 'ready', 'transfer_id' => $transferId, 'reference' => $transfer->ref_no,
            'token' => $fingerprint, 'evidence' => trim($evidence), 'changes' => $changes, 'counter_repairs' => $repairs,
            'date_change' => $dateRepair, 'related_allocation_repairs' => $relatedRepairs['changes'],
            'receiving_repairs' => $receivingReconcile,
            'remaining_stock_shortages' => $remainingShortages, 'allow_overselling' => $allowOverselling,
            'remaining_line_issues' => array_values(array_filter($report['sale_line_issues'], fn ($issue) => (int) $issue['transaction_id'] === $transferId
                && !$changedLines->contains('id', $issue['sell_line_id']))),
            'remaining_transfer_issues' => array_values(array_filter($report['transfer_issues'], fn ($issue) => (int) $issue['transaction_id'] === $transferId)),
            'unchanged' => $dateChange ? ['product_quantities', 'location_quantities', 'prices', 'payments']
                : ['transfer_dates', 'product_quantities', 'location_quantities', 'prices', 'payments']];
    }

    /** Repair only related mappings whose recorded selection and receiving serial already agree. */
    private function repairRelatedSelectedAllocations(int $businessId, $related, $mappings, array $changedIds, array $beforeReport, bool $allowExistingShortageRepair = false, bool $allowBlankSerial = false): array
    {
        $changes = [];
        $sourceIds = [];
        $preservedSourceIds = [];
        $blockers = [];
        $util = app(TransactionUtil::class);
        $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        foreach ($beforeReport['sale_line_issues'] as $issue) {
            $line = $related->firstWhere('id', $issue['sell_line_id']);
            if (! $line || in_array((int) $line->id, array_map('intval', $changedIds), true)) {
                continue;
            }
            $mapped = $mappings->where('sell_line_id', $line->id);
            if ((int) $line->business_id !== $businessId || $line->status !== 'final'
                || ! in_array($line->type, ['sell', 'sell_transfer'], true)
                || empty($line->lot_no_line_id) || (float) $line->quantity <= 0
                || (float) $line->quantity_returned > 0 || (float) $mapped->sum('qty_returned') > 0) {
                $blockers[(int) $line->id] = 'The original movement is incomplete, has returns, or has no valid selected source receipt.';
                continue;
            }
            $source = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                ->where('t.business_id', $businessId)->where('p.id', $line->lot_no_line_id)->lockForUpdate()
                ->select('p.*', 't.location_id', 't.status as receipt_status', 't.type as receipt_type', 't.transaction_date as receipt_date')
                ->selectRaw($util->lotQuantityAvailableSql('p', (int) $line->id).' as available')->first();
            if (! $source || (!$allowBlankSerial && trim((string) $source->lot_number) === '') || $source->receipt_status !== 'received'
                || ! in_array($source->receipt_type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                || (int) $source->location_id !== (int) $line->movement_location_id
                || (int) $source->product_id !== (int) $line->product_id
                || (int) $source->variation_id !== (int) $line->variation_id
                || ! DB::table('products')->where('id', $line->product_id)->where('business_id', $businessId)->where('enable_stock', 1)->exists()) {
                $blockers[(int) $line->id] = 'The selected source receipt is missing, not received, or does not match the product and source location.';
                continue;
            }
            $existingShortage = (float) $source->available + 0.0001 < (float) $line->quantity;
            if ($existingShortage && !$allowOverselling && !$allowExistingShortageRepair) {
                $blockers[(int) $line->id] = 'The selected source has insufficient stock. An administrator may repair the existing allocation only while preserving the recorded shortage.';
                continue;
            }
            if ($line->type === 'sell_transfer') {
                // A later wrong allocation is repairable only if the selected receipt predates delivery.
                if (\Carbon::parse($source->receipt_date)->gt(\Carbon::parse($line->transaction_date))) {
                    $blockers[(int) $line->id] = 'The selected source receipt #'.$source->id.' arrived '.$source->receipt_date
                        .', after the recorded transfer '.$line->transaction_date.'. Choose the actual earlier source receipt in Verified correction.';
                    continue;
                }
                $serialMismatch = fn ($issue) => (int) $issue['transaction_id'] === (int) $line->transaction_id
                    && (int) ($issue['product_id'] ?? 0) === (int) $line->product_id
                    && (int) ($issue['variation_id'] ?? 0) === (int) $line->variation_id
                    && $issue['selected_serial'] === $source->lot_number;
                // With overselling enabled the administrator expects this correction to auto-calculate
                // completely: re-point the line to its recorded selected serial and then rebuild the
                // transfer receiving to corroborate it, instead of deferring to a manual correction.
                if (! $allowOverselling && collect($beforeReport['transfer_issues'])->contains($serialMismatch)) {
                    $blockers[(int) $line->id] = 'The related transfer receiving serial or quantity does not match selected serial '.$source->lot_number.'. Open Verified correction for this related line.';
                    continue;
                }
                $receipt = DB::table('transactions')->where('business_id', $businessId)->where('type', 'purchase_transfer')
                    ->where('transfer_parent_id', $line->transaction_id)->where('status', 'received')->lockForUpdate()->first();
                if (! $receipt || (int) $receipt->location_id === (int) $line->movement_location_id) {
                    $blockers[(int) $line->id] = 'The related transfer has no completed receipt at a valid destination location.';
                    continue;
                }
                // Lock the unchanged receiving records that support this correction.
                DB::table('purchase_lines')->where('transaction_id', $receipt->id)->orderBy('id')->lockForUpdate()->get();
                if (! $allowOverselling && collect($this->audit->audit($businessId, null, (int) $line->transaction_id)['transfer_issues'])->contains($serialMismatch)) {
                    $blockers[(int) $line->id] = 'The related transfer receiving serial or quantity changed during correction. Review this line again.';
                    continue;
                }
            }
            $oldIds = $mapped->pluck('purchase_line_id')->filter()->unique()->values();
            $oldSources = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
                ->whereIn('p.id', $oldIds)->where('t.business_id', $businessId)->orderBy('p.id')->lockForUpdate()->get(['p.id']);
            if ($oldSources->count() !== $oldIds->count()) {
                $blockers[(int) $line->id] = 'An existing deducted receipt is missing or belongs to another business.';
                continue;
            }
            DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->delete();
            DB::table('transaction_sell_lines_purchase_lines')->insert([
                'sell_line_id' => $line->id, 'purchase_line_id' => $source->id,
                'quantity' => $line->quantity, 'qty_returned' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $changes[] = ['transaction_id' => (int) $line->transaction_id, 'sell_line_id' => (int) $line->id,
                'selected_batch_id' => (int) $source->id, 'serial' => $source->lot_number,
                'quantity' => (float) $line->quantity, 'allocations_before' => $mapped->values()->all()];
            $sourceIds = array_merge($sourceIds, $oldIds->all(), [(int) $source->id]);
            if ($existingShortage && !$allowOverselling && $allowExistingShortageRepair) {
                $preservedSourceIds[] = (int) $source->id;
            }
        }

        return ['changes' => $changes, 'source_ids' => array_values(array_unique($sourceIds)),
            'preserved_source_ids' => array_values(array_unique($preservedSourceIds)), 'blockers' => $blockers];
    }

    /** Validate both explicit selections and FIFO mappings against their actual receipts. */
    private function allocationIssue(int $businessId, $line, $mappings, array $transferIssues, bool $allowOverselling = false): ?string
    {
        if ((int) $line->business_id !== $businessId) { return 'The movement belongs to another business.'; }
        if ($line->status !== 'final') { return 'The original movement is not completed (status: '.$line->status.').'; }
        if (! in_array($line->type, ['sell', 'sell_transfer'], true)) { return 'The original movement type is unsupported.'; }
        if ((float) $line->quantity <= 0 || (float) $line->quantity_returned < 0
            || (float) $line->quantity_returned > (float) $line->quantity + 0.0001
            || abs((float) $mappings->sum('quantity') - (float) $line->quantity) > 0.0001
            || abs((float) $mappings->sum('qty_returned') - (float) $line->quantity_returned) > 0.0001) {
            return 'Allocation and return quantities do not match the original movement.';
        }
        foreach ($mappings as $mapping) {
            if (empty($mapping->purchase_line_id) && ! ($allowOverselling && empty($line->lot_no_line_id))) {
                return 'The original movement contains an unallocated quantity.';
            }
            if ((float) $mapping->quantity <= 0 || (float) $mapping->qty_returned < 0
                || (float) $mapping->qty_returned > (float) $mapping->quantity + 0.0001) {
                return 'An allocation contains invalid movement or return quantities.';
            }
            if (! empty($line->lot_no_line_id) && (int) $mapping->purchase_line_id !== (int) $line->lot_no_line_id) {
                return 'The allocation differs from the recorded selected receipt.';
            }
        }
        $ids = $mappings->pluck('purchase_line_id')->filter()->unique();
        $sources = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->whereIn('p.id', $ids)
            ->get(['p.id', 'p.product_id', 'p.variation_id', 't.status', 't.location_id', 't.type', 't.transaction_date']);
        if ($sources->count() !== $ids->count()) { return 'An allocated receipt is missing or belongs to another business.'; }
        foreach ($sources as $source) {
            if ($source->status !== 'received'
                || ! in_array($source->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                || (int) $source->location_id !== (int) $line->movement_location_id
                || (int) $source->product_id !== (int) $line->product_id || (int) $source->variation_id !== (int) $line->variation_id) {
                return 'An allocated receipt does not match the product, source location or received status.';
            }
            if ($line->type === 'sell_transfer' && \Carbon::parse($source->transaction_date)->gt(\Carbon::parse($line->transaction_date))) {
                return 'An allocated receipt is dated after the original transfer.';
            }
        }
        if ($line->type === 'sell_transfer' && empty($line->lot_no_line_id)) {
            foreach ($transferIssues as $issue) {
                if ((int) $issue['transaction_id'] === (int) $line->transaction_id
                    && (int) ($issue['product_id'] ?? 0) === (int) $line->product_id
                    && (int) ($issue['variation_id'] ?? 0) === (int) $line->variation_id) {
                    return 'FIFO source allocations and received serial quantities differ.';
                }
            }
        }

        return null;
    }

    private function sourceShortageDetails(int $businessId, int $batchId, float $before, float $after, bool $allowOverselling = false): array
    {
        $batch = DB::table('purchase_lines as p')->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->where('p.id', $batchId)
            ->first(['p.id', 'p.lot_number', 'p.quantity', 't.location_id']);
        $movements = DB::table('transaction_sell_lines as s')->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->where('t.status', 'final')->whereIn('t.type', ['sell', 'sell_transfer'])
            ->where(function ($query) use ($batchId) {
                $query->where('s.lot_no_line_id', $batchId)->orWhere(function ($fifo) use ($batchId) {
                    $fifo->where(function ($unselected) { $unselected->whereNull('s.lot_no_line_id')->orWhere('s.lot_no_line_id', 0); })
                        ->whereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $batchId)->select('sell_line_id'));
                });
            })->orderBy('t.transaction_date')->orderBy('s.id')
            ->get(['s.id', 's.lot_no_line_id', 's.quantity', 's.quantity_returned', 't.id as transaction_id', 't.type', 't.ref_no', 't.invoice_no', 't.transaction_date']);
        $history = [];
        foreach ($movements as $movement) {
            $quantity = (float) $movement->quantity - (float) $movement->quantity_returned;
            if (empty($movement->lot_no_line_id)) {
                $quantity = (float) DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $movement->id)
                    ->where('purchase_line_id', $batchId)->selectRaw('COALESCE(SUM(quantity - COALESCE(qty_returned, 0)), 0) as used')->value('used');
            }
            if ($quantity <= 0.0001) { continue; }
            $url = $movement->type === 'sell_transfer'
                ? action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$movement->transaction_id]).'?correct_line='.$movement->id
                : (auth()->user()?->can('sell.view') ? action([\App\Http\Controllers\SellController::class, 'show'], [$movement->transaction_id]) : null);
            $history[] = ['sell_line_id' => (int) $movement->id, 'reference' => $movement->ref_no ?: ($movement->invoice_no ?: '#'.$movement->transaction_id),
                'date' => $movement->transaction_date, 'quantity' => $quantity, 'url' => $url];
        }

        return ['purchase_line_id' => $batchId, 'serial' => $batch->lot_number, 'received_quantity' => (float) $batch->quantity,
            'available_before' => $before, 'available_after' => $after, 'movements' => $history, 'allow_overselling' => $allowOverselling,
            'message' => $allowOverselling ? 'Overselling is enabled. Negative source stock is recorded without adding receipt quantities or changing receipt dates.'
                : 'The existing source shortage remains unchanged. This allocation repair does not add stock. Review prior movements and record any actual missing return receipt.'];
    }

    private function dateDependencyDetails(int $businessId, Transaction $transfer, $receivingBatches): array
    {
        $ids = $receivingBatches->pluck('id');
        $movements = DB::table('transaction_sell_lines as s')->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)
            ->where(function ($q) use ($ids) {
                $q->whereIn('s.lot_no_line_id', $ids)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                    ->whereIn('purchase_line_id', $ids)->select('sell_line_id'));
            })->orderByDesc('t.transaction_date')->orderByDesc('s.id')
            ->get(['s.id as sell_line_id', 's.lot_no_line_id', 's.quantity', 's.quantity_returned',
                't.id as transaction_id', 't.ref_no', 't.invoice_no', 't.transaction_date', 't.type', 't.status']);
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $movements->pluck('sell_line_id'))
            ->whereIn('purchase_line_id', $ids)->get();
        $dependencies = [];
        $links = [];
        foreach ($movements as $movement) {
            $batchIds = $mappings->where('sell_line_id', $movement->sell_line_id)->pluck('purchase_line_id');
            if ($ids->contains($movement->lot_no_line_id)) {
                $batchIds->push($movement->lot_no_line_id);
            }
            $serials = $receivingBatches->whereIn('id', $batchIds->unique())->pluck('lot_number')->filter()->unique()->values()->all();
            $reference = $movement->ref_no ?: ($movement->invoice_no ?: '#'.$movement->transaction_id);
            $dependencies[] = ['transaction_id' => (int) $movement->transaction_id,
                'sell_line_id' => (int) $movement->sell_line_id, 'reference' => $reference,
                'date' => $movement->transaction_date, 'type' => $movement->type, 'status' => $movement->status,
                'serials' => $serials, 'quantity' => (float) $movement->quantity - (float) $movement->quantity_returned];
            if ($movement->type === 'sell_transfer') {
                $links[$movement->transaction_id] = ['label' => 'Review transfer '.$reference,
                    'url' => action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$movement->transaction_id])];
            } elseif ($movement->type === 'sell' && auth()->user()?->can('sell.view')) {
                $links[$movement->transaction_id] = ['label' => 'Review invoice '.$reference,
                    'url' => action([\App\Http\Controllers\SellController::class, 'show'], [$movement->transaction_id])];
            }
        }

        return ['kind' => 'transfer_date_dependencies', 'reference' => $transfer->ref_no,
            'recorded_date' => (string) $transfer->transaction_date, 'dependent_movements' => $dependencies,
            'receiving_batches' => $receivingBatches->map(fn ($row) => ['id' => (int) $row->id, 'serial' => $row->lot_number,
                'sold' => (float) $row->quantity_sold, 'adjusted' => (float) $row->quantity_adjusted,
                'returned' => (float) $row->quantity_returned, 'manufacturing_used' => (float) $row->mfg_quantity_used])->values()->all(),
            'links' => array_values($links), 'steps' => [
                'Keep both recorded document dates for an allocation-only repair. Leave Change delivery date unchecked in Authority and preview again.',
                'If the date is genuinely wrong, review the dependent documents below, newest first, against physical serials and delivery records.',
                'Correct only erroneous references through their authorized document workflow. Do not delete or reverse valid movements to bypass this check.',
                'After dependent records are reconciled, return here and generate a fresh preview with the evidenced delivery date.',
            ]];
    }

    public function assertTransactionalTables(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        $tables = collect(['transactions', 'purchase_lines', 'transaction_sell_lines', 'transaction_sell_lines_purchase_lines', 'variation_location_details',
            config('activitylog.table_name', 'activity_log')])->map(fn ($table) => DB::connection()->getTablePrefix().$table)->unique();
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)->get(['TABLE_NAME', 'ENGINE']);
        if ($engines->count() !== $tables->count() || $engines->contains(fn ($row) => strtolower((string) $row->ENGINE) !== 'innodb')) {
            throw new PurchaseSellMismatch('Safe correction requires transactional InnoDB stock and audit tables. No correction was saved.');
        }
    }
}

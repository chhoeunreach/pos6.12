<?php

namespace App\Services;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/** Read-only stock diagnostics, restricted to the business and permitted locations. */
class StockMismatchDetectorService
{
    use Concerns\RaisesBulkMemoryLimits;

    /** Quantity mismatches displayed by Product Stock Details, one row per product/location. */
    public function detectQuantityMismatches(array $filters): array
    {
        $this->raiseLimits();
        $businessId = (int) $filters['business_id'];
        $locations = DB::table('business_locations')->where('business_id', $businessId)
            ->whereIn('id', $filters['location_ids'])->pluck('name', 'id');
        $eligible = null;
        if (!empty($filters['purchase_ref']) || !empty($filters['purchase_date_from']) || !empty($filters['purchase_date_to'])) {
            $eligible = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('t.business_id', $businessId)->whereIn('t.location_id', $locations->keys())->where('t.type', 'purchase')
                ->when(!empty($filters['purchase_ref']), fn ($q) => $q->where('t.ref_no', 'like', '%'.$filters['purchase_ref'].'%'))
                ->when(!empty($filters['purchase_date_from']), fn ($q) => $q->whereDate('t.transaction_date', '>=', $filters['purchase_date_from']))
                ->when(!empty($filters['purchase_date_to']), fn ($q) => $q->whereDate('t.transaction_date', '<=', $filters['purchase_date_to']))
                ->get(['p.variation_id', 't.location_id'])->mapWithKeys(fn ($p) => [$p->variation_id.':'.$p->location_id => true])->all();
        }
        $rows = [];
        foreach ($locations as $locationId => $locationName) {
            $details = app(\App\Utils\ProductUtil::class)->getVariationStockMisMatch($businessId, null, $locationId);
            foreach ($details as $stock) {
                if (round((float) $stock->stock) === round((float) $stock->total_stock_calculated)
                    || ($eligible !== null && !isset($eligible[$stock->variation_id.':'.$locationId]))) {
                    continue;
                }
                $rows[] = [
                    'product_id' => (int) $stock->product_id, 'variation_id' => (int) $stock->variation_id,
                    'location_id' => (int) $locationId, 'location' => $locationName,
                    'product' => $stock->product, 'sku' => $stock->sub_sku ?: $stock->sku,
                    'available_qty' => (float) $stock->stock, 'expected_qty' => (float) $stock->total_stock_calculated,
                    'difference' => (float) $stock->total_stock_calculated - (float) $stock->stock,
                    'can_fix_quantity' => true, 'problem' => 'Product stock quantity mismatch',
                    'url' => url('/reports/product-stock-details').'?'.http_build_query(['variation_id' => $stock->variation_id, 'location_id' => $locationId]),
                ];
            }
        }
        return $rows;
    }

    public function detectTransferIssues(array $filters, ?array $lastFailure = null): array
    {
        $this->raiseLimits();
        $transfer = \App\Transaction::where('business_id', (int) $filters['business_id'])->where('type', 'sell_transfer')
            ->whereIn('location_id', $filters['location_ids'])->findOrFail((int) $filters['transfer_id']);
        $lines = DB::table('transaction_sell_lines as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->join('variations as v', 'v.id', '=', 's.variation_id')->where('s.transaction_id', $transfer->id)
            ->where('p.business_id', $transfer->business_id)->get(['s.*', 'p.name', 'v.sub_sku']);
        $scoped = $filters;
        $scoped['location_ids'] = [(int) $transfer->location_id];
        $scoped['variation_ids'] = $lines->pluck('variation_id')->all();
        $rows = $lines->isEmpty() ? [] : $this->detect($scoped);
        if ($lastFailure) {
            $detail = $lastFailure['stock_error'] ?? [];
            $line = !empty($detail['source_line_id']) ? $lines->firstWhere('id', (int) $detail['source_line_id']) : null;
            $line = $line ?: (!empty($detail['variation_id']) ? $lines->firstWhere('variation_id', (int) $detail['variation_id']) : null);
            $line = $line ?: $lines->first();
            if ($line) {
                array_unshift($rows, [
                    'product_id' => (int) $line->product_id, 'variation_id' => (int) $line->variation_id,
                    'location_id' => (int) $transfer->location_id, 'location' => DB::table('business_locations')->where('id', $transfer->location_id)->value('name'),
                    'product' => $line->name, 'sku' => $line->sub_sku, 'lot' => $detail['serial'] ?? null,
                    'reference' => $transfer->ref_no.' · Line #'.$line->id,
                    'available_qty' => $detail['recorded_available'] ?? null, 'expected_qty' => $detail['history_available'] ?? null,
                    'difference' => isset($detail['recorded_available'], $detail['history_available']) ? $detail['history_available'] - $detail['recorded_available'] : null,
                    'problem' => 'Last stock action error: '.($detail['reason'] ?? $lastFailure['msg']), 'can_fix_quantity' => false,
                    'url' => url('/stock-transfers/'.$transfer->id.'/edit'),
                    'completion_url' => ($lastFailure['operation'] ?? 'completion') === 'completion' && in_array($transfer->status, ['pending', 'in_transit'], true)
                        ? action([\App\Http\Controllers\StockTransferController::class, 'updateStatus'], [$transfer->id]) : null,
                ]);
            }
        }
        return $rows;
    }

    public function detect(array $filters): array
    {
        $this->raiseLimits();
        $businessId = (int) $filters['business_id'];
        $locations = array_map('intval', $filters['location_ids']);
        if (!$locations) {
            return [];
        }
        $metadata = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)->where('p.enable_stock', 1)
            ->when(isset($filters['variation_ids']), fn ($q) => $q->whereIn('v.id', $filters['variation_ids']))
            ->get(['v.id', 'v.product_id', 'v.sub_sku', 'p.name'])->keyBy('id');
        $locationNames = DB::table('business_locations')->where('business_id', $businessId)
            ->whereIn('id', $locations)->pluck('name', 'id');
        $eligible = null;
        if (!empty($filters['purchase_ref']) || !empty($filters['purchase_date_from']) || !empty($filters['purchase_date_to'])) {
            $eligible = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('t.business_id', $businessId)->whereIn('t.location_id', $locations)->where('t.type', 'purchase')
                ->when(!empty($filters['purchase_ref']), fn ($q) => $q->where('t.ref_no', 'like', '%'.$filters['purchase_ref'].'%'))
                ->when(!empty($filters['purchase_date_from']), fn ($q) => $q->whereDate('t.transaction_date', '>=', $filters['purchase_date_from']))
                ->when(!empty($filters['purchase_date_to']), fn ($q) => $q->whereDate('t.transaction_date', '<=', $filters['purchase_date_to']))
                ->get(['p.variation_id', 't.location_id'])->mapWithKeys(fn ($p) => [$p->variation_id.':'.$p->location_id => true])->all();
        }
        $rows = [];
        $add = function (int $variation, int $location, array $detail) use (&$rows, $metadata, $locationNames, $eligible) {
            $product = $metadata->get($variation);
            if (!$product || !$locationNames->has($location) || ($eligible !== null && !isset($eligible[$variation.':'.$location]))) {
                return;
            }
            if (isset($detail['available_qty'], $detail['expected_qty'])) {
                $detail['difference'] = $detail['expected_qty'] - $detail['available_qty'];
            }
            $rows[] = array_merge([
                'product_id' => (int) $product->product_id, 'variation_id' => $variation, 'location_id' => $location,
                'product' => $product->name, 'sku' => $product->sub_sku, 'location' => $locationNames[$location],
                'lot' => null, 'allocated_lots' => null, 'reference' => null, 'available_qty' => null,
                'expected_qty' => null, 'quantity' => null, 'difference' => null, 'severity' => 'high',
                'url' => url('/reports/product-stock-details').'?'.http_build_query(['variation_id' => $variation, 'location_id' => $location]),
            ], $detail);
        };
        $historySql = app(TransactionUtil::class)->lotQuantityAvailableSql('p');
        $batches = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('t.location_id', $locations)->where('t.status', 'received')
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->select('p.id', 'p.variation_id', 'p.lot_number', 'p.quantity', 't.location_id', 't.ref_no', 't.id as transaction_id', 't.type')
            ->selectRaw($historySql.' as history_qty')
            ->selectRaw('(p.quantity - COALESCE(p.quantity_sold,0) - COALESCE(p.quantity_returned,0) - COALESCE(p.quantity_adjusted,0) - COALESCE(p.mfg_quantity_used,0)) as recorded_qty')->get();
        foreach ($batches as $batch) {
            $reasons = [];
            if ((float) $batch->history_qty < -0.0001 || (float) $batch->recorded_qty < -0.0001) {
                $reasons[] = 'Negative batch stock';
            }
            if (abs((float) $batch->history_qty - (float) $batch->recorded_qty) > 0.0001) {
                $reasons[] = 'Recorded batch balance differs from movement history';
            }
            if ($reasons) {
                $add((int) $batch->variation_id, (int) $batch->location_id, [
                    'lot' => ($batch->lot_number ?: 'No lot').' · Batch #'.$batch->id, 'reference' => $batch->ref_no,
                    'quantity' => (float) $batch->quantity, 'available_qty' => (float) $batch->recorded_qty,
                    'expected_qty' => (float) $batch->history_qty, 'difference' => (float) $batch->history_qty - (float) $batch->recorded_qty,
                    'problem' => implode('; ', $reasons),
                    'url' => $batch->lot_number ? ssi_route('ssi.lot.history', $batch->lot_number) : url('/reports/product-stock-details').'?'.http_build_query(['variation_id' => $batch->variation_id, 'location_id' => $batch->location_id]),
                ]);
            }
        }
        $quantityMismatches = [];
        // Use the same movement calculation and duplicate-row handling as Product Stock Details.
        foreach ($locations as $location) {
            $stockDetails = app(\App\Utils\ProductUtil::class)->getVariationStockMisMatch($businessId, null, $location);
            foreach ($stockDetails as $stock) {
                $actual = (float) $stock->stock;
                $expected = (float) $stock->total_stock_calculated;
                $mismatch = abs($actual - $expected) > 0.0001;
                $quantityMismatches[(int) $stock->variation_id.':'.$location] = $mismatch;
                if ($actual < -0.0001 || $mismatch) {
                    $add((int) $stock->variation_id, $location, [
                        'lot' => 'All lots at location', 'available_qty' => $actual, 'expected_qty' => $expected,
                        'can_fix_quantity' => $mismatch, 'difference' => $expected - $actual,
                        'problem' => implode('; ', array_filter([
                            $actual < -0.0001 ? 'Negative location stock' : null,
                            $mismatch ? 'Location quantity differs from Product Stock Details' : null,
                        ])),
                    ]);
                }
            }
        }
        $audit = app(LotStockAuditService::class)->audit($businessId);
        $lineIds = array_column($audit['sale_line_issues'], 'sell_line_id');
        $lineVariations = DB::table('transaction_sell_lines')->whereIn('id', $lineIds)->pluck('variation_id', 'id');
        foreach ($audit['sale_line_issues'] as $issue) {
            $add((int) $lineVariations[$issue['sell_line_id']], $issue['location_id'], [
                'lot' => ($issue['selected_lot'] ?: 'Missing lot').' · Batch #'.$issue['selected_purchase_line_id'],
                'allocated_lots' => collect($issue['allocations'])->map(fn ($p) => ($p->lot_number ?: 'Unallocated').' (#'.$p->purchase_line_id.', qty '.$p->quantity.')')->implode('; '),
                'reference' => ($issue['ref_no'] ?: $issue['invoice_no']).' · Line #'.$issue['sell_line_id'],
                'sell_line_id' => $issue['sell_line_id'],
                'authority_url' => $issue['type'] === 'sell_transfer'
                    ? ssi_route('ssi.authority.index', ['transfer_id' => $issue['transaction_id']]) : null,
                'deducted_serial_fix_url' => $issue['type'] === 'sell_transfer' && in_array('Stock allocation differs from the invoice selected lot.', $issue['reasons'], true)
                    ? action([\App\Http\Controllers\StockTransferController::class, 'fixDeductedSerial'], [$issue['transaction_id']]) : null,
                'quantity' => $issue['quantity'], 'available_qty' => collect($issue['allocations'])->sum('quantity'),
                'expected_qty' => $issue['quantity'], 'problem' => implode('; ', $issue['reasons']),
                'url' => url($issue['type'] === 'sell_transfer' ? '/stock-transfers/'.$issue['transaction_id'].'/edit?correct_line='.$issue['sell_line_id'] : '/sells/'.$issue['transaction_id']),
            ]);
        }
        $transactions = DB::table('transactions')->where('business_id', $businessId)->whereIn('id', array_column($audit['transfer_issues'], 'transaction_id'))->pluck('location_id', 'id');
        foreach ($audit['transfer_issues'] as $issue) {
            $add($issue['variation_id'], (int) $transactions[$issue['transaction_id']], [
                'lot' => $issue['selected_serial'], 'reference' => $issue['ref_no'], 'quantity' => $issue['source_quantity'],
                'available_qty' => $issue['matching_received_quantity'], 'expected_qty' => $issue['source_quantity'],
                'allocated_lots' => collect($issue['receiving_lines'])->map(fn ($p) => ($p->lot_number ?: 'No lot').' (qty '.$p->quantity.')')->implode('; '),
                'problem' => $issue['reason'], 'url' => url('/stock-transfers/'.$issue['transaction_id'].'/edit'),
            ]);
        }
        // Check FIFO / bulk lines too: they have no selected lot for the serial audit.
        $bulkLines = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('t.location_id', $locations)->where('t.status', 'final')
            ->whereIn('t.type', ['sell', 'sell_transfer'])->whereRaw('COALESCE(s.lot_no_line_id, 0) = 0')
            ->get(['s.id', 's.variation_id', 's.product_id', 's.quantity', 's.quantity_returned', 't.id as transaction_id',
                't.location_id', 't.transaction_date', 't.type', 't.ref_no', 't.invoice_no']);
        $mappings = collect();
        foreach ($bulkLines->pluck('id')->chunk(1000) as $ids) {
            $mappings = $mappings->concat(DB::table('transaction_sell_lines_purchase_lines as m')
                ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
                ->leftJoin('transactions as t', 't.id', '=', 'p.transaction_id')->whereIn('m.sell_line_id', $ids)
                ->get(['m.sell_line_id', 'm.purchase_line_id', 'm.quantity', 'm.qty_returned', 'p.lot_number',
                    'p.variation_id', 'p.product_id', 't.business_id', 't.location_id', 't.status', 't.transaction_date']));
        }
        $mappedByLine = $mappings->groupBy('sell_line_id');
        $overselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        foreach ($bulkLines as $line) {
            $mapped = $mappedByLine->get($line->id, collect());
            $reasons = [];
            if (abs((float) $line->quantity - (float) $mapped->sum('quantity')) > 0.0001) {
                $reasons[] = 'Allocated quantity differs from movement quantity';
            }
            if (abs((float) $line->quantity_returned - (float) $mapped->sum('qty_returned')) > 0.0001) {
                $reasons[] = 'Allocated return quantity differs from movement returns';
            }
            foreach ($mapped as $mapping) {
                if ((int) $mapping->purchase_line_id === 0 && $overselling) {
                    continue;
                }
                if ((int) $mapping->business_id !== $businessId || (int) $mapping->location_id !== (int) $line->location_id
                    || (int) $mapping->product_id !== (int) $line->product_id || (int) $mapping->variation_id !== (int) $line->variation_id
                    || $mapping->status !== 'received') {
                    $reasons[] = 'Allocation receipt is missing or has the wrong business, location, product or status';
                } elseif ($line->type === 'sell_transfer' && $mapping->transaction_date > $line->transaction_date) {
                    $reasons[] = 'Stock allocation uses a receipt dated after the transfer';
                }
                if ((float) $mapping->quantity < 0 || (float) $mapping->qty_returned < 0 || (float) $mapping->qty_returned > (float) $mapping->quantity) {
                    $reasons[] = 'Allocation quantity or return quantity is invalid';
                }
            }
            if ($reasons) {
                $add((int) $line->variation_id, (int) $line->location_id, [
                    'lot' => 'Automatic / FIFO', 'reference' => ($line->ref_no ?: $line->invoice_no).' · Line #'.$line->id,
                    'allocated_lots' => $mapped->filter(fn ($p) => (int) $p->business_id === $businessId)
                        ->map(fn ($p) => ($p->lot_number ?: 'No lot').' (#'.$p->purchase_line_id.', qty '.$p->quantity.')')->implode('; '),
                    'quantity' => (float) $line->quantity, 'available_qty' => (float) $mapped->sum('quantity'),
                    'expected_qty' => (float) $line->quantity, 'problem' => implode('; ', array_unique($reasons)),
                    'url' => url($line->type === 'sell_transfer' ? '/stock-transfers/'.$line->transaction_id.'/edit' : '/sells/'.$line->transaction_id),
                ]);
            }
        }
        foreach ($rows as &$row) {
            $row['can_fix_quantity'] = $quantityMismatches[$row['variation_id'].':'.$row['location_id']] ?? false;
        }
        unset($row);
        return $rows;
    }
}

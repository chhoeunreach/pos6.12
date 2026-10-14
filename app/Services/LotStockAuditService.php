<?php

namespace App\Services;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

class LotStockAuditService
{
    public function audit(int $businessId, ?string $invoice = null): array
    {
        $allocationTotals = DB::table('transaction_sell_lines_purchase_lines as m')
            ->select('m.sell_line_id')
            ->selectRaw('SUM(m.quantity) as allocated_quantity, SUM(COALESCE(m.qty_returned, 0)) as returned_quantity')
            ->groupBy('m.sell_line_id');
        $linesQuery = DB::table('transaction_sell_lines as s')
            ->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->leftJoin('purchase_lines as selected', 's.lot_no_line_id', '=', 'selected.id')
            ->leftJoin('transactions as source', 'selected.transaction_id', '=', 'source.id')
            ->leftJoinSub($allocationTotals, 'totals', 'totals.sell_line_id', '=', 's.id')
            ->where('t.business_id', $businessId)
            ->whereIn('t.type', ['sell', 'sell_transfer'])
            ->where('t.status', 'final')
            ->where('s.lot_no_line_id', '>', 0);
        if ($invoice !== null) {
            $linesQuery->where('t.invoice_no', $invoice);
        }
        $lines = $linesQuery->get([
            's.id as sell_line_id', 't.id as transaction_id', 't.invoice_no', 't.ref_no', 't.type',
            't.location_id', 's.product_id', 's.variation_id', 's.quantity', 's.quantity_returned',
            's.lot_no_line_id', 'selected.lot_number', 'selected.id as selected_id',
            'selected.product_id as selected_product_id', 'selected.variation_id as selected_variation_id',
            'source.business_id as source_business_id', 'source.location_id as source_location_id',
            'source.status as source_status', 'totals.allocated_quantity', 'totals.returned_quantity',
        ]);
        $allocations = collect();
        foreach ($lines->pluck('sell_line_id')->chunk(1000) as $ids) {
            $allocations = $allocations->concat(DB::table('transaction_sell_lines_purchase_lines as m')
                ->leftJoin('purchase_lines as p', 'm.purchase_line_id', '=', 'p.id')
                ->whereIn('m.sell_line_id', $ids)
                ->get(['m.id', 'm.sell_line_id', 'm.purchase_line_id', 'm.quantity', 'm.qty_returned', 'p.lot_number']));
        }
        $byLine = $allocations->groupBy('sell_line_id');
        $issues = [];
        foreach ($lines as $line) {
            $reasons = [];
            if ($line->selected_id === null) {
                $reasons[] = 'Selected purchase lot is missing.';
            } elseif ((int) $line->source_business_id !== $businessId
                || (int) $line->source_location_id !== (int) $line->location_id
                || (int) $line->selected_product_id !== (int) $line->product_id
                || (int) $line->selected_variation_id !== (int) $line->variation_id
                || $line->source_status !== 'received') {
                $reasons[] = 'Selected lot does not match the business, location, product or received status.';
            }
            $mapped = $byLine->get($line->sell_line_id, collect());
            if ($mapped->contains(fn ($row) => (int) $row->purchase_line_id !== (int) $line->lot_no_line_id)) {
                $reasons[] = 'Stock allocation differs from the invoice selected lot.';
            }
            if (abs((float) $line->allocated_quantity - (float) $line->quantity) > 0.0001) {
                $reasons[] = 'Allocated quantity differs from the quantity sold.';
            }
            if (abs((float) $line->returned_quantity - (float) $line->quantity_returned) > 0.0001) {
                $reasons[] = 'Allocated return quantity differs from the invoice return quantity.';
            }
            if ($reasons) {
                $issues[] = [
                    'transaction_id' => (int) $line->transaction_id,
                    'invoice_no' => $line->invoice_no,
                    'ref_no' => $line->ref_no,
                    'type' => $line->type,
                    'sell_line_id' => (int) $line->sell_line_id,
                    'location_id' => (int) $line->location_id,
                    'selected_purchase_line_id' => (int) $line->lot_no_line_id,
                    'selected_lot' => $line->lot_number,
                    'quantity' => (float) $line->quantity,
                    'quantity_returned' => (float) $line->quantity_returned,
                    'reasons' => $reasons,
                    'allocations' => $mapped->values()->all(),
                ];
            }
        }

        $availableSql = app(TransactionUtil::class)->lotQuantityAvailableSql('p');
        $stockQuery = DB::table('purchase_lines as p')
            ->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)
            ->where('t.status', 'received')
            ->whereNotNull('p.lot_number')
            ->where('p.lot_number', '!=', '');
        if ($invoice !== null) {
            $stockQuery->whereIn('p.id', $lines->pluck('lot_no_line_id')->merge($allocations->pluck('purchase_line_id'))->unique());
        }
        $stocks = $stockQuery->select('p.id', 'p.lot_number', 't.location_id', 'p.quantity_sold')
            ->selectRaw($availableSql.' as expected_available')
            ->selectRaw('(p.quantity - p.quantity_sold - p.quantity_returned - p.quantity_adjusted - p.mfg_quantity_used) as cached_available')
            ->get()
            ->filter(fn ($row) => (float) $row->expected_available < -0.0001
                || abs((float) $row->expected_available - (float) $row->cached_available) > 0.0001)
            ->values();

        return [
            'business_id' => $businessId,
            'invoice_filter' => $invoice,
            'generated_at' => now()->toIso8601String(),
            'checked_selected_sale_lines' => $lines->count(),
            'sale_line_issues' => $issues,
            'stock_issues' => $stocks->all(),
            'reconciliation_note' => 'Verify the physical serial and original transfer or invoice before repairing shortages. No records were changed.',
        ];
    }
}

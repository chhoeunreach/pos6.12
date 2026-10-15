<?php

namespace App\Services;

use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

class LotStockAuditService
{
    public function audit(int $businessId, ?string $invoice = null, ?int $transactionId = null, ?array $transactionIds = null): array
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
        if ($transactionId !== null) {
            $linesQuery->where('t.id', $transactionId);
        }
        if ($transactionIds !== null) {
            $linesQuery->whereIn('t.id', $transactionIds);
        }
        $lines = $linesQuery->get([
            's.id as sell_line_id', 't.id as transaction_id', 't.invoice_no', 't.ref_no', 't.type',
            't.location_id', 's.product_id', 's.variation_id', 's.quantity', 's.quantity_returned',
            's.lot_no_line_id', 'selected.lot_number', 'selected.id as selected_id',
            'selected.product_id as selected_product_id', 'selected.variation_id as selected_variation_id',
            'source.business_id as source_business_id', 'source.location_id as source_location_id',
            'source.status as source_status', 'source.transaction_date as receipt_date', 't.transaction_date',
            'totals.allocated_quantity', 'totals.returned_quantity',
        ]);
        $allocations = collect();
        foreach ($lines->pluck('sell_line_id')->chunk(1000) as $ids) {
            $allocations = $allocations->concat(DB::table('transaction_sell_lines_purchase_lines as m')
                ->leftJoin('purchase_lines as p', 'm.purchase_line_id', '=', 'p.id')
                ->leftJoin('transactions as receipt', 'p.transaction_id', '=', 'receipt.id')
                ->whereIn('m.sell_line_id', $ids)
                ->get(['m.id', 'm.sell_line_id', 'm.purchase_line_id', 'm.quantity', 'm.qty_returned', 'p.lot_number',
                    'receipt.transaction_date as receipt_date', 'receipt.location_id as receipt_location_id']));
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
            if ($line->type === 'sell_transfer' && $line->receipt_date
                && \Carbon::parse($line->transaction_date)->lt(\Carbon::parse($line->receipt_date))) {
                $reasons[] = 'Transfer is dated before the selected lot arrived at its source location.';
            }
            if ($mapped->contains(fn ($row) => (int) $row->purchase_line_id !== (int) $line->lot_no_line_id)) {
                $reasons[] = 'Stock allocation differs from the invoice selected lot.';
            }
            if ($line->type === 'sell_transfer' && $mapped->contains(fn ($row) => $row->receipt_date
                && \Carbon::parse($line->transaction_date)->lt(\Carbon::parse($row->receipt_date)))) {
                $reasons[] = 'Stock allocation uses a receipt dated after the transfer.';
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
                    'transaction_date' => $line->transaction_date,
                    'receipt_date' => $line->receipt_date,
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
        if ($invoice !== null || $transactionId !== null || $transactionIds !== null) {
            $stockQuery->whereIn('p.id', $lines->pluck('lot_no_line_id')->merge($allocations->pluck('purchase_line_id'))->unique());
        }
        $stocks = $stockQuery->select('p.id', 'p.lot_number', 't.location_id', 'p.quantity_sold')
            ->selectRaw($availableSql.' as expected_available')
            ->selectRaw('(p.quantity - p.quantity_sold - p.quantity_returned - p.quantity_adjusted - p.mfg_quantity_used) as cached_available')
            ->get()
            ->filter(fn ($row) => (float) $row->expected_available < -0.0001
                || abs((float) $row->expected_available - (float) $row->cached_available) > 0.0001)
            ->values();

        $transferIssues = $invoice === null ? $this->auditTransferReceipts($businessId, $transactionId, $transactionIds) : [];

        return [
            'business_id' => $businessId,
            'invoice_filter' => $invoice,
            'transaction_filter' => $transactionId,
            'generated_at' => now()->toIso8601String(),
            'checked_selected_sale_lines' => $lines->count(),
            'sale_line_issues' => $issues,
            'stock_issues' => $stocks->all(),
            'transfer_issues' => $transferIssues,
            'reconciliation_note' => 'Verify the physical serial and original transfer or invoice before repairing shortages. No records were changed.',
        ];
    }

    private function auditTransferReceipts(int $businessId, ?int $transactionId = null, ?array $transactionIds = null): array
    {
        $sources = DB::table('transaction_sell_lines as s')
            ->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->leftJoin('purchase_lines as p', 's.lot_no_line_id', '=', 'p.id')
            ->where('t.business_id', $businessId)->where('t.type', 'sell_transfer')->where('t.status', 'final')
            ->when($transactionId !== null, fn ($query) => $query->where('t.id', $transactionId))
            ->when($transactionIds !== null, fn ($query) => $query->whereIn('t.id', $transactionIds))
            ->where('s.lot_no_line_id', '>', 0)
            ->get(['t.id', 't.ref_no', 's.product_id', 's.variation_id', 's.quantity', 'p.lot_number']);
        $automaticSources = DB::table('transaction_sell_lines as s')
            ->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->join('transaction_sell_lines_purchase_lines as m', 'm.sell_line_id', '=', 's.id')
            ->join('purchase_lines as p', 'm.purchase_line_id', '=', 'p.id')
            ->where('t.business_id', $businessId)->where('t.type', 'sell_transfer')->where('t.status', 'final')
            ->when($transactionId !== null, fn ($query) => $query->where('t.id', $transactionId))
            ->when($transactionIds !== null, fn ($query) => $query->whereIn('t.id', $transactionIds))
            ->where(function ($query) {
                $query->whereNull('s.lot_no_line_id')->orWhere('s.lot_no_line_id', 0);
            })
            ->get(['t.id', 't.ref_no', 's.product_id', 's.variation_id', 'm.quantity', 'p.lot_number']);
        $sources = $sources->concat($automaticSources)->groupBy('id');
        $receipts = DB::table('purchase_lines as p')
            ->join('transactions as t', 'p.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->where('t.type', 'purchase_transfer')->where('t.status', 'received')
            ->when($transactionId !== null, fn ($query) => $query->where('t.transfer_parent_id', $transactionId))
            ->when($transactionIds !== null, fn ($query) => $query->whereIn('t.transfer_parent_id', $transactionIds))
            ->get(['t.id', 't.transfer_parent_id', 'p.product_id', 'p.variation_id', 'p.quantity', 'p.lot_number'])
            ->groupBy('transfer_parent_id');
        $issues = [];
        foreach ($sources as $id => $lines) {
            $received = $receipts->get($id, collect());
            foreach ($lines->groupBy(fn ($line) => json_encode([$line->product_id, $line->variation_id, (string) $line->lot_number])) as $serialLines) {
                $line = $serialLines->first();
                $quantity = (float) $serialLines->sum('quantity');
                $matching = $received->filter(fn ($row) => (int) $row->product_id === (int) $line->product_id
                    && (int) $row->variation_id === (int) $line->variation_id && (string) $row->lot_number === (string) $line->lot_number);
                if (abs($quantity - (float) $matching->sum('quantity')) > 0.0001) {
                    $issues[] = [
                        'transaction_id' => (int) $id, 'ref_no' => $line->ref_no, 'selected_serial' => $line->lot_number,
                        'product_id' => (int) $line->product_id, 'variation_id' => (int) $line->variation_id,
                        'source_quantity' => $quantity, 'matching_received_quantity' => (float) $matching->sum('quantity'),
                        'receiving_lines' => $received->filter(fn ($row) => (int) $row->variation_id === (int) $line->variation_id)->values()->all(),
                        'reason' => 'Selected source serial and received serial quantities differ.',
                    ];
                }
            }
        }

        return $issues;
    }
}

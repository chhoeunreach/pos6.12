<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Support\Facades\DB;

class StockSellPurchaseCostResolver
{
    public function resolve(object $line, object $sale, array $allowedLocations): array
    {
        $receipts = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $sale->business_id)->whereIn('t.location_id', $allowedLocations)
            ->where('t.status', 'received')->whereIn('t.type', ['purchase', 'opening_stock', 'production_purchase'])
            ->where('p.variation_id', $line->variation_id)->select('p.*');
        $selected = !empty($line->lot_no_line_id) ? (clone $receipts)->where('p.id', $line->lot_no_line_id)->first() : null;
        $rawSelected = !empty($line->lot_no_line_id) ? DB::table('purchase_lines')->where('id', $line->lot_no_line_id)->where('variation_id', $line->variation_id)->first() : null;
        if (!$selected && $rawSelected) return [];
        if ($selected && $selected->purchase_price_inc_tax !== null) return [(int) $selected->id];
        if ($selected && trim((string) $selected->lot_number) !== '') {
            $matched = (clone $receipts)->where('t.location_id', $sale->location_id)->where('p.lot_number', $selected->lot_number)
                ->whereNotNull('p.purchase_price_inc_tax')->orderByDesc('t.transaction_date')->orderByDesc('p.id')->first();
            if ($matched) return [(int) $matched->id];
        }
        if (!$selected) {
            $mapped = DB::table('transaction_sell_lines_purchase_lines as a')
                ->join('purchase_lines as p', 'p.id', '=', 'a.purchase_line_id')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                ->where('a.sell_line_id', $line->id)->where('a.quantity', '>', 0)->where('p.variation_id', $line->variation_id)
                ->where('t.business_id', $sale->business_id)
                ->get(['p.id', 'p.purchase_price_inc_tax', 'a.quantity', 't.location_id', 't.status', 't.type']);
            if (abs($mapped->sum('quantity') - $line->quantity) < .00001 && !$mapped->contains(fn ($row) => $row->purchase_price_inc_tax === null)) {
                if ($mapped->contains(fn ($row) => !in_array((int) $row->location_id, $allowedLocations, true) || $row->status !== 'received' || !in_array($row->type, ['purchase', 'opening_stock', 'production_purchase'], true))) return [];
                $ids = $mapped->pluck('id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
                if ($ids) return $ids;
            }
        }
        $latest = (clone $receipts)->where('t.location_id', $sale->location_id)->whereNotNull('p.purchase_price_inc_tax')
            ->orderByDesc('t.transaction_date')->orderByDesc('p.id')->first();

        return $latest ? [(int) $latest->id] : [];
    }
}

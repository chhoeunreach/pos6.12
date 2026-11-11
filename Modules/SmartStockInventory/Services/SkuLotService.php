<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\SmartStockInventory\Models\SmartStockActionLog;

class SkuLotService
{
    public function attach($rows, int $businessId, int $variationId, array $locationIds)
    {
        $ids = collect($rows)->pluck('transaction_id')->unique()->all();
        if (empty($ids)) {
            return collect($rows);
        }
        $select = function ($query) use ($businessId, $variationId, $locationIds, $ids) {
            return $query->join('transactions as receipt', 'receipt.id', '=', 'pl.transaction_id')
                ->where('t.business_id', $businessId)->where('receipt.business_id', $businessId)
                ->whereIn('t.location_id', $locationIds)->whereIn('receipt.location_id', $locationIds)
                ->whereIn('t.id', $ids)->where('pl.variation_id', $variationId)
                ->select('t.id as transaction_id', 'pl.id', 'pl.lot_number', 'receipt.location_id', 'receipt.status', 'receipt.type');
        };
        $purchases = $select(DB::table('transactions as t')->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.id'))->get();

        // Explicit lot selections take precedence over FIFO allocations, as in Lot Management.
        $sales = DB::table('transactions as t')->join('transaction_sell_lines as sl', 'sl.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines as parent', 'parent.id', '=', 'sl.parent_sell_line_id')
            ->leftJoin('transaction_sell_lines_purchase_lines as m', function ($join) {
                $join->on('m.sell_line_id', '=', DB::raw('COALESCE(parent.id, sl.id)'))
                    ->whereRaw('COALESCE(sl.lot_no_line_id, parent.lot_no_line_id, 0) = 0');
            })
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('COALESCE(NULLIF(sl.lot_no_line_id, 0), NULLIF(parent.lot_no_line_id, 0), m.purchase_line_id)'))
            ->where('sl.variation_id', $variationId);
        $sales = $select($sales)->get();

        $adjustments = DB::table('transactions as t')->join('stock_adjustment_lines as al', 'al.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines_purchase_lines as m', function ($join) {
                $join->on('m.stock_adjustment_line_id', '=', 'al.id')->whereRaw('COALESCE(al.lot_no_line_id, 0) = 0');
            })
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('COALESCE(NULLIF(al.lot_no_line_id, 0), m.purchase_line_id)'))
            ->where('al.variation_id', $variationId);
        $adjustments = $select($adjustments)->get();

        $purchaseReturns = DB::table('transactions as t')->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.return_parent_id')
            ->where('t.type', 'purchase_return')->where('pl.quantity_returned', '>', 0);
        $purchaseReturns = $select($purchaseReturns)->get();

        $legacyReturns = DB::table('transactions as t')->join('transaction_sell_lines as sl', 'sl.transaction_id', '=', 't.return_parent_id')
            ->leftJoin('transaction_sell_lines_purchase_lines as m', function ($join) {
                $join->on('m.sell_line_id', '=', 'sl.id')->whereRaw('COALESCE(sl.lot_no_line_id, 0) = 0');
            })
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('COALESCE(NULLIF(sl.lot_no_line_id, 0), m.purchase_line_id)'))
            ->where('t.type', 'sell_return')->where('sl.variation_id', $variationId)->where('sl.quantity_returned', '>', 0)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('transaction_sell_lines as own')->whereColumn('own.transaction_id', 't.id')->whereColumn('own.variation_id', 'sl.variation_id');
            });
        $legacyReturns = $select($legacyReturns)->get();

        $lots = $purchases->concat($sales)->concat($adjustments)->concat($purchaseReturns)->concat($legacyReturns)
            ->groupBy('transaction_id')->map(fn ($items) => $items->unique('id')->values()->map(fn ($lot) => [
                'id' => $lot->id, 'lot_number' => $lot->lot_number,
                'editable' => $lot->status === 'received' && in_array($lot->type, ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'], true),
            ])->all());

        return collect($rows)->map(fn ($row) => array_merge($row, ['lots' => $lots->get($row['transaction_id'], [])]));
    }

    public function update(int $businessId, int $variationId, int $lineId, array $locationIds, array $data, $user, string $ip): void
    {
        DB::transaction(function () use ($businessId, $variationId, $lineId, $locationIds, $data, $user, $ip) {
            $line = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                ->join('products as p', 'p.id', '=', 'pl.product_id')
                ->where('t.business_id', $businessId)->where('p.business_id', $businessId)
                ->whereIn('t.location_id', $locationIds)->where('t.status', 'received')
                ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
                ->where('pl.variation_id', $variationId)->where('pl.id', $lineId)
                ->select('pl.id', 'pl.lot_number', 't.location_id')->lockForUpdate()->first();
            abort_unless($line, 404);
            if ((string) $line->lot_number !== (string) ($data['original_lot_number'] ?? '')) {
                throw ValidationException::withMessages(['lot_number' => 'This lot was updated by another user. Refresh the history before editing it.']);
            }
            $lot = trim($data['lot_number']);
            if ($lot === '') {
                throw ValidationException::withMessages(['lot_number' => 'Enter a lot number.']);
            }
            if ($lot === (string) $line->lot_number) {
                return;
            }
            DB::table('purchase_lines')->where('id', $lineId)->update(['lot_number' => $lot, 'updated_at' => now()]);
            SmartStockActionLog::create([
                'user_id' => $user->id, 'user_name' => $user->username,
                'business_id' => $businessId, 'location_id' => $line->location_id,
                'module_name' => 'SmartStockInventory', 'table_name' => 'purchase_lines', 'record_id' => $lineId,
                'action_type' => 'update_sku_lot', 'reference_type' => 'purchase_lines', 'reference_id' => $lineId,
                'old_data' => json_encode(['lot_number' => $line->lot_number]), 'new_data' => json_encode(['lot_number' => $lot]),
                'reason' => $data['reason'], 'ip_address' => $ip,
            ]);
        });
    }
}

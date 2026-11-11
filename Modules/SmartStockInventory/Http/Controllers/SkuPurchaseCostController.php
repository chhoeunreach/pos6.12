<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Http\Request;
use Modules\SmartStockInventory\Support\SkuPermissions;
use Illuminate\Support\Facades\DB;
use Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseCostController;

class SkuPurchaseCostController extends FinancialPurchaseCostController
{
    protected function editableReceiptTypes(): array
    {
        return ['purchase', 'opening_stock', 'production_purchase', 'purchase_transfer'];
    }

    protected function scope(Request $request, string $source, int $line): array
    {
        SkuPermissions::authorize($request->user(), 'update_cost');
        abort_unless($source === 'main', 403);
        $business = (int) $request->session()->get('user.business_id');
        $allowed = SkuPermissions::locationIds($request->user(), $business);
        $lot = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->where('t.business_id', $business)->where('p.business_id', $business)->whereIn('t.location_id', $allowed)
            ->where('t.status', 'received')->whereIn('t.type', $this->editableReceiptTypes())
            ->where('pl.id', $line)->exists();
        abort_unless($lot, 404);

        return [$business, config('database.default'), $allowed, [$line]];
    }
}

<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseCostController;
use Modules\SmartStockInventory\Services\StockSellPurchaseCostResolver;

class StockPurchaseCostController extends FinancialPurchaseCostController
{
    protected function scope(Request $request, string $source, int $line): array
    {
        abort_unless($source === 'main' && $request->user()->can('purchase.update'), 403);
        $user = $request->user();
        abort_unless($user->hasAnyPermission(['stock_report.view', 'purchase_n_sell_report.view', 'sell.view', 'sell.create', 'direct_sell.access', 'view_own_sell_only']), 403);
        $business = (int) $request->session()->get('user.business_id');
        $allowed = $user->permitted_locations();
        if ($allowed === 'all') $allowed = DB::table('business_locations')->where('business_id', $business)->pluck('id')->all();
        $allowed = array_map('intval', $allowed);
        $query = DB::table('transactions')->where('business_id', $business)->whereIn('location_id', $allowed)
            ->where('type', 'sell')->where('status', 'final')->where(function ($query) { $query->whereNull('sub_type')->orWhere('sub_type', '!=', 'project_invoice'); })->whereExists(function ($query) use ($line) {
                $query->selectRaw('1')->from('transaction_sell_lines')->whereColumn('transaction_id', 'transactions.id')->where('id', $line)->whereNull('parent_sell_line_id');
            });
        if (!$user->can('direct_sell.view') && $user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping', 'view_commission_agent_sell', 'access_commission_agent_shipping'])) {
            $query->where(function ($query) use ($user) {
                if ($user->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) $query->where('created_by', $user->id);
                if ($user->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) $query->orWhere('commission_agent', $user->id);
            });
        }
        $sale = $query->first();
        abort_unless($sale, 404);
        $sellLine = DB::table('transaction_sell_lines')->where('id', $line)->where('transaction_id', $sale->id)->first();
        $ids = (new StockSellPurchaseCostResolver)->resolve($sellLine, $sale, $allowed);
        abort_unless($ids, 422, 'An editable purchase lot could not be found. Open the original purchase to correct its price.');

        return [$business, config('database.default'), $allowed, $ids];
    }
}

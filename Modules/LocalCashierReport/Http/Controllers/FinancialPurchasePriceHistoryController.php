<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class FinancialPurchasePriceHistoryController extends Controller
{
    public function show(Request $request, string $sourceName, int $variation)
    {
        $user = $request->user();
        abort_unless($user->can('stock_inventory.view') || $user->can('purchase.view') || $user->can('purchase.update'), 403);
        $business = (int) $request->session()->get('user.business_id');
        abort_unless($business > 0, 403);
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        abort_unless(isset($connections[$sourceName]), 404);
        $request->validate(['page' => 'nullable|integer|min:1', 'report_lang' => 'nullable|in:km,en',
            'sort' => 'nullable|in:date,reference,location,lot,quantity,purchase_price', 'direction' => 'nullable|in:asc,desc']);
        $sort = $request->input('sort') ?: 'date';
        $direction = $request->input('direction') ?: 'desc';
        $sortColumns = ['date' => 't.transaction_date', 'reference' => 't.ref_no', 'location' => 'location.name',
            'lot' => 'p.lot_number', 'quantity' => 'p.quantity', 'purchase_price' => 'p.purchase_price_inc_tax'];
        $db = DB::connection($connections[$sourceName]);
        $product = $db->table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('v.id', $variation)->where('p.business_id', $business)
            ->first(['p.name', 'v.sub_sku as sku']);
        abort_unless($product, 404);
        $allowed = $user->permitted_locations($business);
        $query = $db->table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->leftJoin('business_locations as location', function ($join) {
                $join->on('location.id', '=', 't.location_id')->on('location.business_id', '=', 't.business_id');
            })
            ->where('p.variation_id', $variation)->where('t.business_id', $business)
            ->where('t.type', 'purchase')->where('t.status', 'received');
        if ($allowed !== 'all') $query->whereIn('t.location_id', (array) $allowed);
        if ($sort === 'purchase_price') $query->orderByRaw('p.purchase_price_inc_tax IS NULL');
        $rows = $query->orderBy($sortColumns[$sort], $direction)->orderByDesc('t.id')->orderByDesc('p.id')
            ->paginate(50, ['p.id', 't.id as purchase_id', 't.transaction_date', 't.ref_no', 'p.lot_number', 'p.quantity', 'p.purchase_price_inc_tax', 'location.name as location'])
            ->withQueryString();

        return view('localcashierreport::purchase_price_history', compact('product', 'rows', 'sourceName', 'sort', 'direction'));
    }
}

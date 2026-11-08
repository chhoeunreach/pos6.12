<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialLotHistoryController extends Controller
{
    public function __construct(private FinancialDashboardController $dashboard)
    {
    }

    public function show(Request $request, string $sourceName, int $variation, string $lot)
    {
        [$business, $locations, $filters] = $this->dashboard->context($request);
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $connections = ['main' => config('database.default'), 'accessory' => config('accessory.database_connection', 'accessory'), 'service' => config('service.database_connection', 'service')];
        abort_unless(isset($connections[$sourceName]) && in_array($filters['source'], ['all', $sourceName], true), 404);
        $allowed = $locations->pluck('id')->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $sourceName === 'main' ? $id > 0 : ($id < -1 && (-$id % 2) === ($sourceName === 'service' ? 1 : 0)))
            ->map(fn ($id) => $sourceName === 'main' ? $id : intdiv(-$id, 2))->values()->all();
        $connection = $connections[$sourceName];
        $db = DB::connection($connection);
        $product = $db->table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('v.id', $variation)->where('p.business_id', $business)->first(['p.name', 'v.sub_sku as sku']);
        abort_unless($product, 404);
        $scope = fn ($query) => $query->where('t.business_id', $business)->whereIn('t.location_id', $allowed)
            ->where('pl.variation_id', $variation)->where('pl.lot_number', $lot);
        $receipts = $scope($db->table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id'))
            ->whereIn('t.type', ['purchase', 'opening_stock', 'production_purchase', 'purchase_transfer'])->where('t.status', 'received')
            ->select('t.id', 't.transaction_date', 't.type', 't.ref_no as reference', 't.location_id')
            ->selectRaw('pl.quantity as qty_in, 0 as qty_out, pl.purchase_price_inc_tax as unit_price')->get();
        $sales = $scope($db->table('transaction_sell_lines as line')->join('transactions as t', 't.id', '=', 'line.transaction_id')
            ->join('transaction_sell_lines as sold', 'sold.id', '=', DB::raw("CASE WHEN t.type = 'sell_return' THEN COALESCE(line.parent_sell_line_id, line.id) ELSE line.id END"))
            ->leftJoin('transaction_sell_lines_purchase_lines as a', function ($join) { $join->on('a.sell_line_id', '=', 'sold.id')->where('a.quantity', '>', 0); })
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('CASE WHEN COALESCE(sold.lot_no_line_id, 0) > 0 THEN sold.lot_no_line_id ELSE a.purchase_line_id END'))
            ->join('transactions as origin', 'origin.id', '=', 'pl.transaction_id')->where('origin.business_id', $business))
            ->whereColumn('pl.variation_id', 'sold.variation_id')->where('t.status', 'final')->whereIn('t.type', ['sell', 'sell_return', 'sell_transfer']);
        $quantity = "CASE WHEN t.type = 'sell_return' AND line.parent_sell_line_id IS NULL THEN line.quantity_returned ELSE line.quantity END";
        $quantity = "CASE WHEN COALESCE(sold.lot_no_line_id, 0) > 0 THEN ($quantity) ELSE ($quantity) * a.quantity / NULLIF(sold.quantity, 0) END";
        $sales = $sales->select('t.id', 'line.id as line_id', 'pl.id as purchase_line_id', 't.transaction_date', 't.type', 't.location_id')
            ->selectRaw("COALESCE(NULLIF(t.invoice_no, ''), t.ref_no) as reference, CASE WHEN t.type = 'sell_return' THEN ($quantity) ELSE 0 END as qty_in,
                CASE WHEN t.type = 'sell_return' THEN 0 ELSE ($quantity) END as qty_out, line.unit_price_inc_tax as unit_price")
            ->distinct()->get();
        $movements = $receipts->concat($sales);
        if (Schema::connection($connection)->hasTable('stock_adjustment_lines')) {
            $adjustments = $scope($db->table('transaction_sell_lines_purchase_lines as a')->join('purchase_lines as pl', 'pl.id', '=', 'a.purchase_line_id')
                ->join('stock_adjustment_lines as line', 'line.id', '=', 'a.stock_adjustment_line_id')->join('transactions as t', 't.id', '=', 'line.transaction_id'))
                ->where('t.type', 'stock_adjustment')->select('t.id', 't.transaction_date', 't.type', 't.ref_no as reference', 't.location_id')
                ->selectRaw('0 as qty_in, a.quantity as qty_out, line.unit_price as unit_price')->get();
            $movements = $movements->concat($adjustments);
        }
        abort_if($movements->isEmpty(), 404);
        $movements = $movements->sortBy(fn ($row) => $row->transaction_date.' '.str_pad($row->id, 12, '0', STR_PAD_LEFT))->values();
        $names = $db->table('business_locations')->whereIn('id', $allowed)->pluck('name', 'id');

        return view('localcashierreport::lot_history', compact('lot', 'product', 'sourceName', 'movements', 'names'));
    }
}

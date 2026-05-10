<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();
        [$start, $end] = $this->defaultDateRange($request->all());
        $locationIds = array_map('intval', (array) $request->input('location_ids', $this->permittedLocationIds($businessId)));
        if ($request->filled('location_id')) {
            $locationIds = [(int) $request->input('location_id')];
        }
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');

        $totalProductsQuery = DB::table('products')->where('business_id', $businessId)
            ->when(! empty($categoryId), fn ($q) => $q->where('category_id', $categoryId))
            ->when(! empty($brandId), fn ($q) => $q->where('brand_id', $brandId));
        $totalProducts = $totalProductsQuery->count();
        $totalStockQty = (float) DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->whereIn('vld.location_id', $locationIds)
            ->sum('vld.qty_available');

        $lowStockProducts = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->whereIn('vld.location_id', $locationIds)
            ->whereRaw('vld.qty_available <= COALESCE(v.default_purchase_price, 0) * 0 + 5')
            ->count();

        $negativeStockProducts = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->whereIn('vld.location_id', $locationIds)
            ->where('vld.qty_available', '<', 0)
            ->count();

        $mismatchProducts = DB::table('smart_stock_mismatch_logs')
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $duplicateImei = DB::table('smart_imei_histories')->where('business_id', $businessId)->groupBy('imei')->havingRaw('COUNT(*) > 1')->get()->count();
        $duplicateLot = DB::table('smart_lot_histories')->where('business_id', $businessId)->groupBy('lot_number')->havingRaw('COUNT(*) > 1')->get()->count();
        $inventorySessionsToday = DB::table('smart_stock_inventory_sessions')->where('business_id', $businessId)->whereDate('created_at', now()->toDateString())->count();

        $pendingTransfers = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('type', 'sell_transfer')
            ->where('status', '!=', 'final')
            ->count();

        $totalStockValue = (float) DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $businessId)
            ->whereIn('vld.location_id', $locationIds)
            ->sum(DB::raw('vld.qty_available * COALESCE(v.default_purchase_price, 0)'));

        $totalLots = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)
            ->whereIn('t.location_id', $locationIds)
            ->whereNotNull('pl.lot_number')
            ->where('pl.lot_number', '!=', '')
            ->distinct('pl.lot_number')
            ->count('pl.lot_number');

        return view('smartstockinventory::dashboard.index', compact(
            'totalProducts', 'totalStockQty', 'lowStockProducts', 'negativeStockProducts',
            'mismatchProducts', 'pendingTransfers', 'totalStockValue', 'locationIds', 'duplicateImei', 'duplicateLot', 'inventorySessionsToday', 'totalLots'
        ))->with([
            'locations' => $this->locationOptions($businessId),
            'filters' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
        ]);
    }

    public function export(Request $request) { return $this->index($request); }
    public function print(Request $request) { return $this->index($request); }
    public function refresh(Request $request) { return response()->json(['success' => 1]); }

    public function detail(Request $request, string $metric)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);
        $businessId = $this->businessId();
        $locationIds = (array) $request->input('location_ids', $this->permittedLocationIds($businessId));
        if ($request->filled('location_id')) {
            $locationIds = [(int) $request->input('location_id')];
        }

        $title = 'Dashboard Detail';
        $headers = [];
        $rows = collect();

        if ($metric === 'total_products' || $metric === 'total_stock_qty' || $metric === 'total_stock_value') {
            $title = $metric === 'total_products' ? 'Total Products Detail' : ($metric === 'total_stock_qty' ? 'Total Stock Qty Detail' : 'Total Stock Value Detail');
            $headers = ['SKU', 'Product', 'Variation', 'Location', 'Qty Available', 'Unit Cost', 'Stock Value'];
            $qtyFilters = (array) $request->input('qty_filter', []);
            $rows = DB::table('variation_location_details as vld')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
                ->where('p.business_id', $businessId)
                ->whereIn('vld.location_id', $locationIds)
                ->when(! empty($qtyFilters), function ($q) use ($qtyFilters) {
                    $q->where(function ($sq) use ($qtyFilters) {
                        if (in_array('non_zero', $qtyFilters, true)) {
                            $sq->orWhere('vld.qty_available', '!=', 0);
                        }
                        if (in_array('zero', $qtyFilters, true)) {
                            $sq->orWhere('vld.qty_available', '=', 0);
                        }
                        if (in_array('positive', $qtyFilters, true)) {
                            $sq->orWhere('vld.qty_available', '>', 0);
                        }
                        if (in_array('negative', $qtyFilters, true)) {
                            $sq->orWhere('vld.qty_available', '<', 0);
                        }
                    });
                })
                ->select('v.sub_sku as sku', 'p.name as product', 'v.name as variation', 'bl.name as location', 'vld.qty_available', 'v.default_purchase_price as unit_cost', DB::raw('(vld.qty_available * COALESCE(v.default_purchase_price,0)) as stock_value'))
                ->orderBy('p.name')
                ->limit(3000)
                ->get();
        } elseif ($metric === 'low_stock') {
            $title = 'Low Stock Products';
            $headers = ['SKU', 'Product', 'Variation', 'Location', 'Qty Available', 'Alert Qty'];
            $rows = DB::table('variation_location_details as vld')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
                ->where('p.business_id', $businessId)->whereIn('vld.location_id', $locationIds)
                ->whereRaw('vld.qty_available <= COALESCE(p.alert_quantity, 5)')
                ->select('v.sub_sku as sku', 'p.name as product', 'v.name as variation', 'bl.name as location', 'vld.qty_available', DB::raw('COALESCE(p.alert_quantity, 5) as alert_qty'))
                ->orderBy('vld.qty_available')
                ->limit(3000)->get();
        } elseif ($metric === 'negative_stock') {
            $title = 'Negative Stock Products';
            $headers = ['SKU', 'Product', 'Variation', 'Location', 'Qty Available'];
            $rows = DB::table('variation_location_details as vld')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
                ->where('p.business_id', $businessId)->whereIn('vld.location_id', $locationIds)
                ->where('vld.qty_available', '<', 0)
                ->select('v.sub_sku as sku', 'p.name as product', 'v.name as variation', 'bl.name as location', 'vld.qty_available')
                ->orderBy('vld.qty_available')
                ->limit(3000)->get();
        } elseif ($metric === 'mismatch') {
            $title = 'Mismatch Products';
            $headers = ['Date', 'Product ID', 'Variation ID', 'Location', 'Problem', 'Severity'];
            $rows = DB::table('smart_stock_mismatch_logs')
                ->where('business_id', $businessId)
                ->latest()
                ->limit(3000)
                ->get(['created_at', 'product_id', 'variation_id', 'location_id', 'problem', 'severity']);
        } elseif ($metric === 'duplicate_imei') {
            $title = 'Duplicate IMEI';
            $headers = ['IMEI', 'Count'];
            $rows = DB::table('smart_imei_histories')->where('business_id', $businessId)->groupBy('imei')->havingRaw('COUNT(*) > 1')->select('imei', DB::raw('COUNT(*) as total'))->limit(3000)->get();
        } elseif ($metric === 'duplicate_lot') {
            $title = 'Duplicate Lot';
            $headers = ['Lot Number', 'Count'];
            $rows = DB::table('smart_lot_histories')->where('business_id', $businessId)->groupBy('lot_number')->havingRaw('COUNT(*) > 1')->select('lot_number', DB::raw('COUNT(*) as total'))->limit(3000)->get();
        } elseif ($metric === 'pending_transfers') {
            $title = 'Pending Transfers';
            $headers = ['Date', 'Ref No', 'From Location', 'To Location', 'Status', 'Created By'];
            $rows = DB::table('transactions as t')
                ->leftJoin('transactions as t_in', function ($join) { $join->on('t_in.transfer_parent_id', '=', 't.id')->where('t_in.type', '=', 'purchase_transfer'); })
                ->leftJoin('business_locations as bl_from', 'bl_from.id', '=', 't.location_id')
                ->leftJoin('business_locations as bl_to', 'bl_to.id', '=', 't_in.location_id')
                ->leftJoin('users as u', 'u.id', '=', 't.created_by')
                ->where('t.business_id', $businessId)->where('t.type', 'sell_transfer')->where('t.status', '!=', 'final')
                ->select('t.transaction_date', 't.ref_no', 'bl_from.name as from_location', 'bl_to.name as to_location', 't.status', 'u.username as created_by')
                ->latest('t.transaction_date')->limit(3000)->get();
        } elseif ($metric === 'sessions_today') {
            $title = 'Inventory Sessions Today';
            $headers = ['Session', 'Location', 'Status', 'Created By', 'Created At'];
            $rows = DB::table('smart_stock_inventory_sessions as s')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 's.location_id')
                ->where('s.business_id', $businessId)->whereDate('s.created_at', now()->toDateString())
                ->select('s.name', 'bl.name as location', 's.status', 's.created_by', 's.created_at')
                ->latest('s.created_at')->limit(3000)->get();
        }

        $locations = $this->locationOptions($businessId);
        return view('smartstockinventory::dashboard.detail', compact('title', 'headers', 'rows', 'metric', 'locations', 'locationIds'));
    }
}

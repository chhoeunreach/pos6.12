<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MovementController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();
        [$start, $end] = $this->defaultDateRange($request->all());
        if ($request->filled('date_range') && strpos((string) $request->date_range, ' ~ ') !== false) {
            [$s, $e] = explode(' ~ ', (string) $request->date_range, 2);
            $start = \Illuminate\Support\Carbon::parse($s)->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($e)->endOfDay();
        }

        $mode = (string) $request->input('mode', '');
        if ($mode === 'pending_transfer') {
            $hasExplicitDateFilter = $request->filled('date_range') || $request->filled('start_date') || $request->filled('end_date');
            $rows = DB::table('transactions as t')
                ->leftJoin('business_locations as bl_from', 'bl_from.id', '=', 't.location_id')
                ->leftJoin('transactions as t_in', function ($join) {
                    $join->on('t_in.transfer_parent_id', '=', 't.id')
                        ->where('t_in.type', '=', 'purchase_transfer');
                })
                ->leftJoin('business_locations as bl_to', 'bl_to.id', '=', 't_in.location_id')
                ->leftJoin('users as u', 'u.id', '=', 't.created_by')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'sell_transfer')
                ->where('t.status', '!=', 'final')
                ->when($request->filled('location_id'), fn ($q) => $q->where('t.location_id', (int) $request->location_id))
                ->when($request->filled('product'), function ($q) use ($request) {
                    $q->whereExists(function ($sq) use ($request) {
                        $sq->select(DB::raw(1))
                            ->from('purchase_lines as pl')
                            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
                            ->join('products as p', 'p.id', '=', 'v.product_id')
                            ->whereColumn('pl.transaction_id', 't.id')
                            ->where('p.name', 'like', '%' . $request->product . '%');
                    });
                })
                ->when($request->filled('type'), fn ($q) => $q->where('t.type', $request->type))
                ->when($hasExplicitDateFilter, fn ($q) => $q->whereBetween('t.transaction_date', [$start, $end]))
                ->select(
                    't.transaction_date as movement_date',
                    't.ref_no as reference_no',
                    DB::raw("'sell_transfer_pending' as transaction_type"),
                    't.location_id',
                    DB::raw("CONCAT(COALESCE(bl_from.name,''), ' -> ', COALESCE(bl_to.name,'')) as location_name"),
                    DB::raw("'-' as product_name"),
                    DB::raw("'-' as sku"),
                    DB::raw("NULL as imei"),
                    DB::raw("NULL as lot_number"),
                    DB::raw("0 as qty_in"),
                    DB::raw("0 as qty_out"),
                    DB::raw("0 as balance_qty"),
                    'u.username as created_by_name'
                )
                ->orderByDesc('t.transaction_date')
                ->paginate(100);
        } else {
            $rows = DB::table('smart_stock_inventory_logs as l')
                ->where('l.business_id', $businessId)
                ->whereBetween('l.movement_date', [$start, $end])
                ->when($request->filled('location_id'), fn ($q) => $q->where('l.location_id', (int) $request->location_id))
                ->when($request->filled('product'), fn ($q) => $q->where('l.product_name', 'like', '%' . $request->product . '%'))
                ->when($request->filled('type'), fn ($q) => $q->where('l.transaction_type', $request->type))
                ->orderByDesc('l.movement_date')
                ->paginate(100);
        }

        $locations = $this->locationOptions($businessId);
        $users = DB::table('users')->where('business_id', $businessId)->pluck('username', 'id');
        $types = DB::table('smart_stock_inventory_logs')->where('business_id', $businessId)->distinct()->orderBy('transaction_type')->pluck('transaction_type', 'transaction_type');

        return view('smartstockinventory::movement.index', compact('rows', 'locations', 'users', 'types'));
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);

        $rows = DB::table('smart_stock_inventory_logs')
            ->where('business_id', $this->businessId())
            ->orderByDesc('movement_date')
            ->limit(10000)
            ->get()
            ->map(fn ($r) => [
                'Date' => $r->movement_date,
                'Reference No' => $r->reference_no,
                'Transaction Type' => $r->transaction_type,
                'Location' => $r->location_name,
                'Product' => $r->product_name,
                'SKU' => $r->sku,
                'IMEI' => $r->imei,
                'Lot Number' => $r->lot_number,
                'Qty In' => $r->qty_in,
                'Qty Out' => $r->qty_out,
                'Balance' => $r->balance_qty,
                'Created By' => $r->created_by_name,
            ])->all();

        return Excel::download(new ArrayExport($rows), 'smart_stock_movement_' . now()->format('Ymd_His') . '.xlsx');
    }
    public function print(Request $request) { return $this->index($request); }
}

<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Brands;
use App\BusinessLocation;
use App\Category;
use App\Exports\ArrayExport;
use App\Product;
use App\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Yajra\DataTables\Facades\DataTables;

class LotController extends BaseSmartStockController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();

        if ($request->ajax()) {
            $query = Product::where('products.business_id', $businessId)
                ->leftJoin('units', 'products.unit_id', '=', 'units.id')
                ->join('variations as v', 'products.id', '=', 'v.product_id')
                ->join('purchase_lines as pl', 'v.id', '=', 'pl.variation_id')
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                ->where('t.status', 'received')
                ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
                ->whereNotNull('pl.lot_number');

            $permittedLocations = auth()->user()->permitted_locations();
            if ($permittedLocations !== 'all') {
                $query->whereIn('t.location_id', $permittedLocations);
            }

            $locationId = $request->input('location_id');
            if (! empty($locationId)) {
                $query->where('t.location_id', $locationId);
            }

            $categoryId = $request->input('category_id');
            if (! empty($categoryId)) {
                $query->where('products.category_id', $categoryId);
            }
            $subCategoryId = $request->input('sub_category_id');
            if (! empty($subCategoryId)) {
                $query->where('products.sub_category_id', $subCategoryId);
            }
            $brandId = $request->input('brand_id');
            if (! empty($brandId)) {
                $query->where('products.brand_id', $brandId);
            }

            $stockStatus = $request->input('stock_status');

            $lotQtyAvailableSql = app(\App\Utils\TransactionUtil::class)->lotQuantityAvailableSql('pl');
            $normalSoldSql = app(\App\Utils\TransactionUtil::class)->lotQuantitySoldSql('pl');

            $selectBase = $query->select(
                'products.name as product',
                'v.name as variation_name',
                'v.sub_sku as sku',
                'pl.lot_number',
                'pl.exp_date as exp_date',
                DB::raw("SUM($normalSoldSql) as total_sold"),
                DB::raw('COALESCE(SUM(pl.quantity_adjusted), 0) as total_adjusted'),
                'products.type',
                'units.short_name as unit'
            )
                ->selectRaw("SUM($lotQtyAvailableSql) as stock")
                ->groupBy('v.id')
                ->groupBy('pl.lot_number');

            if ($stockStatus === 'positive') {
                $selectBase->havingRaw('stock > 0');
            } elseif ($stockStatus === 'negative') {
                $selectBase->havingRaw('stock < 0');
            }

            return DataTables::of($selectBase)
                ->addColumn('product_display', function ($row) {
                    if ($row->variation_name !== 'DUMMY') {
                        return $row->product . ' (' . $row->variation_name . ')';
                    }
                    return $row->product;
                })
                ->editColumn('exp_date', function ($row) {
                    if (! empty($row->exp_date)) {
                        $carbon_exp = \Carbon::createFromFormat('Y-m-d', $row->exp_date);
                        $carbon_now = \Carbon::now();
                        if ($carbon_now->diffInDays($carbon_exp, false) >= 0) {
                            return $this->util->format_date($row->exp_date) . '<br><small>( <span class="time-to-now">' . $row->exp_date . '</span> )</small>';
                        } else {
                            return $this->util->format_date($row->exp_date) . ' &nbsp; <span class="label label-danger no-print">' . __('report.expired') . '</span><span class="print_section">' . __('report.expired') . '</span><br><small>( <span class="time-from-now">' . $row->exp_date . '</span> )</small>';
                        }
                    }
                    return '--';
                })
                ->editColumn('lot_number', function ($row) {
                    return '<a href="javascript:void(0)" class="lot-history-link" data-lot=" . e($row->lot_number) . "" target="_blank">' . e($row->lot_number) . '</a>';
                })
                ->addColumn('action', function ($row) {
                    return '<a class="btn btn-xs btn-info" href="' . ssi_route('ssi.lot.history', $row->lot_number) . '" target="_blank"><i class="fa fa-history"></i> History</a>';
                })
                ->editColumn('stock', function ($row) {
                    $stock = $row->stock ?: 0;
                    return '<span data-is_quantity="true" class="display_currency total_stock" data-currency_symbol=false data-orig-value="' . (float) $stock . '" data-unit="' . $row->unit . '" >' . (float) $stock . '</span> ' . $row->unit;
                })
                ->editColumn('total_sold', function ($row) {
                    if ($row->total_sold) {
                        return '<span data-is_quantity="true" class="display_currency total_sold" data-currency_symbol=false data-orig-value="' . (float) $row->total_sold . '" data-unit="' . $row->unit . '" >' . (float) $row->total_sold . '</span> ' . $row->unit;
                    }
                    return '0 ' . $row->unit;
                })
                ->editColumn('total_adjusted', function ($row) {
                    if ($row->total_adjusted) {
                        return '<span data-is_quantity="true" class="display_currency total_adjusted" data-currency_symbol=false data-orig-value="' . (float) $row->total_adjusted . '" data-unit="' . $row->unit . '" >' . (float) $row->total_adjusted . '</span> ' . $row->unit;
                    }
                    return '0 ' . $row->unit;
                })
                ->filterColumn('product_display', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('products.name', 'like', '%' . $keyword . '%')
                            ->orWhere('v.name', 'like', '%' . $keyword . '%')
                            ->orWhere('v.sub_sku', 'like', '%' . $keyword . '%');
                    });
                })
                ->removeColumn('variation_name')
                ->removeColumn('type')
                ->removeColumn('unit')
                ->rawColumns(['exp_date', 'lot_number', 'stock', 'total_sold', 'total_adjusted', 'action'])
                ->make(true);
        }

        $categories = Category::forDropdown($businessId, 'product');
        $brands = Brands::forDropdown($businessId);
        $units = Unit::where('business_id', $businessId)->pluck('short_name', 'id');
        $business_locations = BusinessLocation::forDropdown($businessId, true);

        return view('smartstockinventory::lot.index', compact('categories', 'brands', 'units', 'business_locations'));
    }

    public function updateLot(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.update'), 403);
        $d = $request->validate(['id' => 'required|integer', 'lot_number' => 'required|string|max:191', 'reason' => 'required|string|max:500']);
        $row = DB::table('smart_lot_histories')->where('business_id', $this->businessId())->whereNull('deleted_at')->where('id', $d['id'])->first();
        abort_unless($row, 404);
        DB::table('smart_lot_histories')->where('id', $d['id'])->update(['lot_number' => $d['lot_number']]);
        $userName = trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')));
        if ($userName === '') { $userName = (string) (auth()->user()->username ?? ''); }
        SmartStockActionLog::create(['user_id' => auth()->id(), 'user_name' => $userName, 'business_id' => $this->businessId(), 'module_name' => 'SmartStockInventory', 'table_name' => 'smart_lot_histories', 'record_id' => $row->id, 'location_id' => $row->location_id, 'action_type' => 'update_lot_detail', 'reference_type' => 'smart_lot_histories', 'reference_id' => $row->id, 'old_data' => json_encode($row), 'new_data' => json_encode(['lot_number' => $d['lot_number']]), 'reason' => $d['reason'], 'ip_address' => $request->ip()]);
        return back()->with('status', ['success' => 1, 'msg' => 'Lot updated']);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.export'), 403);
        $filters = $this->lotFilters($request);
        $rows = $this->lotExportQuery($filters)->orderByDesc('movement_date')->limit(10000)->get()->map(fn ($r) => [
            'Lot Number' => $r->lot_number,
            'SKU' => $r->sku ?? '-',
            'Product' => trim(($r->product_name ?? '-') . (($r->variation_name ?? '') && ($r->variation_name ?? '') !== 'DUMMY' ? ' - ' . $r->variation_name : '')),
            'Location' => $r->location_name ?? '-',
            'Expiry Date' => $r->expiry_date ?? '-',
            'Qty' => $r->movement_type === 'transfer'
                ? 'Move ' . max((float) $r->qty_in, (float) $r->qty_out)
                : ((float) $r->qty_in > 0 ? '+' . (float) $r->qty_in : '-' . (float) $r->qty_out),
            'Balance' => $r->balance_qty,
            'Date' => $r->movement_date,
        ])->all();
        return Excel::download(new ArrayExport($rows), 'lot_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function history(Request $request, string $lot)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();

        $permittedLocations = auth()->user()->permitted_locations();
        $locationId = $request->input('location_id');
        [$currentStock, $stockLocationDisplay] = $this->lotStockSummary($businessId, $lot, $permittedLocations, $locationId);
        $stockReview = $this->lotStockReview($businessId, $lot, $permittedLocations, $locationId);
        $stockNeedsReview = $stockReview['balances']->isNotEmpty() || $stockReview['allocations']->isNotEmpty();

        if ($request->ajax()) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $applyCommonFilters = function ($query, $tableAlias = 't', $locationFilter = null) use ($permittedLocations, $locationId, $lot) {
                if ($permittedLocations !== 'all') {
                    if (is_callable($locationFilter)) {
                        $locationFilter($query, $permittedLocations);
                    } else {
                        $query->whereIn($tableAlias . '.location_id', $permittedLocations);
                    }
                }
                if (! empty($locationId)) {
                    if (is_callable($locationFilter)) {
                        $locationFilter($query, [$locationId]);
                    } else {
                        $query->where($tableAlias . '.location_id', $locationId);
                    }
                }
                $query->where('pl.lot_number', $lot);
            };

            $purchaseMovements = DB::table('purchase_lines as pl')
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('contacts as supplier', 't.contact_id', '=', 'supplier.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->where('t.business_id', $businessId)
                ->whereIn('t.type', ['purchase', 'opening_stock', 'production_purchase'])
                ->where('t.status', 'received')
                ->whereNotNull('pl.lot_number');

            $applyCommonFilters($purchaseMovements, 't');

            $purchaseMovements = $purchaseMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'purchase' as movement_type"),
                DB::raw('1 as movement_sort'),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw('bl.name as location_display'),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(t.ref_no, t.invoice_no, '') as ref_no"),
                DB::raw("COALESCE(NULLIF(supplier.supplier_business_name, ''), supplier.name, '') as contact"),
                DB::raw('(COALESCE(pl.quantity, 0) - COALESCE(pl.quantity_returned, 0)) as qty_in'),
                DB::raw('0 as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("'' as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            $sellMovements = $this->lotSaleLinesQuery($businessId)
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('contacts as customer', 't.contact_id', '=', 'customer.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->whereNotNull('pl.lot_number');

            $applyCommonFilters($sellMovements, 't');

            $sellMovements = $sellMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'sell' as movement_type"),
                DB::raw('4 as movement_sort'),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw('bl.name as location_display'),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(NULLIF(t.ref_no, ''), t.invoice_no, '') as ref_no"),
                DB::raw("COALESCE(NULLIF(customer.supplier_business_name, ''), customer.name, '') as contact"),
                DB::raw('0 as qty_in'),
                DB::raw('CASE WHEN COALESCE(tsl.lot_no_line_id, 0) > 0 THEN tsl.quantity ELSE COALESCE(tspl.quantity, 0) END as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            $sellReturnMovements = DB::table('transaction_sell_lines as return_tsl')
                ->join('purchase_lines as pl', 'return_tsl.lot_no_line_id', '=', 'pl.id')
                ->join('transactions as t', 'return_tsl.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('contacts as customer', 't.contact_id', '=', 'customer.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'sell_return')
                ->whereNotNull('return_tsl.parent_sell_line_id')
                ->whereNotNull('pl.lot_number');

            $applyCommonFilters($sellReturnMovements, 't');

            $sellReturnMovements = $sellReturnMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'sell_return' as movement_type"),
                DB::raw('5 as movement_sort'),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw('bl.name as location_display'),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(NULLIF(t.ref_no, ''), t.invoice_no, '') as ref_no"),
                DB::raw("COALESCE(NULLIF(customer.supplier_business_name, ''), customer.name, '') as contact"),
                DB::raw('COALESCE(return_tsl.quantity, 0) as qty_in'),
                DB::raw('0 as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            $transferQuantitySql = 'CASE WHEN COALESCE(tsl.lot_no_line_id, 0) > 0 THEN tsl.quantity - COALESCE(tsl.quantity_returned, 0) ELSE COALESCE(tspl.quantity, 0) - COALESCE(tspl.qty_returned, 0) END';
            $transferMovements = $this->lotMovementLinesQuery($businessId, ['sell_transfer'])
                ->join('products as p', 'tsl.product_id', '=', 'p.id')
                ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('transactions as t_in', function ($join) {
                    $join->on('t_in.transfer_parent_id', '=', 't.id')
                        ->where('t_in.type', '=', 'purchase_transfer');
                })
                ->leftJoin('business_locations as bl_to', 't_in.location_id', '=', 'bl_to.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->whereNotNull('pl.lot_number');

            $applyCommonFilters($transferMovements, 't');

            $transferMovements = $transferMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'transfer' as movement_type"),
                DB::raw('2 as movement_sort'),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw("CONCAT('From ', COALESCE(bl.name, '-'), ' to ', COALESCE(bl_to.name, '-')) as location_display"),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(NULLIF(t.ref_no, ''), t.invoice_no, '') as ref_no"),
                DB::raw("'' as contact"),
                DB::raw('0 as qty_in'),
                DB::raw($transferQuantitySql.' as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            // Incoming stock belongs to the actual destination batch, which may differ
            // from the source on older inconsistent transfers. Never invent its receipt.
            $transferReceipts = DB::table('purchase_lines as pl')
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                ->leftJoin('transactions as source', function ($join) {
                    $join->on('source.id', '=', 't.transfer_parent_id')
                        ->on('source.business_id', '=', 't.business_id')
                        ->where('source.type', '=', 'sell_transfer');
                })
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 'source.location_id', '=', 'bl.id')
                ->leftJoin('business_locations as bl_to', 't.location_id', '=', 'bl_to.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'purchase_transfer')->where('t.status', 'received')
                ->whereNotNull('pl.lot_number');
            $applyCommonFilters($transferReceipts, 't');
            $transferReceipts = $transferReceipts->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'transfer' as movement_type"),
                DB::raw('3 as movement_sort'),
                DB::raw('COALESCE(t.transfer_parent_id, t.id) as transaction_id'),
                DB::raw('t.type as transaction_type'),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl_to.name as location_name'),
                DB::raw("CONCAT('From ', COALESCE(bl.name, '-'), ' to ', COALESCE(bl_to.name, '-')) as location_display"),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(NULLIF(t.ref_no, ''), t.invoice_no, '') as ref_no"),
                DB::raw("'' as contact"),
                DB::raw('(COALESCE(pl.quantity, 0) - COALESCE(pl.quantity_returned, 0)) as qty_in'),
                DB::raw('0 as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            $adjustmentMovements = DB::table('transaction_sell_lines_purchase_lines as tspl')
                ->join('purchase_lines as pl', 'tspl.purchase_line_id', '=', 'pl.id')
                ->join('stock_adjustment_lines as sal', 'tspl.stock_adjustment_line_id', '=', 'sal.id')
                ->join('transactions as t', 'sal.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('users as creator', 't.created_by', '=', 'creator.id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'stock_adjustment')
                ->whereNotNull('pl.lot_number')
                ->whereNotNull('tspl.stock_adjustment_line_id');

            $applyCommonFilters($adjustmentMovements, 't');

            $adjustmentMovements = $adjustmentMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'adjustment' as movement_type"),
                DB::raw('6 as movement_sort'),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw('bl.name as location_display'),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(t.ref_no, '') as ref_no"),
                DB::raw("'' as contact"),
                DB::raw('0 as qty_in'),
                DB::raw('COALESCE(tspl.quantity, 0) as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
                DB::raw("CONCAT(COALESCE(creator.surname, ''),' ',COALESCE(creator.first_name, ''),' ',COALESCE(creator.last_name,'')) as user_name"),
            ]);

            $movementType = $request->input('movement_type');
            $movementUnion = $purchaseMovements->unionAll($transferMovements)
                ->unionAll($transferReceipts)
                ->unionAll($sellMovements)->unionAll($sellReturnMovements)->unionAll($adjustmentMovements);

            $movementRows = DB::query()
                ->fromSub($movementUnion, 'lot_movements')
                ->orderBy('movement_date')
                ->orderBy('movement_sort')
                ->orderBy('transaction_id')
                ->get();

            $movementRows = $this->prepareLotHistoryRows($movementRows, $movementType, $startDate, $endDate);

            return DataTables::of($movementRows)
                ->addColumn('transaction_id', function ($row) {
                    return (int) ($row->transaction_id ?? 0);
                })
                ->addColumn('transaction_type', function ($row) {
                    return (string) ($row->transaction_type ?? '');
                })
                ->addColumn('movement_type_key', function ($row) {
                    return (string) ($row->movement_type ?? '');
                })
                ->editColumn('ref_no', function ($row) {
                    $refNo = (string) ($row->ref_no ?? '');
                    if ($refNo === '') {
                        return '--';
                    }

                    $txId = (int) ($row->transaction_id ?? 0);
                    $txType = (string) ($row->transaction_type ?? '');
                    $viewUrl = $this->resolveTransactionUrl($txType, $txId);

                    if ($viewUrl === null) {
                        return e($refNo);
                    }

                    return '<a href="#" class="btn-modal" data-href="' . e($viewUrl) . '" data-container=".view_modal" title="' . e($refNo) . '">' . e($refNo) . '</a>';
                })
                ->editColumn('movement_date', function ($row) {
                    return $this->util->format_date($row->movement_date, true);
                })
                ->editColumn('movement_type', function ($row) {
                    if ($row->movement_type === 'purchase') {
                        return __('purchase.purchase');
                    } elseif ($row->movement_type === 'sell') {
                        return __('sale.sale');
                    } elseif ($row->movement_type === 'sell_return') {
                        return 'Cancel Sell';
                    } elseif ($row->movement_type === 'transfer') {
                        return __('lang_v1.stock_transfers');
                    } elseif ($row->movement_type === 'adjustment') {
                        return __('stock_adjustment.stock_adjustment');
                    }
                    return $row->movement_type;
                })
                ->editColumn('qty_in', function ($row) {
                    $qty = (float) ($row->qty_in ?? 0);
                    $unit = e($row->unit);
                    if ($qty <= 0) {
                        return '<span class="text-muted">-</span>';
                    }

                    return '<span class="display_currency qty_in text-success text-bold" data-is_quantity="true" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . $unit . '">' . $qty . '</span>';
                })
                ->editColumn('qty_out', function ($row) {
                    $qty = (float) ($row->qty_out ?? 0);
                    $unit = e($row->unit);
                    if ($qty <= 0) {
                        return '<span class="text-muted">-</span>';
                    }

                    $displayQty = $row->movement_type === 'adjustment' ? '-' . $qty : $qty;

                    return '<span class="display_currency qty_out text-danger text-bold" data-is_quantity="true" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . $unit . '">' . $displayQty . '</span>';
                })
                ->addColumn('balance_qty', function ($row) {
                    $balance = (float) ($row->balance_qty ?? 0);
                    $class = $balance > 0 ? 'text-success' : ($balance < 0 ? 'text-danger' : 'text-red');

                    return '<span class="display_currency balance_qty ' . $class . ' text-bold" data-is_quantity="true" data-currency_symbol=false data-orig-value="' . $balance . '">' . $balance . '</span>';
                })
                ->addColumn('quantity_change', function ($row) {
                    $renderQuantity = function (float $quantity) {
                        $class = $quantity > 0 ? 'text-success' : ($quantity < 0 ? 'text-danger' : 'text-muted');
                        $prefix = $quantity > 0 ? '+' : '';

                        return '<span class="' . $class . ' text-bold">' . $prefix
                            . e($this->util->num_f($quantity, false, null, true)) . '</span>';
                    };
                    if ($row->movement_type === 'transfer' && (float) $row->qty_in > 0 && (float) $row->qty_out > 0) {
                        return $renderQuantity(-(float) $row->qty_out) . ' / ' . $renderQuantity((float) $row->qty_in);
                    }

                    return $renderQuantity((float) $row->quantity_change);
                })
                ->addColumn('status', function ($row) {
                    if ($row->movement_type === 'sell') {
                        return '<span class="lot-status lot-status-sold">Sold</span>';
                    }
                    if ($row->movement_type === 'transfer') {
                        return '<span class="lot-status lot-status-transferred">Transferred</span>';
                    }
                    if ($row->movement_type === 'adjustment') {
                        return '<span class="lot-status lot-status-adjusted">Adjusted</span>';
                    }

                    return '<span class="lot-status lot-status-stock">In Stock</span>';
                })
                ->editColumn('exp_date', function ($row) {
                    if (! empty($row->exp_date)) {
                        return $this->util->format_date($row->exp_date);
                    }
                    return '--';
                })
                ->editColumn('location_display', function ($row) {
                    return e($row->location_display ?: '-');
                })
                ->editColumn('user_name', function ($row) {
                    $userName = trim((string) ($row->user_name ?? ''));

                    return $userName !== '' ? e($userName) : '--';
                })
                ->rawColumns(['qty_in', 'qty_out', 'balance_qty', 'quantity_change', 'status', 'ref_no'])
                ->with(['current_stock' => $currentStock, 'stock_location_display' => $stockLocationDisplay,
                    'stock_reconciliation_required' => $stockNeedsReview,
                    'stock_review_html' => view('smartstockinventory::lot.partials.stock_review', compact('stockReview'))->render()])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($businessId, true);

        $lotInfo = DB::table('purchase_lines as pl')
            ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->join('products as p', 'pl.product_id', '=', 'p.id')
            ->join('variations as v', 'pl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('pl.lot_number', $lot)
            ->where('t.business_id', $businessId)
            ->where('t.status', 'received')
            ->when($permittedLocations !== 'all', fn ($q) => $q->whereIn('t.location_id', $permittedLocations))
            ->when(! empty($locationId), fn ($q) => $q->where('t.location_id', $locationId))
            ->select(
                'v.sub_sku as sku',
                'p.name as product_name',
                'v.name as variation_name',
                'pl.lot_number',
                'pl.exp_date as exp_date',
                'bl.name as location_name',
                'u.short_name as unit'
            )
            ->first();

        if ($lotInfo) {
            $lotInfo->location_name = $stockLocationDisplay;
        }

        return view('smartstockinventory::lot.history', compact('business_locations', 'lot', 'lotInfo', 'currentStock', 'stockReview', 'stockNeedsReview'));
    }

    protected function lotStockReview(int $businessId, string $lot, $permittedLocations, $locationId = null): array
    {
        $util = app(\App\Utils\TransactionUtil::class);
        $balances = DB::table('purchase_lines as pl')->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->where('t.business_id', $businessId)->where('t.status', 'received')->where('pl.lot_number', $lot)
            ->when($permittedLocations !== 'all', fn ($q) => $q->whereIn('t.location_id', $permittedLocations))
            ->when(! empty($locationId), fn ($q) => $q->where('t.location_id', $locationId))
            ->select('pl.id')->selectRaw($util->lotQuantityAvailableSql('pl').' as history_available')
            ->selectRaw('(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned - pl.mfg_quantity_used) as recorded_available')
            ->get()->filter(fn ($row) => abs((float) $row->history_available - (float) $row->recorded_available) > 0.0001)->values();
        $allocations = DB::table('transaction_sell_lines_purchase_lines as m')
            ->join('transaction_sell_lines as s', 'm.sell_line_id', '=', 's.id')
            ->join('transactions as t', 's.transaction_id', '=', 't.id')
            ->join('purchase_lines as allocated', 'm.purchase_line_id', '=', 'allocated.id')
            ->join('transactions as receipt', 'allocated.transaction_id', '=', 'receipt.id')
            ->join('purchase_lines as selected', 's.lot_no_line_id', '=', 'selected.id')
            ->join('transactions as selected_receipt', 'selected.transaction_id', '=', 'selected_receipt.id')
            ->where('t.business_id', $businessId)->where('receipt.business_id', $businessId)->where('selected_receipt.business_id', $businessId)
            ->where('t.status', 'final')->whereIn('t.type', ['sell', 'sell_transfer'])
            ->whereColumn('m.purchase_line_id', '!=', 's.lot_no_line_id')
            ->whereRaw('m.quantity > COALESCE(m.qty_returned, 0)')
            ->where(fn ($q) => $q->where('allocated.lot_number', $lot)->orWhere('selected.lot_number', $lot))
            ->when($permittedLocations !== 'all', function ($q) use ($permittedLocations) {
                $q->whereIn('t.location_id', $permittedLocations)->whereIn('receipt.location_id', $permittedLocations)
                    ->whereIn('selected_receipt.location_id', $permittedLocations);
            })
            ->when(! empty($locationId), fn ($q) => $q->where('t.location_id', $locationId))
            ->orderBy('t.transaction_date')->get(['t.id', 's.id as sell_line_id', 't.type', 't.ref_no', 't.invoice_no', 't.transaction_date',
                'selected.lot_number as selected_serial', 'allocated.lot_number as allocated_serial',
                'receipt.transaction_date as receipt_date', 'm.quantity', 'm.qty_returned']);
        foreach ($allocations as $row) {
            $row->reference = $row->ref_no ?: ($row->invoice_no ?: '#'.$row->id);
            $row->review_url = null;
            $row->correction_url = null;
            $row->deducted_serial_fix_url = null;
            if ($row->type === 'sell_transfer' && (auth()->user()->can('superadmin') || auth()->user()->can('stock_transfer.update'))) {
                $row->review_url = ssi_url('/stock-transfers/'.$row->id.'/edit');
                $row->correction_url = $row->review_url.'?correct_line='.$row->sell_line_id;
                $row->deducted_serial_fix_url = action([\App\Http\Controllers\StockTransferController::class, 'fixDeductedSerial'], [$row->id]);
            } elseif ($row->type === 'sell' && auth()->user()->can('sell.view')) {
                $row->review_url = ssi_url('/sells/'.$row->id);
            }
        }

        return compact('balances', 'allocations');
    }

    protected function lotStockSummary(int $businessId, string $lot, $permittedLocations, $locationId = null): array
    {
        $quantitySql = app(\App\Utils\TransactionUtil::class)->lotQuantityAvailableSql('pl');
        $locations = DB::table('purchase_lines as pl')
            ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
            ->where('t.business_id', $businessId)
            ->where('t.status', 'received')
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->where('pl.lot_number', $lot)
            ->when($permittedLocations !== 'all', fn ($q) => $q->whereIn('t.location_id', $permittedLocations))
            ->when(! empty($locationId), fn ($q) => $q->where('t.location_id', $locationId))
            ->select('bl.name as location_name')
            ->selectRaw("COALESCE(SUM($quantitySql), 0) as current_stock")
            ->groupBy('t.location_id', 'bl.name')->orderBy('bl.name')->get();

        return [
            (float) $locations->sum('current_stock'),
            $locations->filter(fn ($row) => (float) $row->current_stock > 0)
                ->pluck('location_name')->filter()->implode(', '),
        ];
    }

    protected function prepareLotHistoryRows($rows, ?string $movementType = null, ?string $startDate = null, ?string $endDate = null)
    {
        $balance = 0;
        // Apply display filters after calculating the complete ledger for the visible locations.
        $rows = $rows->map(function ($row) use (&$balance) {
            $row->quantity_change = (float) ($row->qty_in ?? 0) - (float) ($row->qty_out ?? 0);
            $balance += $row->quantity_change;
            $row->balance_qty = $balance;

            return $row;
        });
        $types = match ($movementType) {
            'purchase' => ['purchase'],
            'sell' => ['sell', 'sell_return'],
            'transfer' => ['transfer'],
            'adjustment' => ['adjustment'],
            default => null,
        };

        return $rows->filter(function ($row) use ($types, $startDate, $endDate) {
            $date = substr((string) $row->movement_date, 0, 10);

            return ($types === null || in_array($row->movement_type, $types, true))
                && (empty($startDate) || empty($endDate) || ($date >= $startDate && $date <= $endDate));
        })->reverse()->values();
    }

    protected function lotSaleLinesQuery(int $businessId)
    {
        return $this->lotMovementLinesQuery($businessId, ['sell', 'production_sell']);
    }

    protected function lotMovementLinesQuery(int $businessId, array $types)
    {
        // An explicit invoice lot is authoritative; allocations can be reassigned later.
        return DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines_purchase_lines as tspl', function ($join) {
                $join->on('tspl.sell_line_id', '=', 'tsl.id')
                    ->whereRaw('COALESCE(tsl.lot_no_line_id, 0) = 0');
            })
            ->join('purchase_lines as pl', 'pl.id', '=', DB::raw('COALESCE(NULLIF(tsl.lot_no_line_id, 0), tspl.purchase_line_id)'))
            ->where('t.business_id', $businessId)
            ->whereIn('t.type', $types)
            ->where('t.status', 'final');
    }

    private function resolveTransactionUrl(string $transactionType, int $transactionId): ?string
    {
        if ($transactionId <= 0) {
            return null;
        }

        return match ($transactionType) {
            'sell', 'production_sell' => ssi_url('/sells/' . $transactionId),
            'sell_return' => ssi_url('/sell-return/' . $transactionId),
            'purchase', 'opening_stock', 'production_purchase' => ssi_url('/purchases/' . $transactionId),
            'purchase_return' => ssi_url('/purchase-return/' . $transactionId),
            'stock_adjustment' => ssi_url('/stock-adjustments/' . $transactionId),
            'sell_transfer', 'purchase_transfer', 'sell_transfer_pending' => ssi_url('/stock-transfers/' . $transactionId),
            default => null,
        };
    }

    private function lotFilters(Request $request): array
    {
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        return [
            'q' => trim((string) $request->input('q', '')),
            'product' => trim((string) $request->input('product', '')),
            'location_id' => $request->input('location_id'),
            'balance_status' => (string) $request->input('balance_status', ''),
            'start_date' => (string) $request->input('start_date', ''),
            'end_date' => (string) $request->input('end_date', ''),
            'per_page' => $perPage,
        ];
    }

    private function lotExportQuery(array $filters)
    {
        return DB::table('smart_lot_histories as l')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('variations as v', 'v.id', '=', 'l.variation_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 'l.location_id')
            ->where('l.business_id', $this->businessId())
            ->whereNull('l.deleted_at')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where('l.lot_number', 'like', '%'.$filters['q'].'%');
            })
            ->when($filters['product'] !== '', function ($query) use ($filters) {
                $like = '%'.$filters['product'].'%';
                $query->where(function ($q) use ($like) {
                    $q->where('p.name', 'like', $like)
                        ->orWhere('v.sub_sku', 'like', $like)
                        ->orWhere('v.name', 'like', $like);
                });
            })
            ->when(! empty($filters['location_id']), function ($query) use ($filters) {
                $query->where('l.location_id', $filters['location_id']);
            })
            ->when($filters['balance_status'] !== '', function ($query) use ($filters) {
                if ($filters['balance_status'] === 'positive') {
                    $query->where('l.balance_qty', '>', 0);
                } elseif ($filters['balance_status'] === 'zero') {
                    $query->where('l.balance_qty', '=', 0);
                } elseif ($filters['balance_status'] === 'negative') {
                    $query->where('l.balance_qty', '<', 0);
                }
            })
            ->when($filters['start_date'] !== '', function ($query) use ($filters) {
                $query->whereDate('l.movement_date', '>=', $filters['start_date']);
            })
            ->when($filters['end_date'] !== '', function ($query) use ($filters) {
                $query->whereDate('l.movement_date', '<=', $filters['end_date']);
            })
            ->select(
                'l.*',
                'bl.name as location_name',
                'p.name as product_name',
                'v.name as variation_name',
                'v.sub_sku as sku'
            );
    }
}

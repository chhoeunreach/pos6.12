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
                ->leftJoin('transaction_sell_lines_purchase_lines as tspl', 'pl.id', '=', 'tspl.purchase_line_id')
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
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

            $stockJoin = '';
            $stockWhere = [];
            $stockBindings = [];
            if ($permittedLocations !== 'all') {
                $stockJoin = ' LEFT JOIN transactions as t2 on pls.transaction_id=t2.id';
                $placeholders = implode(', ', array_fill(0, count($permittedLocations), '?'));
                $stockWhere[] = "t2.location_id IN ($placeholders)";
                $stockBindings = array_merge($stockBindings, array_map('intval', $permittedLocations));
            }
            if (! empty($locationId)) {
                $stockJoin = ' LEFT JOIN transactions as t2 on pls.transaction_id=t2.id';
                $stockWhere[] = 't2.location_id = ?';
                $stockBindings[] = (int) $locationId;
            }
            $stockWhereSql = ! empty($stockWhere) ? 'WHERE ' . implode(' AND ', $stockWhere) . ' AND ' : 'WHERE ';

            $selectBase = $query->select(
                'products.name as product',
                'v.name as variation_name',
                'v.sub_sku as sku',
                'pl.lot_number',
                'pl.exp_date as exp_date',
                DB::raw('COALESCE(SUM(IF(tspl.sell_line_id IS NULL, 0, (tspl.quantity - tspl.qty_returned)) ), 0) as total_sold'),
                DB::raw('COALESCE(SUM(IF(tspl.stock_adjustment_line_id IS NULL, 0, tspl.quantity ) ), 0) as total_adjusted'),
                'products.type',
                'units.short_name as unit'
            )
                ->selectRaw("(COALESCE((SELECT SUM(quantity - quantity_returned) from purchase_lines as pls$stockJoin $stockWhereSql variation_id = v.id AND lot_number = pl.lot_number), 0) - SUM(COALESCE((tspl.quantity - tspl.qty_returned), 0))) as stock", $stockBindings)
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
            'Qty In' => $r->qty_in,
            'Qty Out' => $r->qty_out,
            'Balance' => $r->balance_qty,
            'Date' => $r->movement_date,
        ])->all();
        return Excel::download(new ArrayExport($rows), 'lot_report_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function history(Request $request, string $lot)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();

        if ($request->ajax()) {
            $permittedLocations = auth()->user()->permitted_locations();
            $locationId = $request->input('location_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $applyCommonFilters = function ($query, $tableAlias = 't', $locationFilter = null) use ($permittedLocations, $locationId, $startDate, $endDate, $lot) {
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
                if (! empty($startDate) && ! empty($endDate)) {
                    $query->whereRaw("DATE({$tableAlias}.transaction_date) BETWEEN ? AND ?", [$startDate, $endDate]);
                }
            };

            $transferLocationFilter = function ($query, array $locationIds) {
                $query->where(function ($q) use ($locationIds) {
                    $q->whereIn('t.location_id', $locationIds)
                        ->orWhereIn('t_in.location_id', $locationIds);
                });
            };

            $purchaseMovements = DB::table('purchase_lines as pl')
                ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('contacts as supplier', 't.contact_id', '=', 'supplier.id')
                ->where('t.business_id', $businessId)
                ->where('t.type', '!=', 'purchase_transfer')
                ->whereNotNull('pl.lot_number');

            $applyCommonFilters($purchaseMovements, 't');

            $purchaseMovements = $purchaseMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'purchase' as movement_type"),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
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
            ]);

            $sellMovements = DB::table('transaction_sell_lines_purchase_lines as tspl')
                ->join('purchase_lines as pl', 'tspl.purchase_line_id', '=', 'pl.id')
                ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
                ->join('transactions as t', 'tsl.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('transactions as t_in', function ($join) {
                    $join->on('t_in.transfer_parent_id', '=', 't.id')
                        ->where('t_in.type', '=', 'purchase_transfer');
                })
                ->leftJoin('business_locations as bl_to', 't_in.location_id', '=', 'bl_to.id')
                ->leftJoin('contacts as customer', 't.contact_id', '=', 'customer.id')
                ->where('t.business_id', $businessId)
                ->whereNotNull('pl.lot_number')
                ->whereNotNull('tspl.sell_line_id');

            $applyCommonFilters($sellMovements, 't', $transferLocationFilter);

            $sellMovements = $sellMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("CASE WHEN t.type = 'sell_transfer' THEN 'transfer' ELSE 'sell' END as movement_type"),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw("CASE WHEN t.type = 'sell_transfer' THEN CONCAT('Out: ', COALESCE(bl.name, ''), ' -> In: ', COALESCE(bl_to.name, '')) ELSE bl.name END as location_name"),
                DB::raw('v.sub_sku as sku'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw("COALESCE(t.ref_no, t.invoice_no, '') as ref_no"),
                DB::raw("COALESCE(NULLIF(customer.supplier_business_name, ''), customer.name, '') as contact"),
                DB::raw('0 as qty_in'),
                DB::raw('(COALESCE(tspl.quantity, 0) - COALESCE(tspl.qty_returned, 0)) as qty_out'),
                DB::raw('u.short_name as unit'),
                DB::raw("COALESCE(t.additional_notes, '') as notes"),
            ]);

            $adjustmentMovements = DB::table('transaction_sell_lines_purchase_lines as tspl')
                ->join('purchase_lines as pl', 'tspl.purchase_line_id', '=', 'pl.id')
                ->join('stock_adjustment_lines as sal', 'tspl.stock_adjustment_line_id', '=', 'sal.id')
                ->join('transactions as t', 'sal.transaction_id', '=', 't.id')
                ->join('products as p', 'pl.product_id', '=', 'p.id')
                ->join('variations as v', 'pl.variation_id', '=', 'v.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'stock_adjustment')
                ->whereNotNull('pl.lot_number')
                ->whereNotNull('tspl.stock_adjustment_line_id');

            $applyCommonFilters($adjustmentMovements, 't');

            $adjustmentMovements = $adjustmentMovements->select([
                DB::raw('t.transaction_date as movement_date'),
                DB::raw("'adjustment' as movement_type"),
                DB::raw('t.id as transaction_id'),
                DB::raw("t.type as transaction_type"),
                DB::raw('t.location_id as location_id'),
                DB::raw('bl.name as location_name'),
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
            ]);

            $movementType = $request->input('movement_type');
            $movementUnion = $purchaseMovements;

            if (empty($movementType) || $movementType === 'all') {
                $movementUnion->unionAll($sellMovements)->unionAll($adjustmentMovements);
            } elseif ($movementType === 'sell') {
                $sellMovements->where('t.type', '!=', 'sell_transfer');
                $movementUnion = $sellMovements;
            } elseif ($movementType === 'transfer') {
                $sellMovements->where('t.type', 'sell_transfer');
                $movementUnion = $sellMovements;
            } elseif ($movementType === 'adjustment') {
                $movementUnion = $adjustmentMovements;
            }

            $movements = DB::query()->fromSub($movementUnion, 'lot_movements');

            return DataTables::of($movements)
                ->addColumn('transaction_id', function ($row) {
                    return (int) ($row->transaction_id ?? 0);
                })
                ->addColumn('transaction_type', function ($row) {
                    return (string) ($row->transaction_type ?? '');
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
                    } elseif ($row->movement_type === 'transfer') {
                        return 'sell_transfer';
                    } elseif ($row->movement_type === 'adjustment') {
                        return __('stock_adjustment.stock_adjustment');
                    }
                    return $row->movement_type;
                })
                ->editColumn('qty_in', function ($row) {
                    $qty = (float) ($row->qty_in ?? 0);
                    return '<span data-is_quantity="true" class="display_currency qty_in" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
                })
                ->editColumn('qty_out', function ($row) {
                    $qty = (float) ($row->qty_out ?? 0);
                    return '<span data-is_quantity="true" class="display_currency qty_out" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
                })
                ->editColumn('exp_date', function ($row) {
                    if (! empty($row->exp_date)) {
                        return $this->util->format_date($row->exp_date);
                    }
                    return '--';
                })
                ->rawColumns(['qty_in', 'qty_out', 'ref_no'])
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

        $stockQty = DB::table('purchase_lines as pl')
            ->leftJoin('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->leftJoin('transaction_sell_lines_purchase_lines as tspl', 'pl.id', '=', 'tspl.purchase_line_id')
            ->where('pl.lot_number', $lot)
            ->where('t.business_id', $businessId)
            ->select(
                DB::raw('COALESCE(SUM(pl.quantity - pl.quantity_returned), 0) as total_purchased'),
                DB::raw("COALESCE(SUM(CASE WHEN tspl.sell_line_id IS NOT NULL THEN (tspl.quantity - tspl.qty_returned) ELSE 0 END), 0) as total_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN tspl.stock_adjustment_line_id IS NOT NULL THEN tspl.quantity ELSE 0 END), 0) as total_adjusted")
            )
            ->first();

        $currentStock = ($stockQty->total_purchased ?? 0) - ($stockQty->total_sold ?? 0) - ($stockQty->total_adjusted ?? 0);

        return view('smartstockinventory::lot.history', compact('business_locations', 'lot', 'lotInfo', 'currentStock'));
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

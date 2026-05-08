<?php

namespace Modules\ManageLot\Http\Controllers;

use App\PurchaseLine;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ManageLotController extends Controller
{
    protected ProductUtil $productUtil;
    protected TransactionUtil $transactionUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    private function authorizeViewOnly(): void
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Main list page (report/view only)
     */
    public function index(Request $request)
    {
        $this->authorizeViewOnly();

        return view('manage_lot::index');
    }

    /**
     * Select2 lot search endpoint (GET only, view/report).
     * Returns purchase_lines.id as the lot identifier (lot_id).
     */
    public function lotSearch(Request $request)
    {
        $this->authorizeViewOnly();

        $business_id = $request->session()->get('user.business_id');
        $permitted_locations = auth()->user()->permitted_locations();

        $term = $request->input('term');

        $query = PurchaseLine::join('transactions as tp', 'purchase_lines.transaction_id', '=', 'tp.id')
            ->join('products as p', 'purchase_lines.product_id', '=', 'p.id')
            ->join('variations as v', 'purchase_lines.variation_id', '=', 'v.id')
            ->leftJoin('business_locations as bl', 'tp.location_id', '=', 'bl.id')
            ->where('tp.business_id', $business_id)
            ->whereNotNull('purchase_lines.lot_number');

        if ($permitted_locations !== 'all') {
            $query->whereIn('tp.location_id', $permitted_locations);
        }

        if (! empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('purchase_lines.lot_number', 'like', '%' . $term . '%')
                    ->orWhere('p.name', 'like', '%' . $term . '%')
                    ->orWhere('p.sku', 'like', '%' . $term . '%')
                    ->orWhere('v.sub_sku', 'like', '%' . $term . '%');
            });
        }

        $results = $query
            ->orderBy('tp.transaction_date', 'desc')
            ->limit(20)
            ->get([
                'purchase_lines.id as id',
                'purchase_lines.lot_number',
                'purchase_lines.exp_date',
                'p.name as product_name',
                'v.name as variation_name',
                'v.sub_sku as sub_sku',
                'bl.name as location_name',
            ])->map(function ($row) {
                $product = $row->product_name;
                if (! empty($row->variation_name) && $row->variation_name !== 'DUMMY') {
                    $product .= ' (' . $row->variation_name . ')';
                }

                $text = trim((string) $row->lot_number) . ' - ' . $product;
                if (! empty($row->sub_sku)) {
                    $text .= ' [' . $row->sub_sku . ']';
                }
                if (! empty($row->location_name)) {
                    $text .= ' @ ' . $row->location_name;
                }
                if (! empty($row->exp_date)) {
                    $text .= ' | ' . $this->productUtil->format_date($row->exp_date);
                }

                return [
                    'id' => $row->id,
                    'text' => $text,
                ];
            })->values();

        return response()->json($results);
    }

    /**
     * Server-side DataTable for lots by location (report/view only)
     */
    public function indexData(Request $request)
    {
        $this->authorizeViewOnly();

        $business_id = $request->session()->get('user.business_id');
        $permitted_locations = auth()->user()->permitted_locations();

        $location_id = $request->input('location_id');
        $lot_number = $request->input('lot_number');
        $product_id = $request->input('product_id');
        $transaction_type = $request->input('transaction_type'); // optional (kept for API compatibility)
        $start_date = $request->input('start_date'); // optional
        $end_date = $request->input('end_date'); // optional

        // Default behavior: DO NOT load "all lots" on initial page load.
        // Require at least lot_number or product_id to be present.
        if (empty($lot_number) && empty($product_id)) {
            return DataTables::of(collect())->make(true);
        }

        $apply_where_date = function ($query, $field) use ($start_date, $end_date) {
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(' . $field . ')'), [$start_date, $end_date]);
            }
        };

        // Purchases define the lot identity (purchase_lines.id)
        $purchase_base = DB::table('purchase_lines as pl')
            ->join('transactions as tp', 'pl.transaction_id', '=', 'tp.id')
            ->where('tp.business_id', $business_id)
            ->whereNotNull('pl.lot_number');

        // Respect permitted locations
        if ($permitted_locations !== 'all') {
            $purchase_base->whereIn('tp.location_id', $permitted_locations);
        }

        if (! empty($location_id)) {
            $purchase_base->where('tp.location_id', $location_id);
        }
        if (! empty($lot_number)) {
            $purchase_base->where('pl.lot_number', 'like', '%' . $lot_number . '%');
        }
        if (! empty($product_id)) {
            $purchase_base->where('pl.product_id', $product_id);
        }

        $apply_where_date($purchase_base, 'tp.transaction_date');

        // --- Aggregations (all READ ONLY) ---
        // Sold qty (sell)
        $sold = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
            ->join('transactions as ts', 'tsl.transaction_id', '=', 'ts.id')
            ->where('ts.business_id', $business_id)
            ->where('ts.type', 'sell');
        if ($permitted_locations !== 'all') {
            $sold->whereIn('ts.location_id', $permitted_locations);
        }
        if (! empty($location_id)) {
            $sold->where('ts.location_id', $location_id);
        }
        $apply_where_date($sold, 'ts.transaction_date');
        $sold = $sold->groupBy('tspl.purchase_line_id', 'ts.location_id')
            ->select([
                'tspl.purchase_line_id',
                DB::raw('ts.location_id as location_id'),
                DB::raw('SUM(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as sold_qty'),
            ]);

        // Stock adjustment qty (out only, UltimatePOS decreases quantity)
        $adjustments = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('stock_adjustment_lines as sal', 'tspl.stock_adjustment_line_id', '=', 'sal.id')
            ->join('transactions as ta', 'sal.transaction_id', '=', 'ta.id')
            ->where('ta.business_id', $business_id)
            ->where('ta.type', 'stock_adjustment');
        if ($permitted_locations !== 'all') {
            $adjustments->whereIn('ta.location_id', $permitted_locations);
        }
        if (! empty($location_id)) {
            $adjustments->where('ta.location_id', $location_id);
        }
        $apply_where_date($adjustments, 'ta.transaction_date');
        $adjustments = $adjustments->groupBy('tspl.purchase_line_id', 'ta.location_id')
            ->select([
                'tspl.purchase_line_id',
                DB::raw('ta.location_id as location_id'),
                DB::raw('SUM(COALESCE(tspl.quantity,0)) as adjustment_qty'),
            ]);

        // Transfers: derive from sell_transfer (out) + its linked purchase_transfer (in)
        $transfers = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
            ->join('transactions as tt_out', 'tsl.transaction_id', '=', 'tt_out.id')
            ->leftJoin('transactions as tt_in', function ($join) {
                $join->on('tt_in.transfer_parent_id', '=', 'tt_out.id')
                    ->where('tt_in.type', '=', 'purchase_transfer');
            })
            ->where('tt_out.type', 'sell_transfer');

        // business & location restrictions (outgoing transaction owns the mapping)
        $transfers->where('tt_out.business_id', $business_id);
        if ($permitted_locations !== 'all') {
            $transfers->whereIn('tt_out.location_id', $permitted_locations);
        }
        if (! empty($location_id)) {
            // Show any transfer that impacts this location (from OR to)
            $transfers->where(function ($q) use ($location_id) {
                $q->where('tt_out.location_id', $location_id)
                    ->orWhere('tt_in.location_id', $location_id);
            });
        }
        $apply_where_date($transfers, 'tt_out.transaction_date');

        $transfer_out = (clone $transfers)
            ->groupBy('tspl.purchase_line_id', 'tt_out.location_id')
            ->select([
                'tspl.purchase_line_id',
                DB::raw('tt_out.location_id as location_id'),
                DB::raw('SUM(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as transfer_out_qty'),
            ]);

        $transfer_in = (clone $transfers)
            ->whereNotNull('tt_in.location_id')
            ->groupBy('tspl.purchase_line_id', 'tt_in.location_id')
            ->select([
                'tspl.purchase_line_id',
                DB::raw('tt_in.location_id as location_id'),
                DB::raw('SUM(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as transfer_in_qty'),
            ]);

        // Build base rows: one per purchase_line per location present in any movement.
        // We start with purchase location, and then union in other locations from transfers/sells/adjustments.
        $purchase_locations = (clone $purchase_base)
            ->select([
                DB::raw('pl.id as purchase_line_id'),
                DB::raw('tp.location_id as location_id'),
            ])
            ->groupBy('pl.id', 'tp.location_id');

        // IMPORTANT: Keep location_id fully-qualified inside each subquery (do not override select with plain `location_id`)
        // to avoid MySQL "Column 'location_id' ... is ambiguous" errors (e.g. transfers join both out/in transactions).
        $sold_locations = (clone $sold)->select([
            'tspl.purchase_line_id as purchase_line_id',
            DB::raw('ts.location_id as location_id'),
        ]);
        $adjustment_locations = (clone $adjustments)->select([
            'tspl.purchase_line_id as purchase_line_id',
            DB::raw('ta.location_id as location_id'),
        ]);
        $transfer_out_locations = (clone $transfer_out)->select([
            'tspl.purchase_line_id as purchase_line_id',
            DB::raw('tt_out.location_id as location_id'),
        ]);
        $transfer_in_locations = (clone $transfer_in)->select([
            'tspl.purchase_line_id as purchase_line_id',
            DB::raw('tt_in.location_id as location_id'),
        ]);

        $movement_locations = DB::query()->fromSub(
            $purchase_locations
                ->unionAll($sold_locations)
                ->unionAll($adjustment_locations)
                ->unionAll($transfer_out_locations)
                ->unionAll($transfer_in_locations),
            'lot_locations'
        );

        $movement_locations = DB::query()
            ->fromSub($movement_locations, 'll')
            ->select([
                'll.purchase_line_id',
                'll.location_id',
            ])
            ->groupBy('ll.purchase_line_id', 'll.location_id');

        $lots = DB::query()
            ->fromSub($movement_locations, 'll')
            ->join('purchase_lines as pl', 'll.purchase_line_id', '=', 'pl.id')
            ->join('transactions as tp', 'pl.transaction_id', '=', 'tp.id')
            ->join('products as p', 'pl.product_id', '=', 'p.id')
            ->join('variations as v', 'pl.variation_id', '=', 'v.id')
            ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('contacts as supplier', 'tp.contact_id', '=', 'supplier.id')
            ->leftJoin('business_locations as bl', 'll.location_id', '=', 'bl.id')
            ->leftJoinSub($sold, 'sold', function ($join) {
                $join->on('sold.purchase_line_id', '=', 'pl.id')
                    ->on('sold.location_id', '=', 'll.location_id');
            })
            ->leftJoinSub($adjustments, 'adj', function ($join) {
                $join->on('adj.purchase_line_id', '=', 'pl.id')
                    ->on('adj.location_id', '=', 'll.location_id');
            })
            ->leftJoinSub($transfer_out, 't_out', function ($join) {
                $join->on('t_out.purchase_line_id', '=', 'pl.id')
                    ->on('t_out.location_id', '=', 'll.location_id');
            })
            ->leftJoinSub($transfer_in, 't_in', function ($join) {
                $join->on('t_in.purchase_line_id', '=', 'pl.id')
                    ->on('t_in.location_id', '=', 'll.location_id');
            })
            ->where('tp.business_id', $business_id)
            ->whereNotNull('pl.lot_number');

        // Filter by transaction type (for reporting visibility only; does not change quantities)
        if (! empty($transaction_type) && $transaction_type !== 'all') {
            if ($transaction_type === 'purchase') {
                // only show purchase-location rows
                $lots->whereColumn('tp.location_id', 'll.location_id');
            } elseif ($transaction_type === 'sell') {
                $lots->whereRaw('COALESCE(sold.sold_qty,0) > 0');
            } elseif ($transaction_type === 'transfer') {
                $lots->whereRaw('(COALESCE(t_out.transfer_out_qty,0) > 0 OR COALESCE(t_in.transfer_in_qty,0) > 0)');
            } elseif ($transaction_type === 'adjustment') {
                $lots->whereRaw('COALESCE(adj.adjustment_qty,0) > 0');
            }
        }

            $lots = $lots->select([
                DB::raw('pl.id as lot_id'),
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                DB::raw('p.sku as product_sku'),
                DB::raw('v.sub_sku as sku'),
                DB::raw('v.name as variation'),
                DB::raw('pl.lot_number as lot_number'),
                DB::raw('pl.exp_date as exp_date'),
                DB::raw('tp.transaction_date as purchase_date'),
                DB::raw("COALESCE(NULLIF(supplier.supplier_business_name,''), supplier.name, '') as supplier"),
                DB::raw('ll.location_id as location_id'),
                DB::raw('bl.name as location_name'),
                DB::raw('(COALESCE(pl.quantity,0) - COALESCE(pl.quantity_returned,0)) as purchase_qty'),
                DB::raw('COALESCE(sold.sold_qty,0) as sold_qty'),
                DB::raw('COALESCE(t_out.transfer_out_qty,0) as transfer_out_qty'),
                DB::raw('COALESCE(t_in.transfer_in_qty,0) as transfer_in_qty'),
                DB::raw('(COALESCE(t_out.transfer_out_qty,0) - COALESCE(t_in.transfer_in_qty,0)) as transfer_qty'),
                DB::raw('COALESCE(adj.adjustment_qty,0) as adjustment_qty'),
                DB::raw('((COALESCE(pl.quantity,0) - COALESCE(pl.quantity_returned,0)) + COALESCE(t_in.transfer_in_qty,0) - (COALESCE(sold.sold_qty,0) + COALESCE(t_out.transfer_out_qty,0) + COALESCE(adj.adjustment_qty,0))) as current_qty'),
                DB::raw('u.short_name as unit'),
            ]);

        return DataTables::of($lots)
            ->filterColumn('product', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('p.name', 'like', '%' . $keyword . '%')
                        ->orWhere('v.name', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('supplier', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('supplier.name', 'like', '%' . $keyword . '%')
                        ->orWhere('supplier.supplier_business_name', 'like', '%' . $keyword . '%');
                });
            })
            ->addColumn('action', function ($row) {
                $url = action([ManageLotController::class, 'history'], [$row->lot_id]);
                return '<a class="btn btn-xs btn-primary" href="' . e($url) . '"><i class="fa fa-eye"></i> ' . __('messages.view') . '</a>';
            })
            ->editColumn('purchase_qty', function ($row) {
                $qty = (float) $row->purchase_qty;
                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
            })
            ->editColumn('sold_qty', function ($row) {
                $qty = (float) $row->sold_qty;
                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
            })
            ->editColumn('transfer_out_qty', function ($row) {
                $qty = (float) $row->transfer_qty;
                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
            })
            ->editColumn('adjustment_qty', function ($row) {
                $qty = (float) $row->adjustment_qty;
                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
            })
            ->editColumn('current_qty', function ($row) {
                $qty = (float) $row->current_qty;
                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false data-orig-value="' . $qty . '" data-unit="' . e($row->unit) . '">' . $qty . '</span> ' . e($row->unit);
            })
            ->editColumn('exp_date', function ($row) {
                return ! empty($row->exp_date) ? $this->productUtil->format_date($row->exp_date) : '--';
            })
            ->editColumn('purchase_date', function ($row) {
                return ! empty($row->purchase_date) ? $this->productUtil->format_date($row->purchase_date, true) : '--';
            })
            ->rawColumns(['action', 'purchase_qty', 'sold_qty', 'transfer_out_qty', 'adjustment_qty', 'current_qty'])
            ->make(true);
    }

    /**
     * History page for a specific lot (purchase_lines.id)
     */
    public function history(Request $request, int $lot_id)
    {
        $this->authorizeViewOnly();

        $business_id = $request->session()->get('user.business_id');

        $lot = PurchaseLine::join('transactions as tp', 'purchase_lines.transaction_id', '=', 'tp.id')
            ->join('products as p', 'purchase_lines.product_id', '=', 'p.id')
            ->join('variations as v', 'purchase_lines.variation_id', '=', 'v.id')
            ->where('tp.business_id', $business_id)
            ->where('purchase_lines.id', $lot_id)
            ->select([
                'purchase_lines.id as lot_id',
                'purchase_lines.lot_number',
                'purchase_lines.exp_date',
                DB::raw("CONCAT(p.name, IF(v.name != 'DUMMY', CONCAT(' (', v.name, ')'), '')) as product"),
                'v.sub_sku as sku',
            ])->firstOrFail();

        return view('manage_lot::history')->with(compact('lot'));
    }

    /**
     * History DataTable (array-based to compute running balance safely)
     */
    public function historyData(Request $request, int $lot_id)
    {
        $this->authorizeViewOnly();

        $business_id = $request->session()->get('user.business_id');
        $permitted_locations = auth()->user()->permitted_locations();

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        $apply_where_date = function ($query, $field) use ($start_date, $end_date) {
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(' . $field . ')'), [$start_date, $end_date]);
            }
        };

        // Purchase (qty in at purchase location)
        $purchase = DB::table('purchase_lines as pl')
            ->join('transactions as tp', 'pl.transaction_id', '=', 'tp.id')
            ->leftJoin('business_locations as bl', 'tp.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 'tp.created_by', '=', 'u.id')
            ->where('tp.business_id', $business_id)
            ->where('pl.id', $lot_id);
        if ($permitted_locations !== 'all') {
            $purchase->whereIn('tp.location_id', $permitted_locations);
        }
        $apply_where_date($purchase, 'tp.transaction_date');
        $purchase = $purchase->select([
            DB::raw('tp.transaction_date as movement_date'),
            DB::raw("'purchase' as movement_type"),
            DB::raw("COALESCE(tp.ref_no, tp.invoice_no, '') as ref_no"),
            DB::raw('bl.name as from_location'),
            DB::raw("'' as to_location"),
            DB::raw('(COALESCE(pl.quantity,0) - COALESCE(pl.quantity_returned,0)) as qty_in'),
            DB::raw('0 as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Sell (qty out at sell location)
        $sell = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
            ->join('transactions as ts', 'tsl.transaction_id', '=', 'ts.id')
            ->leftJoin('business_locations as bl', 'ts.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 'ts.created_by', '=', 'u.id')
            ->where('ts.business_id', $business_id)
            ->where('ts.type', 'sell')
            ->where('tspl.purchase_line_id', $lot_id);
        if ($permitted_locations !== 'all') {
            $sell->whereIn('ts.location_id', $permitted_locations);
        }
        $apply_where_date($sell, 'ts.transaction_date');
        $sell = $sell->select([
            DB::raw('ts.transaction_date as movement_date'),
            DB::raw("'sell' as movement_type"),
            DB::raw("COALESCE(ts.invoice_no, ts.ref_no, '') as ref_no"),
            DB::raw('bl.name as from_location'),
            DB::raw("'' as to_location"),
            DB::raw('0 as qty_in'),
            DB::raw('(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Sell fallback (when mapping rows do not exist): transaction_sell_lines.lot_no_line_id
        $sell_fallback = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as ts', 'tsl.transaction_id', '=', 'ts.id')
            ->leftJoin('business_locations as bl', 'ts.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 'ts.created_by', '=', 'u.id')
            ->where('ts.business_id', $business_id)
            ->where('ts.type', 'sell')
            ->where('tsl.lot_no_line_id', $lot_id)
            ->whereNotExists(function ($q) use ($lot_id) {
                $q->select(DB::raw(1))
                    ->from('transaction_sell_lines_purchase_lines as tspl2')
                    ->whereColumn('tspl2.sell_line_id', 'tsl.id')
                    ->where('tspl2.purchase_line_id', $lot_id);
            });
        if ($permitted_locations !== 'all') {
            $sell_fallback->whereIn('ts.location_id', $permitted_locations);
        }
        $apply_where_date($sell_fallback, 'ts.transaction_date');
        $sell_fallback = $sell_fallback->select([
            DB::raw('ts.transaction_date as movement_date'),
            DB::raw("'sell' as movement_type"),
            DB::raw("COALESCE(ts.invoice_no, ts.ref_no, '') as ref_no"),
            DB::raw('bl.name as from_location'),
            DB::raw("'' as to_location"),
            DB::raw('0 as qty_in'),
            DB::raw('COALESCE(tsl.quantity,0) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Transfer OUT (qty out at from location, to_location resolved)
        $transfer_out = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
            ->join('transactions as tt_out', 'tsl.transaction_id', '=', 'tt_out.id')
            ->leftJoin('transactions as tt_in', function ($join) {
                $join->on('tt_in.transfer_parent_id', '=', 'tt_out.id')
                    ->where('tt_in.type', '=', 'purchase_transfer');
            })
            ->leftJoin('business_locations as bl_from', 'tt_out.location_id', '=', 'bl_from.id')
            ->leftJoin('business_locations as bl_to', 'tt_in.location_id', '=', 'bl_to.id')
            ->leftJoin('users as u', 'tt_out.created_by', '=', 'u.id')
            ->where('tt_out.business_id', $business_id)
            ->where('tt_out.type', 'sell_transfer')
            ->where('tspl.purchase_line_id', $lot_id);
        if ($permitted_locations !== 'all') {
            $transfer_out->whereIn('tt_out.location_id', $permitted_locations);
        }
        $apply_where_date($transfer_out, 'tt_out.transaction_date');
        $transfer_out = $transfer_out->select([
            DB::raw('tt_out.transaction_date as movement_date'),
            DB::raw("'transfer_out' as movement_type"),
            DB::raw("COALESCE(tt_out.ref_no, '') as ref_no"),
            DB::raw('bl_from.name as from_location'),
            DB::raw('bl_to.name as to_location'),
            DB::raw('0 as qty_in'),
            DB::raw('(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Transfer OUT fallback (when mapping rows do not exist): transaction_sell_lines.lot_no_line_id
        $transfer_out_fallback = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as tt_out', 'tsl.transaction_id', '=', 'tt_out.id')
            ->leftJoin('transactions as tt_in', function ($join) {
                $join->on('tt_in.transfer_parent_id', '=', 'tt_out.id')
                    ->where('tt_in.type', '=', 'purchase_transfer');
            })
            ->leftJoin('business_locations as bl_from', 'tt_out.location_id', '=', 'bl_from.id')
            ->leftJoin('business_locations as bl_to', 'tt_in.location_id', '=', 'bl_to.id')
            ->leftJoin('users as u', 'tt_out.created_by', '=', 'u.id')
            ->where('tt_out.business_id', $business_id)
            ->where('tt_out.type', 'sell_transfer')
            ->where('tsl.lot_no_line_id', $lot_id)
            ->whereNotExists(function ($q) use ($lot_id) {
                $q->select(DB::raw(1))
                    ->from('transaction_sell_lines_purchase_lines as tspl2')
                    ->whereColumn('tspl2.sell_line_id', 'tsl.id')
                    ->where('tspl2.purchase_line_id', $lot_id);
            });
        if ($permitted_locations !== 'all') {
            $transfer_out_fallback->whereIn('tt_out.location_id', $permitted_locations);
        }
        $apply_where_date($transfer_out_fallback, 'tt_out.transaction_date');
        $transfer_out_fallback = $transfer_out_fallback->select([
            DB::raw('tt_out.transaction_date as movement_date'),
            DB::raw("'transfer_out' as movement_type"),
            DB::raw("COALESCE(tt_out.ref_no, '') as ref_no"),
            DB::raw('bl_from.name as from_location'),
            DB::raw('bl_to.name as to_location'),
            DB::raw('0 as qty_in'),
            DB::raw('COALESCE(tsl.quantity,0) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Transfer IN (mirror of transfer out)
        $transfer_in = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('transaction_sell_lines as tsl', 'tspl.sell_line_id', '=', 'tsl.id')
            ->join('transactions as tt_out', 'tsl.transaction_id', '=', 'tt_out.id')
            ->join('transactions as tt_in', function ($join) {
                $join->on('tt_in.transfer_parent_id', '=', 'tt_out.id')
                    ->where('tt_in.type', '=', 'purchase_transfer');
            })
            ->leftJoin('business_locations as bl_from', 'tt_out.location_id', '=', 'bl_from.id')
            ->leftJoin('business_locations as bl_to', 'tt_in.location_id', '=', 'bl_to.id')
            ->leftJoin('users as u', 'tt_in.created_by', '=', 'u.id')
            ->where('tt_out.business_id', $business_id)
            ->where('tt_out.type', 'sell_transfer')
            ->where('tspl.purchase_line_id', $lot_id);
        if ($permitted_locations !== 'all') {
            $transfer_in->whereIn('tt_in.location_id', $permitted_locations);
        }
        $apply_where_date($transfer_in, 'tt_in.transaction_date');
        $transfer_in = $transfer_in->select([
            DB::raw('tt_in.transaction_date as movement_date'),
            DB::raw("'transfer_in' as movement_type"),
            DB::raw("COALESCE(tt_in.ref_no, '') as ref_no"),
            DB::raw('bl_from.name as from_location'),
            DB::raw('bl_to.name as to_location'),
            DB::raw('(COALESCE(tspl.quantity,0) - COALESCE(tspl.qty_returned,0)) as qty_in'),
            DB::raw('0 as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Adjustment (qty out)
        $adjustment = DB::table('transaction_sell_lines_purchase_lines as tspl')
            ->join('stock_adjustment_lines as sal', 'tspl.stock_adjustment_line_id', '=', 'sal.id')
            ->join('transactions as ta', 'sal.transaction_id', '=', 'ta.id')
            ->leftJoin('business_locations as bl', 'ta.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 'ta.created_by', '=', 'u.id')
            ->where('ta.business_id', $business_id)
            ->where('ta.type', 'stock_adjustment')
            ->where('tspl.purchase_line_id', $lot_id);
        if ($permitted_locations !== 'all') {
            $adjustment->whereIn('ta.location_id', $permitted_locations);
        }
        $apply_where_date($adjustment, 'ta.transaction_date');
        $adjustment = $adjustment->select([
            DB::raw('ta.transaction_date as movement_date'),
            DB::raw("'adjustment' as movement_type"),
            DB::raw("COALESCE(ta.ref_no, '') as ref_no"),
            DB::raw('bl.name as from_location'),
            DB::raw("'' as to_location"),
            DB::raw('0 as qty_in'),
            DB::raw('COALESCE(tspl.quantity,0) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        // Adjustment fallback: stock_adjustment_lines.lot_no_line_id
        $adjustment_fallback = DB::table('stock_adjustment_lines as sal')
            ->join('transactions as ta', 'sal.transaction_id', '=', 'ta.id')
            ->leftJoin('business_locations as bl', 'ta.location_id', '=', 'bl.id')
            ->leftJoin('users as u', 'ta.created_by', '=', 'u.id')
            ->where('ta.business_id', $business_id)
            ->where('ta.type', 'stock_adjustment')
            ->where('sal.lot_no_line_id', $lot_id)
            ->whereNotExists(function ($q) use ($lot_id) {
                $q->select(DB::raw(1))
                    ->from('transaction_sell_lines_purchase_lines as tspl2')
                    ->whereColumn('tspl2.stock_adjustment_line_id', 'sal.id')
                    ->where('tspl2.purchase_line_id', $lot_id);
            });
        if ($permitted_locations !== 'all') {
            $adjustment_fallback->whereIn('ta.location_id', $permitted_locations);
        }
        $apply_where_date($adjustment_fallback, 'ta.transaction_date');
        $adjustment_fallback = $adjustment_fallback->select([
            DB::raw('ta.transaction_date as movement_date'),
            DB::raw("'adjustment' as movement_type"),
            DB::raw("COALESCE(ta.ref_no, '') as ref_no"),
            DB::raw('bl.name as from_location'),
            DB::raw("'' as to_location"),
            DB::raw('0 as qty_in'),
            DB::raw('COALESCE(sal.quantity,0) as qty_out'),
            DB::raw("COALESCE(CONCAT(u.surname, ' ', u.first_name), u.username, '') as created_by"),
        ]);

        $union = $purchase
            ->unionAll($sell)
            ->unionAll($sell_fallback)
            ->unionAll($transfer_out)
            ->unionAll($transfer_out_fallback)
            ->unionAll($transfer_in)
            ->unionAll($adjustment);
        $union = $union->unionAll($adjustment_fallback);

        $rows = DB::query()
            ->fromSub($union, 'm')
            ->orderBy('movement_date', 'asc')
            ->get();

        $balance = 0.0;
        $data = [];
        foreach ($rows as $row) {
            $qty_in = (float) ($row->qty_in ?? 0);
            $qty_out = (float) ($row->qty_out ?? 0);
            $balance += ($qty_in - $qty_out);

            $data[] = [
                'movement_date' => $this->productUtil->format_date($row->movement_date, true),
                'movement_type' => $this->formatMovementType((string) $row->movement_type),
                'ref_no' => $row->ref_no,
                'from_location' => $row->from_location ?: '--',
                'to_location' => $row->to_location ?: '--',
                'qty_in' => $qty_in,
                'qty_out' => $qty_out,
                'balance_qty' => $balance,
                'created_by' => $row->created_by ?: '--',
            ];
        }

        return DataTables::of($data)->make(true);
    }

    private function formatMovementType(string $movement_type): string
    {
        return match ($movement_type) {
            'purchase' => __('purchase.purchase'),
            'sell' => __('sale.sale'),
            'transfer_out' => __('lang_v1.stock_transfer') . ' (' . __('lang_v1.out') . ')',
            'transfer_in' => __('lang_v1.stock_transfer') . ' (' . __('lang_v1.in') . ')',
            'adjustment' => __('stock_adjustment.stock_adjustment'),
            default => $movement_type,
        };
    }
}

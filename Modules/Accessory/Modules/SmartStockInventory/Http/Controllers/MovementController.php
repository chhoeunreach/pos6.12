<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Exports\ArrayExport;
use App\BusinessLocation;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MovementController extends BaseSmartStockController
{
    protected ProductUtil $productUtil;
    protected TransactionUtil $transactionUtil;

    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $selectedLocationId = (int) $request->input('location_id', 0);
        $skuFilter = trim((string) $request->input('sku', ''));
        $productFilter = trim((string) $request->input('product_name', ''));
        $lotFilter = trim((string) $request->input('lot_number', ''));
        $selectedSku = $skuFilter;
        $resolvedVariationIdFromSku = 0;
        if ($selectedLocationId > 0 && ! in_array($selectedLocationId, $permittedLocationIds, true)) {
            abort(403, 'Unauthorized location.');
        }
        if (! $request->filled('variation_id') && $selectedLocationId > 0 && $skuFilter !== '') {
            $resolvedVariationIdFromSku = (int) DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
                ->where('p.business_id', $businessId)
                ->where('v.sub_sku', $skuFilter)
                ->where('vld.location_id', $selectedLocationId)
                ->value('v.id');
        }
        $stockSummary = null;
        $mode = (string) $request->input('mode', '');
        if ($mode === 'pending_transfer') {
            $rowsQuery = DB::table('transactions as t')
                ->leftJoin('business_locations as bl_from', 'bl_from.id', '=', 't.location_id')
                ->leftJoin('transactions as t_in', function ($join) {
                    $join->on('t_in.transfer_parent_id', '=', 't.id')
                        ->where('t_in.type', '=', 'purchase_transfer');
                })
                ->leftJoin('business_locations as bl_to', 'bl_to.id', '=', 't_in.location_id')
                ->leftJoin('users as u', 'u.id', '=', 't.created_by')
                ->where('t.business_id', $businessId)
                ->where('t.type', 'sell_transfer')
                ->where('t.status', '!=', 'final');
            if ($selectedLocationId > 0) {
                $rowsQuery->where(function ($q) use ($selectedLocationId) {
                    $q->where('t.location_id', $selectedLocationId)
                        ->orWhere('t_in.location_id', $selectedLocationId);
                });
            }
            $rows = $rowsQuery
                ->select(
                    't.id as transaction_id',
                    't.status as transaction_status',
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
            if (($request->filled('variation_id') || $resolvedVariationIdFromSku > 0) && $selectedLocationId > 0) {
                $variationId = (int) ($request->input('variation_id') ?: $resolvedVariationIdFromSku);
                $locationId = $selectedLocationId;
                $stockSummary = $this->productUtil->getVariationStockDetails($businessId, $variationId, $locationId);
                $meta = DB::table('variations as v')
                    ->join('products as p', 'p.id', '=', 'v.product_id')
                    ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
                    ->leftJoin('business_locations as bl', 'bl.id', '=', DB::raw((int) $locationId))
                    ->where('v.id', $variationId)
                    ->select(
                        'v.sub_sku as sku',
                        'p.name as product_name',
                        'pv.name as product_variation_name',
                        'v.name as variation_name',
                        'bl.name as location_name'
                    )
                    ->first();

                $resolvedProductName = trim(implode(' - ', array_filter([
                    $meta->product_name ?? null,
                    (($meta->product_variation_name ?? '') !== 'DUMMY') ? ($meta->product_variation_name ?? null) : null,
                    (($meta->variation_name ?? '') !== 'DUMMY') ? ($meta->variation_name ?? null) : null,
                ])));
                if ($resolvedProductName === '') {
                    $resolvedProductName = $request->input('product', '');
                }
                if ($selectedSku === '' && ! empty($meta->sku)) {
                    $selectedSku = (string) $meta->sku;
                }

                $history = $this->productUtil->getVariationStockHistory($businessId, $variationId, $locationId);
                $transferRows = collect($history)->filter(function ($line) {
                    return in_array((string) ($line['type'] ?? ''), ['sell_transfer', 'purchase_transfer'], true)
                        && ! empty($line['transaction_id']);
                })->values();
                $historyTxIds = collect($history)
                    ->pluck('transaction_id')
                    ->filter(fn ($id) => ! empty($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $createdByMap = [];
                $txMetaMap = [];
                if (! empty($historyTxIds)) {
                    $txMetaRows = DB::table('transactions as t')
                        ->leftJoin('users as u', 'u.id', '=', 't.created_by')
                        ->whereIn('t.id', $historyTxIds)
                        ->select(
                            't.id',
                            't.return_parent_id',
                            DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as full_name"),
                            'u.username'
                        )
                        ->get();
                    $createdByMap = $txMetaRows
                        ->mapWithKeys(function ($row) {
                            $name = trim((string) ($row->full_name ?? ''));
                            if ($name === '') {
                                $name = (string) ($row->username ?? '');
                            }
                            return [(int) $row->id => $name];
                        })
                        ->toArray();
                    $txMetaMap = $txMetaRows
                        ->mapWithKeys(fn ($row) => [(int) $row->id => ['return_parent_id' => (int) ($row->return_parent_id ?? 0)]])
                        ->toArray();
                }
                $transferTxIds = $transferRows->pluck('transaction_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
                $transferMap = [];
                if (! empty($transferTxIds)) {
                    $transferData = DB::table('transactions as t')
                        ->leftJoin('transactions as parent', 'parent.id', '=', 't.transfer_parent_id')
                        ->leftJoin('transactions as child', function ($join) {
                            $join->on('child.transfer_parent_id', '=', 't.id')
                                ->where('child.type', '=', 'purchase_transfer');
                        })
                        ->whereIn('t.id', $transferTxIds)
                        ->select(
                            't.id',
                            't.type',
                            't.location_id as this_location_id',
                            'parent.id as parent_transaction_id',
                            'parent.location_id as parent_location_id',
                            'child.location_id as child_location_id'
                        )
                        ->get();
                    foreach ($transferData as $tr) {
                        $outId = null;
                        $inId = null;
                        if ($tr->type === 'sell_transfer') {
                            $outId = $tr->this_location_id;
                            $inId = $tr->child_location_id;
                        } elseif ($tr->type === 'purchase_transfer') {
                            $outId = $tr->parent_location_id;
                            $inId = $tr->this_location_id;
                        }
                        $actionTransactionId = (int) $tr->id;
                        if ($tr->type === 'purchase_transfer' && ! empty($tr->parent_transaction_id)) {
                            $actionTransactionId = (int) $tr->parent_transaction_id;
                        }
                        $transferMap[(int) $tr->id] = [
                            'out_id' => $outId,
                            'in_id' => $inId,
                            'action_transaction_id' => $actionTransactionId,
                        ];
                    }
                }

                $transferLocationIds = collect($transferMap)->flatMap(function ($pair) {
                    return [(int) ($pair['out_id'] ?? 0), (int) ($pair['in_id'] ?? 0)];
                })->filter(fn ($id) => $id > 0)->unique()->values()->all();
                $locationNameMap = [];
                if (! empty($transferLocationIds)) {
                    $locationNameMap = DB::table('business_locations')
                        ->whereIn('id', $transferLocationIds)
                        ->pluck('name', 'id')
                        ->toArray();
                }

                $page = LengthAwarePaginator::resolveCurrentPage() ?: 1;
                $perPage = 100;
                $items = collect($history)->map(function ($line) use ($locationId, $meta, $resolvedProductName, $transferMap, $locationNameMap, $createdByMap, $txMetaMap) {
                    $qtyIn = (float) ($line['quantity_change'] ?? 0) > 0 ? (float) $line['quantity_change'] : 0;
                    $qtyOut = (float) ($line['quantity_change'] ?? 0) < 0 ? abs((float) $line['quantity_change']) : 0;
                    $type = (string) ($line['type'] ?? '');
                    $baseLocationName = $meta->location_name ?? null;
                    $locationName = $baseLocationName;
                    $transactionId = (int) ($line['transaction_id'] ?? 0);
                    $actionTransactionId = $transactionId;
                    $editTransactionId = $transactionId;
                    if (in_array($type, ['sell_transfer', 'purchase_transfer'], true) && isset($transferMap[$transactionId])) {
                        $outId = (int) ($transferMap[$transactionId]['out_id'] ?? 0);
                        $inId = (int) ($transferMap[$transactionId]['in_id'] ?? 0);
                        $actionTransactionId = (int) ($transferMap[$transactionId]['action_transaction_id'] ?? $transactionId);
                        // Stock transfer edit expects the parent sell_transfer transaction id.
                        $editTransactionId = $actionTransactionId;
                        $outName = $outId > 0 ? ($locationNameMap[$outId] ?? null) : null;
                        $inName = $inId > 0 ? ($locationNameMap[$inId] ?? null) : null;
                        if (! empty($outName) && ! empty($inName)) {
                            $locationName = 'Out: ' . $outName . ' -> In: ' . $inName;
                        } elseif (! empty($outName)) {
                            $locationName = 'Out: ' . $outName;
                        } elseif (! empty($inName)) {
                            $locationName = 'In: ' . $inName;
                        }
                    }
                    if (in_array($type, ['sell_return', 'purchase_return'], true) && isset($txMetaMap[$transactionId])) {
                        $parentId = (int) ($txMetaMap[$transactionId]['return_parent_id'] ?? 0);
                        if ($parentId > 0) {
                            $editTransactionId = $parentId;
                        }
                    }

                    return (object) [
                        'transaction_id' => (int) ($line['transaction_id'] ?? 0),
                        'action_transaction_id' => $actionTransactionId,
                        'edit_transaction_id' => $editTransactionId,
                        'transaction_status' => $line['status'] ?? null,
                        'movement_date' => $line['date'] ?? null,
                        'reference_no' => $line['ref_no'] ?? null,
                        'transaction_type' => $type,
                        'location_id' => $locationId,
                        'location_name' => $locationName,
                        'product_name' => $resolvedProductName,
                        'sku' => $meta->sku ?? null,
                        'imei' => null,
                        'lot_number' => null,
                        'qty_in' => $qtyIn,
                        'qty_out' => $qtyOut,
                        'balance_qty' => (float) ($line['stock'] ?? 0),
                        'created_by_name' => $createdByMap[$transactionId]
                            ?? ($line['contact_name'] ?? ($line['supplier_business_name'] ?? null)),
                    ];
                })->values();

                // Fallback: if stock-history source has no rows, use smart stock logs for the same variation/location.
                if ($items->isEmpty()) {
                    $items = DB::table('smart_stock_inventory_logs as l')
                        ->where(function ($q) use ($businessId) {
                            $q->where('l.business_id', $businessId)
                                ->orWhereNull('l.business_id');
                        })
                        ->where('l.variation_id', $variationId)
                        ->where('l.location_id', $locationId)
                        ->orderByDesc('l.movement_date')
                        ->get()
                        ->map(fn ($row) => (object) [
                            'transaction_id' => 0,
                            'action_transaction_id' => 0,
                            'edit_transaction_id' => 0,
                            'transaction_status' => null,
                            'movement_date' => $row->movement_date,
                            'reference_no' => $row->reference_no,
                            'transaction_type' => $row->transaction_type,
                            'location_id' => $row->location_id,
                            'location_name' => $row->location_name,
                            'product_name' => $row->product_name,
                            'sku' => $row->sku,
                            'imei' => $row->imei,
                            'lot_number' => $row->lot_number,
                            'qty_in' => (float) $row->qty_in,
                            'qty_out' => (float) $row->qty_out,
                            'balance_qty' => (float) $row->balance_qty,
                            'created_by_name' => $row->created_by_name,
                        ])
                        ->values();
                }

                $rows = new LengthAwarePaginator(
                    $items->forPage($page, $perPage)->values(),
                    $items->count(),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
                if ($skuFilter !== '') {
                    $filtered = collect($rows->items())->filter(function ($r) use ($skuFilter) {
                        return stripos((string) ($r->sku ?? ''), $skuFilter) !== false;
                    })->values();
                    $rows = new LengthAwarePaginator(
                        $filtered,
                        $filtered->count(),
                        $perPage,
                        1,
                        ['path' => $request->url(), 'query' => $request->query()]
                    );
                }
                if ($productFilter !== '') {
                    $filtered = collect($rows->items())->filter(function ($r) use ($productFilter) {
                        return stripos((string) ($r->product_name ?? ''), $productFilter) !== false;
                    })->values();
                    $rows = new LengthAwarePaginator(
                        $filtered,
                        $filtered->count(),
                        $perPage,
                        1,
                        ['path' => $request->url(), 'query' => $request->query()]
                    );
                }
                if ($lotFilter !== '') {
                    $filtered = collect($rows->items())->filter(function ($r) use ($lotFilter) {
                        return stripos((string) ($r->lot_number ?? ''), $lotFilter) !== false;
                    })->values();
                    $rows = new LengthAwarePaginator(
                        $filtered,
                        $filtered->count(),
                        $perPage,
                        1,
                        ['path' => $request->url(), 'query' => $request->query()]
                    );
                }
            } else {
                $rowsQuery = DB::table('smart_stock_inventory_logs as l')
                    ->where(function ($q) use ($businessId) {
                        $q->where('l.business_id', $businessId)
                            ->orWhereNull('l.business_id');
                    });
                if ($selectedLocationId > 0) {
                    $rowsQuery->where('l.location_id', $selectedLocationId);
                }
                if ($skuFilter !== '') {
                    $rowsQuery->where('l.sku', 'like', '%' . $skuFilter . '%');
                }
                if ($productFilter !== '') {
                    $rowsQuery->where('l.product_name', 'like', '%' . $productFilter . '%');
                }
                if ($lotFilter !== '') {
                    $rowsQuery->where('l.lot_number', 'like', '%' . $lotFilter . '%');
                }
                $rows = $rowsQuery
                    ->select('l.*', DB::raw('0 as transaction_id'), DB::raw('0 as action_transaction_id'), DB::raw('0 as edit_transaction_id'), DB::raw('NULL as transaction_status'))
                    ->orderByDesc('l.movement_date')
                    ->paginate(100);
            }
        }

        $locations = $this->locationOptions($businessId)->whereIn('id', $permittedLocationIds)->values();
        $users = DB::table('users')->where('business_id', $businessId)->pluck('username', 'id');
        $types = DB::table('smart_stock_inventory_logs')->where('business_id', $businessId)->distinct()->orderBy('transaction_type')->pluck('transaction_type', 'transaction_type');
        $skuOptions = collect();

        return view('smartstockinventory::movement.index', compact('rows', 'locations', 'users', 'types', 'stockSummary', 'selectedLocationId', 'skuOptions', 'selectedSku'));
    }

    public function searchSku(Request $request)
    {
        abort_unless($request->user()->can('stock_inventory.view'), 403);

        $businessId = $this->businessId();
        $permittedLocationIds = $this->permittedLocationIds($businessId);
        $term = trim((string) $request->input('term', ''));
        $locationId = (int) $request->input('location_id', 0);

        if ($locationId > 0 && ! in_array($locationId, $permittedLocationIds, true)) {
            abort(403, 'Unauthorized location.');
        }

        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        $rows = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('variation_location_details as vld', 'vld.variation_id', '=', 'v.id')
            ->leftJoin('purchase_lines as pl', 'pl.variation_id', '=', 'v.id')
            ->where('p.business_id', $businessId)
            ->whereNotNull('v.sub_sku')
            ->where('v.sub_sku', '!=', '')
            ->when($locationId > 0, fn ($q) => $q->where('vld.location_id', $locationId))
            ->where(function ($q) use ($like, $term) {
                $q->where('v.sub_sku', 'like', $like)
                    ->orWhere('p.name', 'like', $like)
                    ->orWhere('pl.lot_number', 'like', $like);
                if ($term !== '') {
                    $q->orWhere('v.sub_sku', $term);
                }
            })
            ->groupBy('v.sub_sku', 'p.name')
            ->select(
                'v.sub_sku as sku',
                'p.name as product_name',
                DB::raw("MAX(COALESCE(pl.lot_number, '')) as lot_number")
            )
            ->orderBy('v.sub_sku')
            ->limit(30)
            ->get();

        $results = $rows->map(function ($r) {
            $sku = (string) ($r->sku ?? '');
            $productName = (string) ($r->product_name ?? '');
            $lot = (string) ($r->lot_number ?? '');
            $text = $sku . ($productName !== '' ? ' | ' . $productName : '') . ($lot !== '' ? ' | LOT: ' . $lot : '');

            return [
                'id' => $sku,
                'text' => $text,
                'sku' => $sku,
                'product_name' => $productName,
                'lot_number' => $lot,
            ];
        })->values();

        return response()->json(['results' => $results]);
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

    public function editModal(Request $request, int $transaction)
    {
        abort_unless($request->user()->can('stock_inventory.update') || $request->user()->can('sell.update') || $request->user()->can('purchase.update') || $request->user()->can('stock_transfer.update'), 403);

        $tx = Transaction::where('business_id', $this->businessId())->findOrFail($transaction);

        // For stock transfer, keep module route but render original transfer UI inside module wrapper.
        if (in_array((string) $tx->type, ['sell_transfer', 'purchase_transfer'], true)) {
            $businessId = $this->businessId();
            $candidateSellTransferId = (int) $tx->id;
            if ((string) $tx->type === 'purchase_transfer' && ! empty($tx->transfer_parent_id)) {
                $candidateSellTransferId = (int) $tx->transfer_parent_id;
            }

            $sellTransferId = Transaction::where('business_id', $businessId)
                ->where('type', 'sell_transfer')
                ->where(function ($q) use ($tx, $candidateSellTransferId) {
                    $q->where('id', $candidateSellTransferId);
                    if (! empty($tx->transfer_parent_id)) {
                        $q->orWhere('id', (int) $tx->transfer_parent_id);
                    }
                })
                ->value('id');

            if (empty($sellTransferId)) {
                return back()->with('status', ['success' => 0, 'msg' => 'Transfer record not found for edit']);
            }
            $business_locations = BusinessLocation::forDropdown($businessId);
            $statuses = [
                'pending' => __('lang_v1.pending'),
                'in_transit' => __('lang_v1.in_transit'),
                'completed' => __('restaurant.completed'),
                'cancelled' => __('lang_v1.cancelled'),
            ];

            $sell_transfer = Transaction::where('business_id', $businessId)
                ->where('type', 'sell_transfer')
                ->with(['sell_lines'])
                ->findOrFail((int) $sellTransferId);

            $purchase_transfer = Transaction::where('business_id', $businessId)
                ->where('transfer_parent_id', (int) $sellTransferId)
                ->where('type', 'purchase_transfer')
                ->first();

            $products = [];
            foreach ($sell_transfer->sell_lines as $sell_line) {
                $product = $this->productUtil->getDetailsFromVariation(
                    $sell_line->variation_id,
                    $businessId,
                    $sell_transfer->location_id,
                    true,
                    true
                );
                $product->formatted_qty_available = $this->productUtil->num_f($product->qty_available);
                $product->sub_unit_id = $sell_line->sub_unit_id;
                $product->quantity_ordered = $sell_line->quantity;
                $product->transaction_sell_lines_id = $sell_line->id;
                $product->lot_no_line_id = $sell_line->lot_no_line_id;
                $product->unit_details = $this->productUtil->getSubUnits($businessId, $product->unit_id);

                $lot_numbers = [];
                if (request()->session()->get('business.enable_lot_number') == 1 || request()->session()->get('business.enable_product_expiry') == 1) {
                    $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($sell_line->variation_id, $businessId, $sell_transfer->location_id, true);
                    foreach ($lot_number_obj as $lot_number) {
                        $lot_number->qty_formated = $this->productUtil->num_f($lot_number->qty_available);
                        $lot_numbers[] = $lot_number;
                    }
                }
                $product->lot_numbers = $lot_numbers;
                $products[] = $product;
            }

            return view('stock_transfer.edit')->with(compact('sell_transfer', 'purchase_transfer', 'business_locations', 'statuses', 'products'));
        }

        $statusOptions = [
            'pending' => 'Pending',
            'ordered' => 'Ordered',
            'received' => 'Received',
            'final' => 'Final',
            'draft' => 'Draft',
            'in_transit' => 'In Transit',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        return view('smartstockinventory::movement.edit_modal', compact('tx', 'statusOptions'));
    }

    public function updateModal(Request $request, int $transaction)
    {
        abort_unless($request->user()->can('stock_inventory.update') || $request->user()->can('sell.update') || $request->user()->can('purchase.update') || $request->user()->can('stock_transfer.update'), 403);

        $tx = Transaction::where('business_id', $this->businessId())->findOrFail($transaction);
        $data = $request->validate([
            'ref_no' => 'nullable|string|max:191',
            'transaction_date' => 'required|date',
            'status' => 'required|string|max:50',
            'additional_notes' => 'nullable|string|max:1000',
        ]);

        $tx->ref_no = $data['ref_no'] ?? $tx->ref_no;
        $tx->transaction_date = $data['transaction_date'];
        $tx->status = $data['status'];
        $tx->additional_notes = $data['additional_notes'] ?? null;
        $tx->save();

        return redirect()
            ->route('ssi.movement.edit_modal', ['transaction' => $tx->id])
            ->with('status', ['success' => 1, 'msg' => 'Transaction updated successfully']);
    }

    public function voidTransaction(Request $request, int $transaction)
    {
        abort_unless(auth()->user()->can('stock_inventory.update') || auth()->user()->can('sell.update') || auth()->user()->can('purchase.update') || auth()->user()->can('stock_transfer.update'), 403);

        $businessId = $this->businessId();
        $tx = Transaction::where('business_id', $businessId)->findOrFail($transaction);

        if ($tx->status === 'cancelled') {
            return back()->with('status', ['success' => 1, 'msg' => 'Transaction is already cancelled']);
        }

        if ($tx->type === 'sell_transfer') {
            DB::beginTransaction();
            try {
                $this->rollbackSellTransferStock($businessId, (int) $tx->id);
                $tx->status = 'cancelled';
                $tx->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
            }
        } else {
            $tx->status = 'cancelled';
            $tx->save();
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Transaction voided successfully']);
    }

    public function restoreTransaction(Request $request, int $transaction)
    {
        abort_unless(auth()->user()->can('stock_inventory.update') || auth()->user()->can('sell.update') || auth()->user()->can('purchase.update') || auth()->user()->can('stock_transfer.update'), 403);

        $businessId = $this->businessId();
        $tx = Transaction::where('business_id', $businessId)->findOrFail($transaction);

        if ($tx->type === 'sell_transfer' && $tx->status === 'cancelled') {
            DB::beginTransaction();
            try {
                $this->applySellTransferStock($businessId, (int) $tx->id, $request);
                $tx->status = 'final';
                $tx->save();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
            }
        } else {
            $tx->status = $this->defaultRestoreStatus((string) $tx->type);
            $tx->save();
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Transaction restored successfully']);
    }

    private function rollbackSellTransferStock(int $businessId, int $sellTransferId): void
    {
        $sellTransfer = Transaction::where('business_id', $businessId)
            ->where('id', $sellTransferId)
            ->where('type', 'sell_transfer')
            ->with(['sell_lines'])
            ->firstOrFail();

        $purchaseTransfer = Transaction::where('business_id', $businessId)
            ->where('transfer_parent_id', $sellTransfer->id)
            ->where('type', 'purchase_transfer')
            ->with(['purchase_lines'])
            ->first();

        if (empty($purchaseTransfer)) {
            throw new \Exception('Linked purchase transfer not found');
        }

        $deletedSellPurchaseIds = [];
        $products = [];

        foreach ($sellTransfer->sell_lines as $sellLine) {
            if (isset($products[$sellLine->variation_id])) {
                $products[$sellLine->variation_id]['quantity'] += (float) $sellLine->quantity;
            } else {
                $products[$sellLine->variation_id] = [
                    'product_id' => (int) $sellLine->product_id,
                    'quantity' => (float) $sellLine->quantity,
                ];
            }

            $purchaseSellLines = TransactionSellLinesPurchaseLines::where('sell_line_id', $sellLine->id)->get();

            if ($purchaseSellLines->isNotEmpty()) {
                foreach ($purchaseSellLines as $purchaseSellLine) {
                    $reverseQty = (float) $purchaseSellLine->quantity;
                    if ($reverseQty <= 0) {
                        $deletedSellPurchaseIds[] = $purchaseSellLine->id;
                        continue;
                    }

                    $purchaseLine = PurchaseLine::lockForUpdate()->find($purchaseSellLine->purchase_line_id);
                    if (! empty($purchaseLine)) {
                        $purchaseLine->quantity_sold = max(0, (float) $purchaseLine->quantity_sold - $reverseQty);
                        $purchaseLine->save();
                    }
                    $deletedSellPurchaseIds[] = $purchaseSellLine->id;
                }

            } elseif (! empty($sellLine->lot_no_line_id)) {
                $fallbackQty = (float) $sellLine->quantity;
                if ($fallbackQty > 0) {
                    $purchaseLine = PurchaseLine::lockForUpdate()->find($sellLine->lot_no_line_id);
                    if (! empty($purchaseLine)) {
                        $purchaseLine->quantity_sold = max(0, (float) $purchaseLine->quantity_sold - $fallbackQty);
                        $purchaseLine->save();
                    }
                }
            }
        }

        if (! empty($products)) {
            foreach ($products as $variationId => $value) {
                $currentQtyAtDestination = (float) $this->productUtil->getCurrentStock((int) $variationId, (int) $purchaseTransfer->location_id);
                if ($currentQtyAtDestination < (float) $value['quantity']) {
                    throw new \Exception(__('lang_v1.stock_transfer_cannot_be_deleted'));
                }
            }

            foreach ($products as $variationId => $value) {
                $this->productUtil->decreaseProductQuantity(
                    $value['product_id'],
                    $variationId,
                    $purchaseTransfer->location_id,
                    $value['quantity']
                );
                $this->productUtil->updateProductQuantity(
                    $sellTransfer->location_id,
                    $value['product_id'],
                    $variationId,
                    $value['quantity']
                );

                if (method_exists($this->productUtil, 'recalculateVariationStock')) {
                    $this->productUtil->recalculateVariationStock($value['product_id'], $variationId, $sellTransfer->location_id);
                    $this->productUtil->recalculateVariationStock($value['product_id'], $variationId, $purchaseTransfer->location_id);
                }
            }
        }

        if (! empty($deletedSellPurchaseIds)) {
            TransactionSellLinesPurchaseLines::whereIn('id', $deletedSellPurchaseIds)->delete();
        }

        $purchaseTransfer->status = 'cancelled';
        $purchaseTransfer->save();
    }

    private function applySellTransferStock(int $businessId, int $sellTransferId, Request $request): void
    {
        $sellTransfer = Transaction::where('business_id', $businessId)
            ->where('type', 'sell_transfer')
            ->with(['sell_lines', 'sell_lines.product'])
            ->findOrFail($sellTransferId);

        $purchaseTransfer = Transaction::where('business_id', $businessId)
            ->where('transfer_parent_id', $sellTransferId)
            ->where('type', 'purchase_transfer')
            ->with(['purchase_lines'])
            ->first();

        if (empty($purchaseTransfer)) {
            throw new \Exception('Linked purchase transfer not found');
        }

        foreach ($sellTransfer->sell_lines as $sellLine) {
            if ($sellLine->product->enable_stock) {
                $this->productUtil->decreaseProductQuantity(
                    $sellLine->product_id,
                    $sellLine->variation_id,
                    $sellTransfer->location_id,
                    $sellLine->quantity
                );
                $this->productUtil->updateProductQuantity(
                    $purchaseTransfer->location_id,
                    $sellLine->product_id,
                    $sellLine->variation_id,
                    $sellLine->quantity,
                    0,
                    null,
                    false
                );
            }
        }

        $this->productUtil->adjustStockOverSelling($purchaseTransfer);

        $business = [
            'id' => $businessId,
            'accounting_method' => $request->session()->get('business.accounting_method'),
            'location_id' => $sellTransfer->location_id,
        ];
        $this->transactionUtil->mapPurchaseSell($business, $sellTransfer->sell_lines, 'purchase');

        $purchaseTransfer->status = 'received';
        $purchaseTransfer->save();
    }

    private function defaultRestoreStatus(string $type): string
    {
        if (in_array($type, ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'], true)) {
            return 'received';
        }
        if ($type === 'sell_transfer') {
            return 'final';
        }
        if (in_array($type, ['sell', 'sell_return', 'purchase_return', 'stock_adjustment', 'production_sell'], true)) {
            return 'final';
        }

        return 'final';
    }
}

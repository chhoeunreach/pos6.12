<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\PurchaseLine;
use App\Transaction;
use App\TransactionSellLinesPurchaseLines;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Datatables;
use DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use App\Events\StockTransferCreatedOrModified;
use App\Variation;

class StockTransferController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->status_colors = [
            'in_transit' => 'bg-yellow',
            'completed' => 'bg-green',
            'pending' => 'bg-red',
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('stock_transfer.view') && ! auth()->user()->can('stock_transfer.create') && ! auth()->user()->can('stock_transfer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $statuses = $this->stockTransferStatuses();

        if (request()->ajax()) {
            $edit_days = request()->session()->get('business.transaction_edit_days');

            $stock_transfers = Transaction::join(
                'business_locations AS l1',
                'transactions.location_id',
                '=',
                'l1.id'
            )
                    ->join('transactions as t2', 't2.transfer_parent_id', '=', 'transactions.id')
                    ->join(
                        'business_locations AS l2',
                        't2.location_id',
                        '=',
                        'l2.id'
                    )
                    ->leftJoin('transaction_sell_lines as tsl_sum', 'tsl_sum.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'sell_transfer');

                    if (! auth()->user()->can('stock_transfer.view') && auth()->user()->can('stock_transfer.view_own')) {
                        $stock_transfers->where('t2.created_by', request()->session()->get('user.id'));
                    }

                    $product_id = request()->get('product_id', null);
                    if (! empty($product_id)) {
                        $stock_transfers->whereExists(function ($query) use ($product_id) {
                            $query->select(DB::raw(1))
                                ->from('transaction_sell_lines as tsl')
                                ->whereColumn('tsl.transaction_id', 'transactions.id')
                                ->where('tsl.product_id', $product_id);
                        });
                    }

                    $location_from_id = request()->get('location_from_id', null);
                    if (! empty($location_from_id)) {
                        $stock_transfers->where('transactions.location_id', $location_from_id);
                    }

                    $location_to_id = request()->get('location_to_id', null);
                    if (! empty($location_to_id)) {
                        $stock_transfers->where('t2.location_id', $location_to_id);
                    }

                    $created_by = request()->get('created_by', null);
                    if (! empty($created_by)) {
                        $stock_transfers->where('t2.created_by', $created_by);
                    }

                    $status = request()->get('status', null);
                    if (! empty($status)) {
                        if ($status == 'completed') {
                            // Completed is stored as final in transactions.status
                            $stock_transfers->whereIn('transactions.status', ['final', 'completed']);
                        } else {
                            $stock_transfers->where('transactions.status', $status);
                        }
                    }

                    $start_date = request()->get('start_date', null);
                    $end_date = request()->get('end_date', null);
                    if (! empty($start_date) && ! empty($end_date)) {
                        $stock_transfers->whereDate('transactions.transaction_date', '>=', $start_date)
                            ->whereDate('transactions.transaction_date', '<=', $end_date);
                    }

                    $stock_transfers->select(
                        'transactions.id',
                        'transactions.transaction_date',
                        'transactions.ref_no',
                        'l1.name as location_from',
                        'l2.name as location_to',
                        'transactions.location_id as location_from_id',
                        't2.location_id as location_to_id',
                        't2.created_by as created_by',
                        DB::raw('COALESCE(SUM(tsl_sum.quantity), 0) as total_qty'),
                        'transactions.final_total',
                        'transactions.shipping_charges',
                        'transactions.additional_notes',
                        'transactions.id as DT_RowId',
                        'transactions.status'
                    );
                    $stock_transfers->groupBy(
                        'transactions.id',
                        'transactions.transaction_date',
                        'transactions.ref_no',
                        'l1.name',
                        'l2.name',
                        'transactions.location_id',
                        't2.location_id',
                        't2.created_by',
                        'transactions.final_total',
                        'transactions.shipping_charges',
                        'transactions.additional_notes',
                        'transactions.status'
                    );



            return Datatables::of($stock_transfers)
                ->addColumn('action', function ($row) use ($edit_days) {
                    $html = '';
                    if (auth()->user()->can('stock_transfer.view') || auth()->user()->can('stock_transfer.view_own')) {
                        $html .= '<button type="button" title="'.__('stock_adjustment.view_details').'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-accent btn-modal" data-container=".view_modal" data-href="'.action([\App\Http\Controllers\StockTransferController::class, 'show'], [$row->id]).'"><i class="fa fa-eye" aria-hidden="true"></i> '.__('messages.view').'</button>';
                    }

                    $html .= ' <a href="#" class="print-invoice tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info" data-href="'.action([\App\Http\Controllers\StockTransferController::class, 'printInvoice'], [$row->id]).'"><i class="fa fa-print" aria-hidden="true"></i> '.__('messages.print').'</a>';

                    // Retransfer allowed only for completed (final) transfers
                    if (in_array($row->status, ['final', 'completed']) && auth()->user()->can('stock_transfer.create')) {
                        $html .= ' <a href="'.action([\App\Http\Controllers\StockTransferController::class, 'retransfer'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-secondary"><i class="fa fa-random" aria-hidden="true"></i> Retransfer</a>';
                    }

                    $date = \Carbon::parse($row->transaction_date)
                        ->addDays($edit_days);
                    $today = today();

                    if ($date->gte($today) && auth()->user()->can('stock_transfer.delete')) {
                        $html .= '&nbsp;
                        <button type="button" data-href="'.action([\App\Http\Controllers\StockTransferController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error delete_stock_transfer"><i class="fa fa-trash" aria-hidden="true"></i> '.__('messages.delete').'</button>';
                    }

                    if ($row->status != 'final' && auth()->user()->can('stock_transfer.update')) {
                        $html .= '&nbsp;
                        <a href="'.action([\App\Http\Controllers\StockTransferController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary"><i class="fa fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a>';
                    }

                    return $html;
                })
                ->editColumn(
                    'final_total',
                    function($row) {
                        if (auth()->user()->can('view_purchase_price')) {
                            return '<span class="display_currency" data-currency_symbol="true">' . $row->final_total . '</span>';
                        } else {
                            return '<span>-</span>';
                        }
                    }
                )
                ->editColumn(
                    'shipping_charges',
                    '<span class="display_currency" data-currency_symbol="true">{{$shipping_charges}}</span>'
                )
                ->editColumn('total_qty', function ($row) {
                    return $this->productUtil->num_f($row->total_qty, false, null, true);
                })
                ->editColumn('status', function ($row) use ($statuses) {
                    $row->status = $row->status == 'final' ? 'completed' : $row->status;
                    $status = $statuses[$row->status];
                    $status_color = ! empty($this->status_colors[$row->status]) ? $this->status_colors[$row->status] : 'bg-gray';
                    $status = $row->status != 'completed' ? '<a href="#" class="stock_transfer_status" data-status="'.$row->status.'" data-href="'.action([\App\Http\Controllers\StockTransferController::class, 'updateStatus'], [$row->id]).'"><span class="label '.$status_color.'">'.$statuses[$row->status].'</span></a>' : '<span class="label '.$status_color.'">'.$statuses[$row->status].'</span>';

                    return $status;
                })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->rawColumns(['final_total', 'action', 'shipping_charges', 'status'])
                ->setRowAttr([
                    'data-href' => function ($row) {
                        return  action([\App\Http\Controllers\StockTransferController::class, 'show'], [$row->id]);
                    }, ])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $users = User::forDropdown($business_id, false, false, true);

        return view('stock_transfer.index')->with(compact('statuses', 'business_locations', 'users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('stock_transfer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\StockTransferController::class, 'index']));
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $statuses = $this->stockTransferStatuses();

        return view('stock_transfer.create')
                ->with(compact('business_locations', 'statuses'));
    }

    private function stockTransferStatuses()
    {
        return [
            'pending' => __('lang_v1.pending'),
            'in_transit' => __('lang_v1.in_transit'),
            'completed' => __('restaurant.completed'),
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('stock_transfer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\StockTransferController::class, 'index']));
            }

            DB::beginTransaction();

            $input_data = $request->only(['location_id', 'ref_no', 'transaction_date', 'additional_notes', 'shipping_charges', 'final_total']);
            $status = $request->input('status');
            $user_id = $request->session()->get('user.id');

            // Retransfer identifier (do not modify notes automatically)
            $retransfer_of_id = $request->input('retransfer_of_transfer_id');

            $input_data['total_before_tax'] = $input_data['final_total'];

            $input_data['type'] = 'sell_transfer';
            $input_data['business_id'] = $business_id;
            $input_data['created_by'] = $user_id;
            $input_data['transaction_date'] = $this->productUtil->uf_date($input_data['transaction_date'], true);
            $input_data['shipping_charges'] = $this->productUtil->num_uf($input_data['shipping_charges']);
            $input_data['payment_status'] = 'paid';
            $input_data['status'] = $status == 'completed' ? 'final' : $status;

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount('stock_transfer');
            //Generate reference number
            if (empty($input_data['ref_no'])) {
                $input_data['ref_no'] = $this->productUtil->generateReferenceNumber('stock_transfer', $ref_count);
            } else {
                // If ref_no exists already for this business, make it unique for retransfer.
                $ref_exists = Transaction::where('business_id', $business_id)
                    ->where('ref_no', $input_data['ref_no'])
                    ->exists();

                if ($ref_exists && ! empty($retransfer_of_id)) {
                    $base_ref = $input_data['ref_no'];
                    $suffix = 1;
                    while (Transaction::where('business_id', $business_id)->where('ref_no', $base_ref . '-' . $suffix)->exists()) {
                        $suffix++;
                    }
                    $input_data['ref_no'] = $base_ref . '-' . $suffix;
                }
            }

            $products = $request->input('products');
            $sell_lines = [];
            $purchase_lines = [];

            if (! empty($products)) {
                foreach ($products as $product) {
                    $sell_line_arr = [
                        'product_id' => $product['product_id'],
                        'variation_id' => $product['variation_id'],
                        'quantity' => $this->productUtil->num_uf($product['quantity']),
                        'item_tax' => 0,
                        'line_total_tax' => 0,
                        'tax_id' => null, ];

                    if (! empty($product['product_unit_id'])) {
                        $sell_line_arr['product_unit_id'] = $product['product_unit_id'];
                    }
                    if (! empty($product['sub_unit_id'])) {
                        $sell_line_arr['sub_unit_id'] = $product['sub_unit_id'];
                    }

                    $purchase_line_arr = $sell_line_arr;

                    if (! empty($product['base_unit_multiplier'])) {
                        $sell_line_arr['base_unit_multiplier'] = $product['base_unit_multiplier'];
                    }

                    $sell_line_arr['unit_price'] = $this->productUtil->num_uf($product['unit_price']);
                    $sell_line_arr['unit_price_inc_tax'] = $sell_line_arr['unit_price'];

                    $purchase_line_arr['purchase_price'] = $sell_line_arr['unit_price'];
                    $purchase_line_arr['purchase_price_inc_tax'] = $sell_line_arr['unit_price'];

                    if (! empty($product['lot_no_line_id'])) {
                        //Add lot_no_line_id to sell line
                        $sell_line_arr['lot_no_line_id'] = $product['lot_no_line_id'];

                        //Copy lot number and expiry date to purchase line
                        $lot_details = PurchaseLine::find($product['lot_no_line_id']);
                        $purchase_line_arr['lot_number'] = $lot_details->lot_number;
                        $purchase_line_arr['mfg_date'] = $lot_details->mfg_date;
                        $purchase_line_arr['exp_date'] = $lot_details->exp_date;
                    }

                    if (! empty($product['base_unit_multiplier'])) {
                        $purchase_line_arr['quantity'] = $purchase_line_arr['quantity'] * $product['base_unit_multiplier'];
                        $purchase_line_arr['purchase_price'] = $purchase_line_arr['purchase_price'] / $product['base_unit_multiplier'];
                        $purchase_line_arr['purchase_price_inc_tax'] = $purchase_line_arr['purchase_price_inc_tax'] / $product['base_unit_multiplier'];
                    }

                    if (isset($purchase_line_arr['sub_unit_id']) && $purchase_line_arr['sub_unit_id'] == $purchase_line_arr['product_unit_id']) {
                        unset($purchase_line_arr['sub_unit_id']);
                    }
                    unset($purchase_line_arr['product_unit_id']);

                    $sell_lines[] = $sell_line_arr;
                    $purchase_lines[] = $purchase_line_arr;
                }
            }

            //Create Sell Transfer transaction
            $sell_transfer = Transaction::create($input_data);

            //Create Purchase Transfer at transfer location
            $input_data['type'] = 'purchase_transfer';
            $input_data['location_id'] = $request->input('transfer_location_id');
            $input_data['transfer_parent_id'] = $sell_transfer->id;
            $input_data['status'] = $status == 'completed' ? 'received' : $status;

            $purchase_transfer = Transaction::create($input_data);

            //Sell Product from first location
            if (! empty($sell_lines)) {
                $this->transactionUtil->createOrUpdateSellLines($sell_transfer, $sell_lines, $input_data['location_id'], false, null, [], false);
            }

            //Purchase product in second location
            if (! empty($purchase_lines)) {
                $purchase_transfer->purchase_lines()->createMany($purchase_lines);
            }

            //Decrease product stock from sell location
            //And increase product stock at purchase location
            if ($status == 'completed') {
                foreach ($products as $product) {
                    if ($product['enable_stock']) {
                        $decrease_qty = $this->productUtil
                                    ->num_uf($product['quantity']);
                        if (! empty($product['base_unit_multiplier'])) {
                            $decrease_qty = $decrease_qty * $product['base_unit_multiplier'];
                        }

                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $sell_transfer->location_id,
                            $decrease_qty
                        );

                        $this->productUtil->updateProductQuantity(
                            $purchase_transfer->location_id,
                            $product['product_id'],
                            $product['variation_id'],
                            $decrease_qty,
                            0,
                            null,
                            false
                        );
                    }
                }

                //Adjust stock over selling if found
                $this->productUtil->adjustStockOverSelling($purchase_transfer);

                //Map sell lines with purchase lines
                $business = ['id' => $business_id,
                    'accounting_method' => $request->session()->get('business.accounting_method'),
                    'location_id' => $sell_transfer->location_id,
                ];
                $this->transactionUtil->mapPurchaseSell($business, $sell_transfer->sell_lines, 'purchase');
            }

            $this->transactionUtil->activityLog($sell_transfer, 'added');

            event( new StockTransferCreatedOrModified($sell_transfer, 'added'));

            $output = ['success' => 1,
                'msg' => __('lang_v1.stock_transfer_added_successfully'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect('stock-transfers')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('stock_transfer.view') && ! auth()->user()->can('stock_transfer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $sell_transfer = Transaction::where('business_id', $business_id)
                            ->where('id', $id)
                            ->where('type', 'sell_transfer')
                            ->with(
                                'contact',
                                'sell_lines',
                                'sell_lines.product',
                                'sell_lines.variations',
                                'sell_lines.variations.product_variation',
                                'sell_lines.lot_details',
                                'sell_lines.sub_unit',
                                'location',
                                'sell_lines.product.unit'
                            )
                            ->first();

        foreach ($sell_transfer->sell_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_sell_line = $this->transactionUtil->recalculateSellLineTotals($business_id, $value);

                $sell_transfer->sell_lines[$key] = $formated_sell_line;
            }
        }

        $purchase_transfer = Transaction::where('business_id', $business_id)
                    ->where('transfer_parent_id', $sell_transfer->id)
                    ->where('type', 'purchase_transfer')
                    ->first();

        $location_details = ['sell' => $sell_transfer->location, 'purchase' => $purchase_transfer->location];

        $lot_n_exp_enabled = false;
        if (request()->session()->get('business.enable_lot_number') == 1 || request()->session()->get('business.enable_product_expiry') == 1) {
            $lot_n_exp_enabled = true;
        }

        $statuses = $this->stockTransferStatuses();

        $statuses['final'] = __('restaurant.completed');

        $activities = Activity::forSubject($sell_transfer)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('stock_transfer.show')
                ->with(compact('sell_transfer', 'location_details', 'lot_n_exp_enabled', 'statuses', 'activities'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('stock_transfer.delete')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if (request()->ajax()) {
                $edit_days = request()->session()->get('business.transaction_edit_days');
                if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
                    return ['success' => 0,
                        'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ];
                }

                //Get sell transfer transaction
                $sell_transfer = Transaction::where('id', $id)
                                    ->where('type', 'sell_transfer')
                                    ->with(['sell_lines'])
                                    ->first();

                //Get purchase transfer transaction
                $purchase_transfer = Transaction::where('transfer_parent_id', $sell_transfer->id)
                                    ->where('type', 'purchase_transfer')
                                    ->with(['purchase_lines'])
                                    ->first();

                //Check if any transfer stock is deleted and delete purchase lines
                $purchase_lines = $purchase_transfer->purchase_lines;
                foreach ($purchase_lines as $purchase_line) {
                    if ($purchase_line->quantity_sold > 0) {
                        return ['success' => 0,
                            'msg' => __('lang_v1.stock_transfer_cannot_be_deleted'),
                        ];
                    }
                }

                event( new StockTransferCreatedOrModified($sell_transfer, 'deleted'));

                DB::beginTransaction();
                //Get purchase lines from transaction_sell_lines_purchase_lines and decrease quantity_sold
                $sell_lines = $sell_transfer->sell_lines;
                $deleted_sell_purchase_ids = [];
                $products = []; //variation_id as array

                foreach ($sell_lines as $sell_line) {
                    $purchase_sell_line = TransactionSellLinesPurchaseLines::where('sell_line_id', $sell_line->id)->first();

                    if (! empty($purchase_sell_line)) {
                        //Decrease quntity sold from purchase line
                        PurchaseLine::where('id', $purchase_sell_line->purchase_line_id)
                                ->decrement('quantity_sold', $sell_line->quantity);

                        $deleted_sell_purchase_ids[] = $purchase_sell_line->id;

                        //variation details
                        if (isset($products[$sell_line->variation_id])) {
                            $products[$sell_line->variation_id]['quantity'] += $sell_line->quantity;
                            $products[$sell_line->variation_id]['product_id'] = $sell_line->product_id;
                        } else {
                            $products[$sell_line->variation_id]['quantity'] = $sell_line->quantity;
                            $products[$sell_line->variation_id]['product_id'] = $sell_line->product_id;
                        }
                    }
                }

                //Update quantity available in both location
                if (! empty($products)) {
                    foreach ($products as $key => $value) {
                        //Decrease from location 2
                        $this->productUtil->decreaseProductQuantity(
                            $products[$key]['product_id'],
                            $key,
                            $purchase_transfer->location_id,
                            $products[$key]['quantity']
                        );

                        //Increase in location 1
                        $this->productUtil->updateProductQuantity(
                            $sell_transfer->location_id,
                            $products[$key]['product_id'],
                            $key,
                            $products[$key]['quantity']
                        );
                    }
                }

                //Delete sale line purchase line
                if (! empty($deleted_sell_purchase_ids)) {
                    TransactionSellLinesPurchaseLines::whereIn('id', $deleted_sell_purchase_ids)
                        ->delete();
                }

                //Delete both transactions
                $sell_transfer->delete();
                $purchase_transfer->delete();
                event( new StockTransferCreatedOrModified($sell_transfer, 'deleted'));
                $output = ['success' => 1,
                    'msg' => __('lang_v1.stock_transfer_delete_success'),
                ];
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Checks if ref_number and supplier combination already exists.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function printInvoice($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $sell_transfer = Transaction::where('business_id', $business_id)
                                ->where('id', $id)
                                ->where('type', 'sell_transfer')
                                ->with(
                                    'contact',
                                    'sell_lines',
                                    'sell_lines.product',
                                    'sell_lines.variations',
                                    'sell_lines.variations.product_variation',
                                    'sell_lines.lot_details',
                                    'location',
                                    'sell_lines.product.unit'
                                )
                                ->first();

            $purchase_transfer = Transaction::where('business_id', $business_id)
                        ->where('transfer_parent_id', $sell_transfer->id)
                        ->where('type', 'purchase_transfer')
                        ->first();

            $location_details = ['sell' => $sell_transfer->location, 'purchase' => $purchase_transfer->location];

            $lot_n_exp_enabled = false;
            if (request()->session()->get('business.enable_lot_number') == 1 || request()->session()->get('business.enable_product_expiry') == 1) {
                $lot_n_exp_enabled = true;
            }

            $output = ['success' => 1, 'receipt' => [], 'print_title' => $sell_transfer->ref_no];
            $output['receipt']['html_content'] = view('stock_transfer.print', compact('sell_transfer', 'location_details', 'lot_n_exp_enabled'))->render();
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id);

        $statuses = $this->stockTransferStatuses();

        $sell_transfer = Transaction::where('business_id', $business_id)
                ->where('type', 'sell_transfer')
                ->where('status', '!=', 'final')
                ->with(['sell_lines'])
                ->findOrFail($id);

        $purchase_transfer = Transaction::where('business_id',
                $business_id)
                ->where('transfer_parent_id', $id)
                ->where('status', '!=', 'received')
                ->where('type', 'purchase_transfer')
                ->first();

        $products = [];
        foreach ($sell_transfer->sell_lines as $sell_line) {
            $product = $this->productUtil->getDetailsFromVariation(
                $sell_line->variation_id,
                $business_id,
                $sell_transfer->location_id,
                true,
                // Allow zero stock here so an existing transfer can still be edited/viewed
                // even if the current location stock has since reached 0.
                true
            );
            $product->formatted_qty_available = $this->productUtil->num_f($product->qty_available);
            $product->sub_unit_id = $sell_line->sub_unit_id;
            $product->quantity_ordered = $sell_line->quantity;
            $product->transaction_sell_lines_id = $sell_line->id;
            $product->lot_no_line_id = $sell_line->lot_no_line_id;

            $product->unit_details = $this->productUtil->getSubUnits($business_id, $product->unit_id);

            //Get lot number dropdown if enabled
            $lot_numbers = [];
            if (request()->session()->get('business.enable_lot_number') == 1 || request()->session()->get('business.enable_product_expiry') == 1) {
                $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($sell_line->variation_id, $business_id, $sell_transfer->location_id, true);
                foreach ($lot_number_obj as $lot_number) {
                    $lot_number->qty_formated = $this->productUtil->num_f($lot_number->qty_available);
                    $lot_numbers[] = $lot_number;
                }
            }
            $product->lot_numbers = $lot_numbers;

            $products[] = $product;
        }

        return view('stock_transfer.edit')
                ->with(compact('sell_transfer', 'purchase_transfer', 'business_locations', 'statuses', 'products'));
    }

    /**
     * Create a new stock transfer based on an existing completed transfer.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function retransfer($id)
    {
        if (! auth()->user()->can('stock_transfer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Original transfer must be completed (final)
        $original_transfer = Transaction::where('business_id', $business_id)
            ->where('type', 'sell_transfer')
            ->where('status', 'final')
            ->with(['sell_lines'])
            ->findOrFail($id);

        $purchase_transfer = Transaction::where('business_id', $business_id)
            ->where('transfer_parent_id', $id)
            ->where('type', 'purchase_transfer')
            ->first();

        $business_locations = BusinessLocation::forDropdown($business_id);
        $statuses = $this->stockTransferStatuses();

        // Suggested Ref No: RT-<original>, ensure uniqueness within the business.
        $suggested_ref_no = 'RT-' . $original_transfer->ref_no;
        if (Transaction::where('business_id', $business_id)->where('ref_no', $suggested_ref_no)->exists()) {
            $suffix = 1;
            while (Transaction::where('business_id', $business_id)->where('ref_no', $suggested_ref_no . '-' . $suffix)->exists()) {
                $suffix++;
            }
            $suggested_ref_no = $suggested_ref_no . '-' . $suffix;
        }

        // Notes must match original exactly (no auto-append)
        $suggested_additional_notes = $original_transfer->additional_notes;

        // Default source = original destination location (where stock should now exist)
        $default_location_from_id = ! empty($purchase_transfer) ? $purchase_transfer->location_id : $original_transfer->location_id;

        $products = [];
        $retransfer_lines = [];

        foreach ($original_transfer->sell_lines as $sell_line) {
            $product = $this->productUtil->getDetailsFromVariation(
                $sell_line->variation_id,
                $business_id,
                $default_location_from_id,
                true,
                true
            );
            $product->formatted_qty_available = $this->productUtil->num_f($product->qty_available);
            $product->sub_unit_id = $sell_line->sub_unit_id;
            $product->quantity_ordered = $sell_line->quantity;
            $product->lot_no_line_id = $sell_line->lot_no_line_id;
            $product->unit_details = $this->productUtil->getSubUnits($business_id, $product->unit_id);

            // Get lot number dropdown if enabled
            $lot_numbers = [];
            if (request()->session()->get('business.enable_lot_number') == 1 || request()->session()->get('business.enable_product_expiry') == 1) {
                $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($sell_line->variation_id, $business_id, $default_location_from_id, true);
                foreach ($lot_number_obj as $lot_number) {
                    $lot_number->qty_formated = $this->productUtil->num_f($lot_number->qty_available);
                    $lot_numbers[] = $lot_number;
                }
            }
            $product->lot_numbers = $lot_numbers;

            $products[] = $product;

            $retransfer_lines[] = [
                'variation_id' => $sell_line->variation_id,
                'quantity' => (float) $sell_line->quantity,
                'sub_unit_id' => $sell_line->sub_unit_id,
                'lot_no_line_id' => $sell_line->lot_no_line_id,
            ];
        }

        return view('stock_transfer.retransfer')
            ->with(compact('original_transfer', 'purchase_transfer', 'business_locations', 'statuses', 'products', 'default_location_from_id', 'retransfer_lines', 'suggested_ref_no', 'suggested_additional_notes'));
    }

    /**
     * Download CSV import template for stock transfer lines.
     */
    public function downloadImportTemplate()
    {
        if (! auth()->user()->can('stock_transfer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stock-transfer-import-template.csv"',
        ];

        $columns = ['sku', 'lot_number', 'quantity', 'note'];
        $sample = ['SKU-OR-SUBSKU-HERE', 'LOT-123 (optional)', '1', 'optional note/serial'];

        $callback = function () use ($columns, $sample) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $columns);
            fputcsv($out, $sample);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import stock transfer lines (preview only) by SKU and/or Lot Number.
     * Loads valid items into the create form via JS; does not save transactions.
     *
     * Expected columns (by position):
     * 0: SKU (optional)
     * 1: Lot Number (optional)
     * 2: Quantity (required)
     * 3: Note (optional)
     */
    public function importLines(Request $request)
    {
        try {
            if (! auth()->user()->can('stock_transfer.create')) {
                abort(403, 'Unauthorized action.');
            }

            if (! $request->ajax()) {
                abort(404);
            }

            $request->validate([
                'file' => 'required|file',
                'location_id' => 'required',
            ]);

            $business_id = $request->session()->get('user.business_id');
            $location_id = $request->input('location_id');

            $file = $request->file('file');
            $parsed_array = Excel::toArray([], $file);

            if (empty($parsed_array) || empty($parsed_array[0])) {
                return [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            // Remove header row only if it looks like a header
            $sheet = $parsed_array[0];
            $first_row = $sheet[0] ?? [];
            $first_row_normalized = array_map(function ($v) {
                return strtolower(trim((string) $v));
            }, $first_row);
            $looks_like_header = in_array('sku', $first_row_normalized) || in_array('lot_number', $first_row_normalized) || in_array('quantity', $first_row_normalized);
            $imported_data = $looks_like_header ? array_splice($sheet, 1) : $sheet;

            $errors = [];
            $raw_success_lines = [];
            foreach ($imported_data as $index => $row) {
                $row_number = $looks_like_header ? ($index + 2) : ($index + 1);

                $sku = isset($row[0]) ? trim((string) $row[0]) : '';
                $lot_number = isset($row[1]) ? trim((string) $row[1]) : '';
                $quantity_raw = $row[2] ?? null;
                $note = isset($row[3]) ? trim((string) $row[3]) : '';

                if (empty($sku) && empty($lot_number)) {
                    $errors[] = [
                        'row' => $row_number,
                        'sku' => $sku,
                        'lot_number' => $lot_number,
                        'quantity' => $quantity_raw,
                        'note' => $note,
                        'match_by' => null,
                        'error' => 'SKU or Lot Number is required.',
                    ];
                    continue;
                }

                if ($quantity_raw === null || $quantity_raw === '' || ! is_numeric($quantity_raw) || (float) $quantity_raw <= 0) {
                    $errors[] = [
                        'row' => $row_number,
                        'sku' => $sku,
                        'lot_number' => $lot_number,
                        'quantity' => $quantity_raw,
                        'note' => $note,
                        'match_by' => ! empty($lot_number) ? 'lot' : 'sku',
                        'error' => 'Quantity is required and must be greater than 0.',
                    ];
                    continue;
                }

                $qty = (float) $quantity_raw;

                // Prefer lot if provided
                if (! empty($lot_number)) {
                    $purchase_line = PurchaseLine::join('transactions as T', 'purchase_lines.transaction_id', '=', 'T.id')
                        ->where('T.business_id', $business_id)
                        ->where('T.location_id', $location_id)
                        ->whereNotNull('purchase_lines.lot_number')
                        ->where('purchase_lines.lot_number', $lot_number)
                        ->whereRaw('(purchase_lines.quantity_sold + purchase_lines.quantity_adjusted + purchase_lines.quantity_returned) < purchase_lines.quantity')
                        ->select(
                            'purchase_lines.id as purchase_line_id',
                            'purchase_lines.product_id',
                            'purchase_lines.variation_id',
                            'purchase_lines.lot_number',
                            DB::raw('(purchase_lines.quantity - (purchase_lines.quantity_sold + purchase_lines.quantity_adjusted + purchase_lines.quantity_returned)) AS qty_available')
                        )
                        ->orderByDesc(DB::raw('(purchase_lines.quantity - (purchase_lines.quantity_sold + purchase_lines.quantity_adjusted + purchase_lines.quantity_returned))'))
                        ->first();

                    if (empty($purchase_line)) {
                        $errors[] = [
                            'row' => $row_number,
                            'sku' => $sku,
                            'lot_number' => $lot_number,
                            'quantity' => $qty,
                            'note' => $note,
                            'match_by' => 'lot',
                            'error' => 'Lot number not found in selected source location.',
                        ];
                        continue;
                    }

                    if ($qty > (float) $purchase_line->qty_available) {
                        $errors[] = [
                            'row' => $row_number,
                            'sku' => $sku,
                            'lot_number' => $lot_number,
                            'quantity' => $qty,
                            'note' => $note,
                            'match_by' => 'lot',
                            'error' => 'Quantity exceeds available stock in this lot.',
                        ];
                        continue;
                    }

                    // Validate variation exists & is for this business (defensive)
                    try {
                        $this->productUtil->getDetailsFromVariation($purchase_line->variation_id, $business_id, $location_id, true, true);
                    } catch (\Exception $e) {
                        $errors[] = [
                            'row' => $row_number,
                            'sku' => $sku,
                            'lot_number' => $lot_number,
                            'quantity' => $qty,
                            'note' => $note,
                            'match_by' => 'lot',
                            'error' => 'Product for this lot could not be loaded.',
                        ];
                        continue;
                    }

                    $raw_success_lines[] = [
                        'variation_id' => (int) $purchase_line->variation_id,
                        'quantity' => $qty,
                        'lot_no_line_id' => (int) $purchase_line->purchase_line_id,
                        'note' => $note,
                        'match_by' => 'lot',
                        'sku' => $sku,
                        'lot_number' => $lot_number,
                    ];
                    continue;
                }

                // SKU match (sub_sku preferred)
                $sku_value = $sku;
                $variation = Variation::where('sub_sku', $sku_value)
                    ->join('products as p', 'p.id', '=', 'variations.product_id')
                    ->where('p.business_id', $business_id)
                    ->select('variations.*')
                    ->first();

                if (empty($variation)) {
                    // Try product sku -> first variation
                    $variation = Variation::join('products as p', 'p.id', '=', 'variations.product_id')
                        ->where('p.business_id', $business_id)
                        ->where('p.sku', $sku_value)
                        ->select('variations.*')
                        ->first();
                }

                if (empty($variation)) {
                    $errors[] = [
                        'row' => $row_number,
                        'sku' => $sku,
                        'lot_number' => $lot_number,
                        'quantity' => $qty,
                        'note' => $note,
                        'match_by' => 'sku',
                        'error' => 'Product not found for SKU.',
                    ];
                    continue;
                }

                $product_details = $this->productUtil->getDetailsFromVariation($variation->id, $business_id, $location_id, true, true);
                if ($product_details->enable_stock == 1 && $qty > (float) $product_details->qty_available) {
                    $errors[] = [
                        'row' => $row_number,
                        'sku' => $sku,
                        'lot_number' => $lot_number,
                        'quantity' => $qty,
                        'note' => $note,
                        'match_by' => 'sku',
                        'error' => 'Quantity exceeds available stock in selected source location.',
                    ];
                    continue;
                }

                $raw_success_lines[] = [
                    'variation_id' => (int) $variation->id,
                    'quantity' => $qty,
                    'lot_no_line_id' => null,
                    'note' => $note,
                    'match_by' => 'sku',
                    'sku' => $sku,
                    'lot_number' => $lot_number,
                ];
            }

            // Merge duplicates: same variation + same lot (or both null)
            $merged = [];
            foreach ($raw_success_lines as $line) {
                $key = $line['variation_id'] . ':' . (! empty($line['lot_no_line_id']) ? $line['lot_no_line_id'] : 0);
                if (! isset($merged[$key])) {
                    $merged[$key] = $line;
                } else {
                    $merged[$key]['quantity'] += $line['quantity'];
                    // Preserve notes by concatenation
                    if (! empty($line['note'])) {
                        $existing = trim((string) ($merged[$key]['note'] ?? ''));
                        $merged[$key]['note'] = trim($existing . (empty($existing) ? '' : "\n") . $line['note']);
                    }
                }
            }

            $success_lines = array_values($merged);

            // Re-validate merged quantities against available stock
            $final_success = [];
            foreach ($success_lines as $line) {
                if (! empty($line['lot_no_line_id'])) {
                    $pl = PurchaseLine::join('transactions as T', 'purchase_lines.transaction_id', '=', 'T.id')
                        ->where('T.business_id', $business_id)
                        ->where('T.location_id', $location_id)
                        ->where('purchase_lines.id', $line['lot_no_line_id'])
                        ->select(
                            'purchase_lines.id as purchase_line_id',
                            DB::raw('(purchase_lines.quantity - (purchase_lines.quantity_sold + purchase_lines.quantity_adjusted + purchase_lines.quantity_returned)) AS qty_available')
                        )
                        ->first();

                    if (empty($pl) || (float) $line['quantity'] > (float) $pl->qty_available) {
                        $errors[] = [
                            'row' => null,
                            'sku' => $line['sku'] ?? '',
                            'lot_number' => $line['lot_number'] ?? '',
                            'quantity' => $line['quantity'],
                            'note' => $line['note'] ?? '',
                            'match_by' => 'lot',
                            'error' => 'Merged quantity exceeds available stock in this lot.',
                        ];
                        continue;
                    }
                } else {
                    $product_details = $this->productUtil->getDetailsFromVariation($line['variation_id'], $business_id, $location_id, true, true);
                    if ($product_details->enable_stock == 1 && (float) $line['quantity'] > (float) $product_details->qty_available) {
                        $errors[] = [
                            'row' => null,
                            'sku' => $line['sku'] ?? '',
                            'lot_number' => '',
                            'quantity' => $line['quantity'],
                            'note' => $line['note'] ?? '',
                            'match_by' => 'sku',
                            'error' => 'Merged quantity exceeds available stock in selected source location.',
                        ];
                        continue;
                    }
                }
                $final_success[] = $line;
            }

            return [
                'success' => true,
                'summary' => [
                    'total_rows' => count($imported_data),
                    'success' => count($final_success),
                    'failed' => count($errors),
                ],
                'lines' => $final_success,
                'errors' => $errors,
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            return [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\StockTransferController::class, 'index']));
            }

            $business_id = request()->session()->get('user.business_id');

            $sell_transfer = Transaction::where('business_id', $business_id)
                    ->where('type', 'sell_transfer')
                    ->findOrFail($id);

            $sell_transfer_before = $sell_transfer->replicate();

            $purchase_transfer = Transaction::where('business_id',
                    $business_id)
                    ->where('transfer_parent_id', $id)
                    ->where('type', 'purchase_transfer')
                    ->with(['purchase_lines'])
                    ->first();

            $status = $request->input('status');

            DB::beginTransaction();

            $input_data = $request->only(['transaction_date', 'additional_notes', 'shipping_charges', 'final_total']);
            $status = $request->input('status');

            $input_data['total_before_tax'] = $input_data['final_total'];

            $input_data['transaction_date'] = $this->productUtil->uf_date($input_data['transaction_date'], true);
            $input_data['shipping_charges'] = $this->productUtil->num_uf($input_data['shipping_charges']);
            $input_data['status'] = $status == 'completed' ? 'final' : $status;

            $products = $request->input('products');
            $sell_lines = [];
            $purchase_lines = [];
            $edited_purchase_lines = [];
            if (! empty($products)) {
                foreach ($products as $product) {
                    $sell_line_arr = [
                        'product_id' => $product['product_id'],
                        'variation_id' => $product['variation_id'],
                        'quantity' => $this->productUtil->num_uf($product['quantity']),
                        'item_tax' => 0,
                        'line_total_tax' => 0,
                        'tax_id' => null, ];

                    if (! empty($product['product_unit_id'])) {
                        $sell_line_arr['product_unit_id'] = $product['product_unit_id'];
                    }
                    if (! empty($product['sub_unit_id'])) {
                        $sell_line_arr['sub_unit_id'] = $product['sub_unit_id'];
                    }

                    $purchase_line_arr = $sell_line_arr;

                    if (! empty($product['base_unit_multiplier'])) {
                        $sell_line_arr['base_unit_multiplier'] = $product['base_unit_multiplier'];
                    }

                    $sell_line_arr['unit_price'] = $this->productUtil->num_uf($product['unit_price']);
                    $sell_line_arr['unit_price_inc_tax'] = $sell_line_arr['unit_price'];

                    $purchase_line_arr['purchase_price'] = $sell_line_arr['unit_price'];
                    $purchase_line_arr['purchase_price_inc_tax'] = $sell_line_arr['unit_price'];
                    if (isset($product['transaction_sell_lines_id'])) {
                        $sell_line_arr['transaction_sell_lines_id'] = $product['transaction_sell_lines_id'];
                    }

                    if (! empty($product['lot_no_line_id'])) {
                        //Add lot_no_line_id to sell line
                        $sell_line_arr['lot_no_line_id'] = $product['lot_no_line_id'];

                        //Copy lot number and expiry date to purchase line
                        $lot_details = PurchaseLine::find($product['lot_no_line_id']);
                        $purchase_line_arr['lot_number'] = $lot_details->lot_number;
                        $purchase_line_arr['mfg_date'] = $lot_details->mfg_date;
                        $purchase_line_arr['exp_date'] = $lot_details->exp_date;
                    }

                    if (! empty($product['base_unit_multiplier'])) {
                        $purchase_line_arr['quantity'] = $purchase_line_arr['quantity'] * $product['base_unit_multiplier'];
                        $purchase_line_arr['purchase_price'] = $purchase_line_arr['purchase_price'] / $product['base_unit_multiplier'];
                        $purchase_line_arr['purchase_price_inc_tax'] = $purchase_line_arr['purchase_price_inc_tax'] / $product['base_unit_multiplier'];
                    }

                    if (isset($purchase_line_arr['sub_unit_id']) && $purchase_line_arr['sub_unit_id'] == $purchase_line_arr['product_unit_id']) {
                        unset($purchase_line_arr['sub_unit_id']);
                    }
                    unset($purchase_line_arr['product_unit_id']);

                    $sell_lines[] = $sell_line_arr;

                    $purchase_line = [];
                    //check if purchase_line for the variation exists else create new
                    foreach ($purchase_transfer->purchase_lines as $pl) {
                        if ($pl->variation_id == $purchase_line_arr['variation_id']) {
                            $pl->update($purchase_line_arr);
                            $edited_purchase_lines[] = $pl->id;
                            $purchase_line = $pl;
                            break;
                        }
                    }
                    if (empty($purchase_line)) {
                        $purchase_line = new PurchaseLine($purchase_line_arr);
                    }

                    $purchase_lines[] = $purchase_line;
                }
            }

            //Create Sell Transfer transaction
            $sell_transfer->update($input_data);
            $sell_transfer->save();

            event( new StockTransferCreatedOrModified($sell_transfer, 'updated'));

            //Create Purchase Transfer at transfer location
            $input_data['status'] = $status == 'completed' ? 'received' : $status;

            $purchase_transfer->update($input_data);
            $purchase_transfer->save();

            //Sell Product from first location
            if (! empty($sell_lines)) {
                $this->transactionUtil->createOrUpdateSellLines($sell_transfer, $sell_lines, $sell_transfer->location_id, false, 'draft', [], false);
            }

            //Purchase product in second location
            if (! empty($purchase_lines)) {
                if (! empty($edited_purchase_lines)) {
                    PurchaseLine::where('transaction_id', $purchase_transfer->id)
                    ->whereNotIn('id', $edited_purchase_lines)
                    ->delete();
                }
                $purchase_transfer->purchase_lines()->saveMany($purchase_lines);
            }

            //Decrease product stock from sell location
            //And increase product stock at purchase location
            if ($status == 'completed') {
                foreach ($products as $product) {
                    if ($product['enable_stock']) {
                        $decrease_qty = $this->productUtil
                                    ->num_uf($product['quantity']);
                        if (! empty($product['base_unit_multiplier'])) {
                            $decrease_qty = $decrease_qty * $product['base_unit_multiplier'];
                        }

                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $sell_transfer->location_id,
                            $decrease_qty
                        );

                        $this->productUtil->updateProductQuantity(
                            $purchase_transfer->location_id,
                            $product['product_id'],
                            $product['variation_id'],
                            $decrease_qty,
                            0,
                            null,
                            false
                        );
                    }
                }

                //Adjust stock over selling if found
                $this->productUtil->adjustStockOverSelling($purchase_transfer);

                //Map sell lines with purchase lines
                $business = ['id' => $business_id,
                    'accounting_method' => $request->session()->get('business.accounting_method'),
                    'location_id' => $sell_transfer->location_id,
                ];
                $this->transactionUtil->mapPurchaseSell($business, $sell_transfer->sell_lines, 'purchase');
            }

            $this->transactionUtil->activityLog($sell_transfer, 'edited', $sell_transfer_before);

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_succesfully'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect('stock-transfers')->with('status', $output);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        if (! auth()->user()->can('stock_transfer.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $sell_transfer = Transaction::where('business_id', $business_id)
                    ->where('type', 'sell_transfer')
                    ->with(['sell_lines', 'sell_lines.product'])
                    ->findOrFail($id);

            $purchase_transfer = Transaction::where('business_id',
                    $business_id)
                    ->where('transfer_parent_id', $id)
                    ->where('type', 'purchase_transfer')
                    ->with(['purchase_lines'])
                    ->first();

            $status = $request->input('status');

            DB::beginTransaction();
            if ($status == 'completed' && $sell_transfer->status != 'completed') {
                foreach ($sell_transfer->sell_lines as $sell_line) {
                    if ($sell_line->product->enable_stock) {
                        $this->productUtil->decreaseProductQuantity(
                            $sell_line->product_id,
                            $sell_line->variation_id,
                            $sell_transfer->location_id,
                            $sell_line->quantity
                        );

                        $this->productUtil->updateProductQuantity(
                            $purchase_transfer->location_id,
                            $sell_line->product_id,
                            $sell_line->variation_id,
                            $sell_line->quantity,
                            0,
                            null,
                            false
                        );
                    }
                }

                //Adjust stock over selling if found
                $this->productUtil->adjustStockOverSelling($purchase_transfer);

                //Map sell lines with purchase lines
                $business = ['id' => $business_id,
                    'accounting_method' => $request->session()->get('business.accounting_method'),
                    'location_id' => $sell_transfer->location_id,
                ];
                $this->transactionUtil->mapPurchaseSell($business, $sell_transfer->sell_lines, 'purchase');
            }
            $purchase_transfer->status = $status == 'completed' ? 'received' : $status;
            $purchase_transfer->save();
            $sell_transfer->status = $status == 'completed' ? 'final' : $status;
            $sell_transfer->save();

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_succesfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return $output;
    }
}

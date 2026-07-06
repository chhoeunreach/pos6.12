<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Product;
use App\PurchaseLine;
use App\StockAdjustmentLine;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use App\Utils\TransactionUtil;
use App\Variation;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function stockSellReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('purchase_n_sell_report.view') && ! auth()->user()->can('sell.view') && ! auth()->user()->can('sell.create') && ! auth()->user()->can('direct_sell.access') && ! auth()->user()->can('view_own_sell_only')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $permitted_locations = auth()->user()->permitted_locations();

            $payments = DB::table('transaction_payments')
                ->select(
                    'transaction_id',
                    DB::raw("SUM(IF(method = 'cash', IF(is_return = 1, -1 * amount, amount), 0)) as cash"),
                    DB::raw("SUM(IF(method = 'custom_pay_1', IF(is_return = 1, -1 * amount, amount), 0)) as wing"),
                    DB::raw("SUM(IF(method = 'custom_pay_2', IF(is_return = 1, -1 * amount, amount), 0)) as aba"),
                    DB::raw("SUM(IF(method = 'custom_pay_3', IF(is_return = 1, -1 * amount, amount), 0)) as acleda"),
                    DB::raw("SUM(IF(method IN ('custom_pay_4', 'custom_pay_5'), IF(is_return = 1, -1 * amount, amount), 0)) as true_money"),
                    DB::raw("SUM(IF(method = 'card', IF(is_return = 1, -1 * amount, amount), 0)) as card"),
                    DB::raw("SUM(IF(method = 'other', IF(is_return = 1, -1 * amount, amount), 0)) as other"),
                    DB::raw("SUM(IF(method = 'custom_pay_6', IF(is_return = 1, -1 * amount, amount), 0)) as voido"),
                    DB::raw("SUM(IF(method = 'custom_pay_7', IF(is_return = 1, -1 * amount, amount), 0)) as monthly"),
                    DB::raw('SUM(IF(is_return = 1, -1 * amount, amount)) as paid')
                )
                ->whereNull('parent_id')
                ->groupBy('transaction_id');

            $purchase_costs = DB::table('transaction_sell_lines_purchase_lines as tspl')
                ->join('purchase_lines as pl', 'tspl.purchase_line_id', '=', 'pl.id')
                ->select(
                    'tspl.sell_line_id',
                    DB::raw('SUM((tspl.quantity - COALESCE(tspl.qty_returned, 0)) * pl.purchase_price_inc_tax) as purchase_total'),
                    DB::raw('SUM(tspl.quantity - COALESCE(tspl.qty_returned, 0)) as purchase_qty'),
                    DB::raw("GROUP_CONCAT(DISTINCT NULLIF(pl.lot_number, '') ORDER BY pl.lot_number SEPARATOR ', ') as lots")
                )
                ->whereNotNull('tspl.sell_line_id')
                ->groupBy('tspl.sell_line_id');

            $fifo_purchase_price_sql = "COALESCE(
                pc.purchase_total / NULLIF(pc.purchase_qty, 0),
                lot_pl.purchase_price_inc_tax,
                (
                    SELECT pl_fifo.purchase_price_inc_tax
                    FROM purchase_lines as pl_fifo
                    INNER JOIN transactions as t_fifo ON pl_fifo.transaction_id = t_fifo.id
                    WHERE pl_fifo.variation_id = transaction_sell_lines.variation_id
                        AND t_fifo.business_id = t.business_id
                        AND t_fifo.location_id = t.location_id
                        AND t_fifo.status = 'received'
                        AND t_fifo.type IN ('purchase', 'opening_stock')
                        AND t_fifo.transaction_date <= t.transaction_date
                    ORDER BY t_fifo.transaction_date ASC, pl_fifo.id ASC
                    LIMIT 1
                ),
                v.dpp_inc_tax,
                v.default_purchase_price,
                0
            )";
            $fifo_purchase_total_sql = "COALESCE(pc.purchase_total, transaction_sell_lines.quantity * ({$fifo_purchase_price_sql}), 0)";
            $line_sell_total_sql = '((transaction_sell_lines.quantity * transaction_sell_lines.unit_price_before_discount) - COALESCE(transaction_sell_lines.line_discount_amount, 0))';

            $sells = TransactionSellLine::join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('products as p', 'transaction_sell_lines.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'transaction_sell_lines.variation_id', '=', 'v.id')
                ->leftJoin('purchase_lines as lot_pl', 'transaction_sell_lines.lot_no_line_id', '=', 'lot_pl.id')
                ->leftJoin('customer_groups as tcg', 't.customer_group_id', '=', 'tcg.id')
                ->leftJoin('customer_groups as ccg', 'c.customer_group_id', '=', 'ccg.id')
                ->leftJoin('users as u', 't.created_by', '=', 'u.id')
                ->leftJoinSub($payments, 'tp', function ($join) {
                    $join->on('tp.transaction_id', '=', 't.id');
                })
                ->leftJoinSub($purchase_costs, 'pc', function ($join) {
                    $join->on('pc.sell_line_id', '=', 'transaction_sell_lines.id');
                })
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where(function ($query) {
                    $query->where('t.sub_type', '!=', 'project_invoice')
                        ->orWhereNull('t.sub_type');
                })
                ->whereNull('transaction_sell_lines.parent_sell_line_id')
                ->select(
                    'transaction_sell_lines.id',
                    't.id as transaction_id',
                    't.transaction_date',
                    't.invoice_no',
                    't.additional_notes',
                    't.staff_note',
                    't.final_total',
                    't.payment_status',
                    'c.mobile as phone',
                    'bl.name as location',
                    'p.name as product',
                    'v.sub_sku as sku',
                    DB::raw("COALESCE(pc.lots, NULLIF(lot_pl.lot_number, ''), '') as lots"),
                    'transaction_sell_lines.quantity',
                    'transaction_sell_lines.unit_price_before_discount as price',
                    DB::raw($line_sell_total_sql.' as total'),
                    DB::raw($fifo_purchase_price_sql.' as purchase_price'),
                    DB::raw($fifo_purchase_total_sql.' as purchase_total'),
                    DB::raw('('.$line_sell_total_sql.' - '.$fifo_purchase_total_sql.') as profit_loss'),
                    DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''), 'Walk-In Customer') as customer"),
                    DB::raw("COALESCE(NULLIF(TRIM(tcg.name), ''), NULLIF(TRIM(ccg.name), ''), 'លក់') as customer_group"),
                    DB::raw('COALESCE(tp.cash, 0) as cash'),
                    DB::raw('COALESCE(tp.wing, 0) as wing'),
                    DB::raw('COALESCE(tp.aba, 0) as aba'),
                    DB::raw('COALESCE(tp.acleda, 0) as acleda'),
                    DB::raw('COALESCE(tp.true_money, 0) as true_money'),
                    DB::raw('COALESCE(tp.card, 0) as card'),
                    DB::raw('COALESCE(tp.other, 0) as other'),
                    DB::raw('COALESCE(tp.voido, 0) as voido'),
                    DB::raw('COALESCE(tp.monthly, 0) as monthly'),
                    DB::raw('COALESCE(tp.paid, 0) as paid'),
                    DB::raw('(t.final_total - COALESCE(tp.paid, 0)) as due'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as user_name")
                );

            if ($permitted_locations != 'all') {
                $sells->whereIn('t.location_id', $permitted_locations);
            }

            if (! auth()->user()->can('direct_sell.view') && auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping', 'view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                $sells->where(function ($query) {
                    if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $query->where('t.created_by', request()->session()->get('user.id'));
                    }

                    if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $query->orWhere('t.commission_agent', request()->session()->get('user.id'));
                    }
                });
            }

            if (! empty(request()->input('location_id'))) {
                $location_id = request()->input('location_id');
                if (is_array($location_id)) {
                    $location_id = array_values(array_filter($location_id, function ($id) {
                        return $id !== 'all';
                    }));

                    if (! empty($location_id)) {
                        $sells->whereIn('t.location_id', $location_id);
                    }
                } elseif ($location_id !== 'all') {
                    $sells->where('t.location_id', $location_id);
                }
            }

            if (! empty(request()->input('customer_id'))) {
                $sells->where('t.contact_id', request()->input('customer_id'));
            }

            if (! empty(request()->input('payment_status'))) {
                $sells->where('t.payment_status', request()->input('payment_status'));
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $sells->whereDate('t.transaction_date', '>=', request()->input('start_date'))
                    ->whereDate('t.transaction_date', '<=', request()->input('end_date'));
            }

            return Datatables::of($sells)
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->addColumn('i_t', function ($row) {
                    $sell_note = trim((string) $row->additional_notes);
                    $staff_note_last4 = substr(trim((string) $row->staff_note), -4);
                    $i_t = trim($sell_note.($sell_note !== '' && $staff_note_last4 !== '' ? '-' : '').$staff_note_last4);
                    return $i_t !== '' ? $i_t : '-';
                })
                ->editColumn('quantity', function ($row) {
                    return '<span data-orig-value="'.$row->quantity.'">'.$this->transactionUtil->num_f($row->quantity, false).'</span>';
                })
                ->editColumn('price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->price.'">'.$row->price.'</span>';
                })
                ->editColumn('purchase_price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->purchase_price.'">'.$row->purchase_price.'</span>';
                })
                ->editColumn('total', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->total.'">'.$row->total.'</span>';
                })
                ->editColumn('profit_loss', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->profit_loss.'">'.$row->profit_loss.'</span>';
                })
                ->editColumn('cash', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->cash.'">'.$row->cash.'</span>';
                })
                ->editColumn('wing', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->wing.'">'.$row->wing.'</span>';
                })
                ->editColumn('aba', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->aba.'">'.$row->aba.'</span>';
                })
                ->editColumn('acleda', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->acleda.'">'.$row->acleda.'</span>';
                })
                ->editColumn('true_money', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->true_money.'">'.$row->true_money.'</span>';
                })
                ->editColumn('card', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->card.'">'.$row->card.'</span>';
                })
                ->editColumn('other', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->other.'">'.$row->other.'</span>';
                })
                ->editColumn('voido', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->voido.'">'.$row->voido.'</span>';
                })
                ->editColumn('monthly', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->monthly.'">'.$row->monthly.'</span>';
                })
                ->editColumn('paid', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->paid.'">'.$row->paid.'</span>';
                })
                ->editColumn('due', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->due.'">'.$row->due.'</span>';
                })
                ->rawColumns(['quantity', 'price', 'purchase_price', 'total', 'profit_loss', 'cash', 'wing', 'aba', 'acleda', 'true_money', 'card', 'other', 'voido', 'monthly', 'paid', 'due'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        return view('smartstockinventory::report.stock_sell')
            ->with(compact('business_locations', 'customers'));
    }

    public function stockPurchaseReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('purchase_n_sell_report.view') && ! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $permitted_locations = auth()->user()->permitted_locations();

            $payments = DB::table('transaction_payments')
                ->select(
                    'transaction_id',
                    DB::raw("SUM(IF(method = 'cash', IF(is_return = 1, -1 * amount, amount), 0)) as cash"),
                    DB::raw("SUM(IF(method = 'custom_pay_1', IF(is_return = 1, -1 * amount, amount), 0)) as wing"),
                    DB::raw("SUM(IF(method = 'custom_pay_2', IF(is_return = 1, -1 * amount, amount), 0)) as aba"),
                    DB::raw("SUM(IF(method = 'custom_pay_3', IF(is_return = 1, -1 * amount, amount), 0)) as acleda"),
                    DB::raw("SUM(IF(method IN ('custom_pay_4', 'custom_pay_5'), IF(is_return = 1, -1 * amount, amount), 0)) as true_money"),
                    DB::raw("SUM(IF(method = 'card', IF(is_return = 1, -1 * amount, amount), 0)) as card"),
                    DB::raw("SUM(IF(method = 'other', IF(is_return = 1, -1 * amount, amount), 0)) as other"),
                    DB::raw("SUM(IF(method = 'custom_pay_6', IF(is_return = 1, -1 * amount, amount), 0)) as voido"),
                    DB::raw("SUM(IF(method = 'custom_pay_7', IF(is_return = 1, -1 * amount, amount), 0)) as monthly"),
                    DB::raw('SUM(IF(is_return = 1, -1 * amount, amount)) as paid')
                )
                ->whereNull('parent_id')
                ->groupBy('transaction_id');

            $purchases = PurchaseLine::join('transactions as t', 'purchase_lines.transaction_id', '=', 't.id')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('products as p', 'purchase_lines.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'purchase_lines.variation_id', '=', 'v.id')
                ->leftJoinSub($payments, 'tp', function ($join) {
                    $join->on('tp.transaction_id', '=', 't.id');
                })
                ->where('t.business_id', $business_id)
                ->where('t.type', 'purchase')
                ->where('t.status', 'received')
                ->select(
                    'purchase_lines.id',
                    't.id as transaction_id',
                    't.transaction_date',
                    't.ref_no',
                    't.final_total',
                    't.payment_status',
                    'c.name as supplier',
                    'c.supplier_business_name',
                    'c.mobile as phone',
                    'bl.name as location',
                    'p.name as product',
                    'v.sub_sku as sku',
                    'purchase_lines.lot_number',
                    'purchase_lines.quantity',
                    'purchase_lines.purchase_price_inc_tax as purchase_price',
                    DB::raw('(purchase_lines.quantity * purchase_lines.purchase_price_inc_tax) as subtotal'),
                    DB::raw('COALESCE(tp.cash, 0) as cash'),
                    DB::raw('COALESCE(tp.wing, 0) as wing'),
                    DB::raw('COALESCE(tp.aba, 0) as aba'),
                    DB::raw('COALESCE(tp.acleda, 0) as acleda'),
                    DB::raw('COALESCE(tp.true_money, 0) as true_money'),
                    DB::raw('COALESCE(tp.card, 0) as card'),
                    DB::raw('COALESCE(tp.other, 0) as other'),
                    DB::raw('COALESCE(tp.voido, 0) as voido'),
                    DB::raw('COALESCE(tp.monthly, 0) as monthly'),
                    DB::raw('COALESCE(tp.paid, 0) as paid'),
                    DB::raw('(t.final_total - COALESCE(tp.paid, 0)) as due')
                );

            if ($permitted_locations != 'all') {
                $purchases->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $location_id = request()->input('location_id');
                if (is_array($location_id)) {
                    $location_id = array_values(array_filter($location_id, function ($id) {
                        return $id !== 'all';
                    }));

                    if (! empty($location_id)) {
                        $purchases->whereIn('t.location_id', $location_id);
                    }
                } elseif ($location_id !== 'all') {
                    $purchases->where('t.location_id', $location_id);
                }
            }

            if (! empty(request()->input('supplier_id'))) {
                $purchases->where('t.contact_id', request()->input('supplier_id'));
            }

            if (! empty(request()->input('payment_status'))) {
                $purchases->where('t.payment_status', request()->input('payment_status'));
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $purchases->whereDate('t.transaction_date', '>=', request()->input('start_date'))
                    ->whereDate('t.transaction_date', '<=', request()->input('end_date'));
            }

            return Datatables::of($purchases)
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('quantity', function ($row) {
                    return '<span data-orig-value="'.$row->quantity.'">'.$this->transactionUtil->num_f($row->quantity, false).'</span>';
                })
                ->editColumn('purchase_price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->purchase_price.'">'.$row->purchase_price.'</span>';
                })
                ->editColumn('subtotal', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->subtotal.'">'.$row->subtotal.'</span>';
                })
                ->editColumn('cash', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->cash.'">'.$row->cash.'</span>';
                })
                ->editColumn('wing', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->wing.'">'.$row->wing.'</span>';
                })
                ->editColumn('aba', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->aba.'">'.$row->aba.'</span>';
                })
                ->editColumn('acleda', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->acleda.'">'.$row->acleda.'</span>';
                })
                ->editColumn('true_money', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->true_money.'">'.$row->true_money.'</span>';
                })
                ->editColumn('card', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->card.'">'.$row->card.'</span>';
                })
                ->editColumn('other', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->other.'">'.$row->other.'</span>';
                })
                ->editColumn('voido', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->voido.'">'.$row->voido.'</span>';
                })
                ->editColumn('monthly', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->monthly.'">'.$row->monthly.'</span>';
                })
                ->editColumn('paid', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->paid.'">'.$row->paid.'</span>';
                })
                ->editColumn('due', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->due.'">'.$row->due.'</span>';
                })
                ->rawColumns(['quantity', 'purchase_price', 'subtotal', 'cash', 'wing', 'aba', 'acleda', 'true_money', 'card', 'other', 'voido', 'monthly', 'paid', 'due'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $suppliers = Contact::suppliersDropdown($business_id, false);

        return view('smartstockinventory::report.stock_purchase')
            ->with(compact('business_locations', 'suppliers'));
    }

    public function stockTransferReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('stock_transfer.view') && ! auth()->user()->can('stock_transfer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $query = TransactionSellLine::join(
                'transactions',
                'transaction_sell_lines.transaction_id',
                '=',
                'transactions.id'
            )
                ->join(
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
                ->join(
                    'products',
                    'transaction_sell_lines.product_id',
                    '=',
                    'products.id'
                )
                ->join(
                    'variations',
                    'transaction_sell_lines.variation_id',
                    '=',
                    'variations.id'
                )
                ->leftJoin(
                    'product_variations',
                    'variations.product_variation_id',
                    '=',
                    'product_variations.id'
                )
                ->leftJoin('users as sender', 'transactions.created_by', '=', 'sender.id')
                ->leftJoin('purchase_lines', 'transaction_sell_lines.lot_no_line_id', '=', 'purchase_lines.id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell_transfer');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->where(function ($q) use ($permitted_locations) {
                    $q->whereIn('transactions.location_id', $permitted_locations)
                        ->orWhereIn('t2.location_id', $permitted_locations);
                });
            }

            if (! auth()->user()->can('stock_transfer.view') && auth()->user()->can('stock_transfer.view_own')) {
                $query->where('transactions.created_by', $request->session()->get('user.id'));
            }

            $location_from_id = $request->get('location_from_id', null);
            if (! empty($location_from_id)) {
                $query->where('transactions.location_id', $location_from_id);
            }

            $location_to_id = $request->get('location_to_id', null);
            if (! empty($location_to_id)) {
                $query->where('t2.location_id', $location_to_id);
            }

            $sender_id = $request->get('sender_id', []);
            if (! empty($sender_id)) {
                $sender_ids = is_array($sender_id) ? $sender_id : [$sender_id];
                $sender_ids = array_filter($sender_ids);
                if (! empty($sender_ids)) {
                    $query->whereIn('transactions.created_by', $sender_ids);
                }
            }

            $start_date = $request->get('start_date', null);
            $end_date = $request->get('end_date', null);
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereDate('transactions.transaction_date', '>=', $start_date)
                    ->whereDate('transactions.transaction_date', '<=', $end_date);
            }

            $query->select(
                DB::raw('DATE_FORMAT(transactions.transaction_date, "%Y-%m-%d") as transaction_date'),
                'purchase_lines.lot_number as lot_number',
                'variations.sub_sku as sku',
                DB::raw("IF(products.type='variable', CONCAT(products.name, ' - ', COALESCE(product_variations.name, ''), ' - ', variations.name), products.name) as product_name"),
                'transaction_sell_lines.quantity as qty',
                'l1.name as location_from',
                'l2.name as location_to',
                'transactions.ref_no as invoice',
                DB::raw("CONCAT(COALESCE(sender.surname, ''),' ',COALESCE(sender.first_name, ''),' ',COALESCE(sender.last_name,'')) as sender_by"),
                'transactions.additional_notes as note'
            );

            $datatable = Datatables::of($query)
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('qty', function ($row) {
                    return $this->transactionUtil->num_f($row->qty, false, null, true);
                })
                ->filterColumn('sender_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(sender.surname, ''),' ',COALESCE(sender.first_name, ''),' ',COALESCE(sender.last_name,'')) like ?", ["%{$keyword}%"]);
                })
                ->rawColumns([])
                ->make(true);

            return $datatable;
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $users = User::forDropdown($business_id, false, false, false);

        return view('smartstockinventory::report.stock_transfer')
            ->with(compact('business_locations', 'users'));
    }

    public function stockPurchaserReturnReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('purchase_n_sell_report.view') && ! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $permitted_locations = auth()->user()->permitted_locations();

            $purchase_returns = PurchaseLine::join('transactions as t', 'purchase_lines.transaction_id', '=', 't.id')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('products as p', 'purchase_lines.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'purchase_lines.variation_id', '=', 'v.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'purchase_return')
                ->select(
                    DB::raw('DATE_FORMAT(t.transaction_date, "%Y-%m-%d") as date'),
                    't.ref_no',
                    'c.name as supplier',
                    'c.mobile as phone',
                    'purchase_lines.lot_number as lot_number',
                    'v.sub_sku as sku',
                    'p.name as product',
                    'purchase_lines.quantity',
                    'purchase_lines.purchase_price_inc_tax as purchase_price',
                    DB::raw('(purchase_lines.quantity * purchase_lines.purchase_price_inc_tax) as total'),
                    DB::raw('COALESCE(t.additional_notes, "") as reason'),
                    'bl.name as location'
                );

            if ($permitted_locations != 'all') {
                $purchase_returns->whereIn('t.location_id', $permitted_locations);
            }

            if (! auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
                $purchase_returns->where('t.created_by', request()->session()->get('user.id'));
            }

            if (! empty(request()->input('location_id'))) {
                $location_id = request()->input('location_id');
                if (is_array($location_id)) {
                    $location_id = array_values(array_filter($location_id, function ($id) {
                        return $id !== 'all';
                    }));
                    if (! empty($location_id)) {
                        $purchase_returns->whereIn('t.location_id', $location_id);
                    }
                } elseif ($location_id !== 'all') {
                    $purchase_returns->where('t.location_id', $location_id);
                }
            }

            if (! empty(request()->input('supplier_id'))) {
                $purchase_returns->where('t.contact_id', request()->input('supplier_id'));
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $purchase_returns->whereDate('t.transaction_date', '>=', request()->input('start_date'))
                    ->whereDate('t.transaction_date', '<=', request()->input('end_date'));
            }

            return Datatables::of($purchase_returns)
                ->editColumn('date', '{{@format_date($date)}}')
                ->editColumn('quantity', function ($row) {
                    return '<span data-orig-value="'.$row->quantity.'">'.$this->transactionUtil->num_f($row->quantity, false).'</span>';
                })
                ->editColumn('purchase_price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->purchase_price.'">'.$row->purchase_price.'</span>';
                })
                ->editColumn('total', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->total.'">'.$row->total.'</span>';
                })
                ->rawColumns(['quantity', 'purchase_price', 'total'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $suppliers = Contact::suppliersDropdown($business_id, false);

        return view('smartstockinventory::report.stock_purchaser_return')
            ->with(compact('business_locations', 'suppliers'));
    }

    public function stockSellReturnReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view') && ! auth()->user()->can('purchase_n_sell_report.view') && ! auth()->user()->can('sell.view') && ! auth()->user()->can('sell.create') && ! auth()->user()->can('direct_sell.access') && ! auth()->user()->can('view_own_sell_only')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $permitted_locations = auth()->user()->permitted_locations();

            $sell_returns = TransactionSellLine::join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('products as p', 'transaction_sell_lines.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'transaction_sell_lines.variation_id', '=', 'v.id')
                ->leftJoin('purchase_lines', 'transaction_sell_lines.lot_no_line_id', '=', 'purchase_lines.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell_return')
                ->whereNull('transaction_sell_lines.parent_sell_line_id')
                ->select(
                    DB::raw('DATE_FORMAT(t.transaction_date, "%Y-%m-%d") as date'),
                    't.invoice_no',
                    DB::raw("COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), ''), 'Walk-In Customer') as customer"),
                    'c.mobile as phone',
                    'purchase_lines.lot_number as lot_number',
                    'v.sub_sku as sku',
                    'p.name as product',
                    'transaction_sell_lines.quantity',
                    'transaction_sell_lines.unit_price_before_discount as unit_price',
                    DB::raw('(transaction_sell_lines.quantity * transaction_sell_lines.unit_price_before_discount) as total'),
                    DB::raw('COALESCE(t.additional_notes, "") as reason'),
                    'bl.name as location'
                );

            if ($permitted_locations != 'all') {
                $sell_returns->whereIn('t.location_id', $permitted_locations);
            }

            if (! auth()->user()->can('direct_sell.view') && auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping', 'view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                $sell_returns->where(function ($query) {
                    if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $query->where('t.created_by', request()->session()->get('user.id'));
                    }
                    if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $query->orWhere('t.commission_agent', request()->session()->get('user.id'));
                    }
                });
            }

            if (! empty(request()->input('location_id'))) {
                $location_id = request()->input('location_id');
                if (is_array($location_id)) {
                    $location_id = array_values(array_filter($location_id, function ($id) {
                        return $id !== 'all';
                    }));
                    if (! empty($location_id)) {
                        $sell_returns->whereIn('t.location_id', $location_id);
                    }
                } elseif ($location_id !== 'all') {
                    $sell_returns->where('t.location_id', $location_id);
                }
            }

            if (! empty(request()->input('customer_id'))) {
                $sell_returns->where('t.contact_id', request()->input('customer_id'));
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $sell_returns->whereDate('t.transaction_date', '>=', request()->input('start_date'))
                    ->whereDate('t.transaction_date', '<=', request()->input('end_date'));
            }

            return Datatables::of($sell_returns)
                ->editColumn('date', '{{@format_date($date)}}')
                ->editColumn('quantity', function ($row) {
                    return '<span data-orig-value="'.$row->quantity.'">'.$this->transactionUtil->num_f($row->quantity, false).'</span>';
                })
                ->editColumn('unit_price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->unit_price.'">'.$row->unit_price.'</span>';
                })
                ->editColumn('total', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true" data-orig-value="'.$row->total.'">'.$row->total.'</span>';
                })
                ->rawColumns(['quantity', 'unit_price', 'total'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        return view('smartstockinventory::report.stock_sell_return')
            ->with(compact('business_locations', 'customers'));
    }

    public function stockAdjustmentReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $permitted_locations = auth()->user()->permitted_locations();

            $adjustments = StockAdjustmentLine::join('transactions as t', 'stock_adjustment_lines.transaction_id', '=', 't.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->leftJoin('products as p', 'stock_adjustment_lines.product_id', '=', 'p.id')
                ->leftJoin('variations as v', 'stock_adjustment_lines.variation_id', '=', 'v.id')
                ->leftJoin('users as u', 't.created_by', '=', 'u.id')
                ->leftJoin('purchase_lines', 'stock_adjustment_lines.lot_no_line_id', '=', 'purchase_lines.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'stock_adjustment')
                ->select(
                    DB::raw('DATE_FORMAT(t.transaction_date, "%Y-%m-%d") as date'),
                    'bl.name as location',
                    't.ref_no as invoice_no',
                    'purchase_lines.lot_number as lot_number',
                    'v.sub_sku as sku',
                    DB::raw("CONCAT(COALESCE(p.name, ''), ' ', COALESCE(v.name, '')) as product"),
                    DB::raw('0 as previous_qty'),
                    'stock_adjustment_lines.quantity as adjusted_qty',
                    'stock_adjustment_lines.quantity as difference',
                    DB::raw('COALESCE(t.additional_notes, "") as reason'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as adjusted_by"),
                    't.additional_notes as note'
                );

            if ($permitted_locations != 'all') {
                $adjustments->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $location_id = request()->input('location_id');
                if (is_array($location_id)) {
                    $location_id = array_values(array_filter($location_id, function ($id) {
                        return $id !== 'all';
                    }));
                    if (! empty($location_id)) {
                        $adjustments->whereIn('t.location_id', $location_id);
                    }
                } elseif ($location_id !== 'all') {
                    $adjustments->where('t.location_id', $location_id);
                }
            }

            if (! empty(request()->input('start_date')) && ! empty(request()->input('end_date'))) {
                $adjustments->whereDate('t.transaction_date', '>=', request()->input('start_date'))
                    ->whereDate('t.transaction_date', '<=', request()->input('end_date'));
            }

            return Datatables::of($adjustments)
                ->editColumn('date', '{{@format_date($date)}}')
                ->editColumn('adjusted_qty', function ($row) {
                    return '<span data-orig-value="'.$row->adjusted_qty.'">'.$this->transactionUtil->num_f($row->adjusted_qty, false).'</span>';
                })
                ->editColumn('difference', function ($row) {
                    return '<span data-orig-value="'.$row->difference.'">'.$this->transactionUtil->num_f($row->difference, false).'</span>';
                })
                ->editColumn('previous_qty', function ($row) {
                    return '<span data-orig-value="'.$row->previous_qty.'">'.$this->transactionUtil->num_f($row->previous_qty, false).'</span>';
                })
                ->rawColumns(['adjusted_qty', 'difference', 'previous_qty'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        return view('smartstockinventory::report.stock_adjustment')
            ->with(compact('business_locations'));
    }
}

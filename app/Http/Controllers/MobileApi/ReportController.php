<?php

namespace App\Http\Controllers\MobileApi;

use App\Contact;
use App\Product;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\TransactionSellLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Reports
 * Business reports and analytics
 */
class ReportController extends BaseController
{
    protected $transactionUtil;
    protected $productUtil;
    protected $businessUtil;

    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil, BusinessUtil $businessUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
    }

    protected function applyCommonFilters($query, Request $request)
    {
        $business_id = $this->getBusinessId();
        $query->where('transactions.business_id', $business_id);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        if ($request->filled('location_id')) {
            $query->where('transactions.location_id', $request->location_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transactions.transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transactions.transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('user_id')) {
            $query->where('transactions.created_by', $request->user_id);
        }

        return $query;
    }

    public function sales(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->with(['contact', 'location', 'createdByUser', 'payment_lines']);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('contact_id', $request->customer_id);
        }
        if ($request->filled('user_id')) {
            $query->where('created_by', $request->user_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $sales = $query->select(
            'transactions.*',
            DB::raw("(SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id = transactions.id) as total_paid")
        )->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_sales' => $sales->count(),
            'total_amount' => $sales->sum('final_total'),
            'total_paid' => $sales->sum('total_paid'),
            'total_due' => $sales->sum('final_total') - $sales->sum('total_paid'),
        ];

        return $this->success([
            'summary' => $summary,
            'sales' => $sales,
        ]);
    }

    public function products(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final');

        $query = $this->applyCommonFilters($query, $request);

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('products.brand_id', $request->brand_id);
        }

        $products = $query->select(
            'products.id',
            'products.name',
            'products.sku',
            DB::raw('SUM(transaction_sell_lines.quantity) as total_quantity'),
            DB::raw('SUM(transaction_sell_lines.unit_price_inc_tax * transaction_sell_lines.quantity) as total_amount')
        )
        ->groupBy('products.id', 'products.name', 'products.sku')
        ->orderBy('total_amount', 'desc')
        ->get();

        return $this->success($products);
    }

    public function customersDue(Request $request)
    {
        $business_id = $this->getBusinessId();

        $customers = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->where('balance', '>', 0)
            ->select('id', 'name', 'mobile', 'email', 'balance', 'credit_limit')
            ->orderBy('balance', 'desc')
            ->get();

        return $this->success([
            'total_due' => $customers->sum('balance'),
            'customers' => $customers,
        ]);
    }

    public function suppliersDue(Request $request)
    {
        $business_id = $this->getBusinessId();

        $suppliers = Contact::where('business_id', $business_id)
            ->whereIn('type', ['supplier', 'both'])
            ->where('balance', '<', 0)
            ->select('id', 'name', 'supplier_business_name', 'mobile', 'email', DB::raw('ABS(balance) as due_amount'))
            ->orderBy('due_amount', 'desc')
            ->get();

        return $this->success([
            'total_due' => $suppliers->sum('due_amount'),
            'suppliers' => $suppliers,
        ]);
    }

    public function stock(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $query = Product::where('products.business_id', $business_id)
            ->where('products.enable_stock', 1)
            ->active();

        if ($location_id) {
            $query->forLocation($location_id);
        }
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('brand_id')) {
            $query->where('products.brand_id', $request->brand_id);
        }

        $products = $query->with(['variations.variation_location_details' => function ($q) use ($location_id) {
            if ($location_id) {
                $q->where('location_id', $location_id);
            }
        }])->get();

        $total_stock_value = 0;
        $stock_data = [];

        foreach ($products as $product) {
            $product_stock = 0;
            $product_value = 0;
            foreach ($product->variations as $variation) {
                foreach ($variation->variation_location_details as $detail) {
                    $product_stock += $detail->qty_available;
                    $product_value += $detail->qty_available * $variation->default_purchase_price;
                }
            }
            $total_stock_value += $product_value;

            $stock_data[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'total_qty' => $product_stock,
                'stock_value' => $product_value,
                'alert_quantity' => $product->alert_quantity,
            ];
        }

        return $this->success([
            'total_stock_value' => $total_stock_value,
            'products' => $stock_data,
        ]);
    }

    public function payments(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = TransactionPayment::whereHas('transaction', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->with(['transaction.contact', 'created_user', 'payment_account']);

        if ($request->filled('start_date')) {
            $query->whereDate('paid_on', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('paid_on', '<=', $request->end_date);
        }
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('contact_id')) {
            $query->where('payment_for', $request->contact_id);
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'by_method' => $payments->groupBy('method')->map(function ($items, $method) {
                return ['method' => $method, 'count' => $items->count(), 'total' => $items->sum('amount')];
            })->values(),
        ];

        return $this->success([
            'summary' => $summary,
            'payments' => $payments,
        ]);
    }

    public function expenses(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'expense')
            ->with(['location']);

        $query = $this->applyCommonFilters($query, $request);

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        $expenses = $query->orderBy('created_at', 'desc')->get();

        return $this->success([
            'total_expenses' => $expenses->sum('final_total'),
            'count' => $expenses->count(),
            'expenses' => $expenses,
        ]);
    }

    public function purchases(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->with(['contact', 'location']);

        $query = $this->applyCommonFilters($query, $request);

        if ($request->filled('supplier_id')) {
            $query->where('contact_id', $request->supplier_id);
        }

        $purchases = $query->orderBy('created_at', 'desc')->get();

        $total_paid = 0;
        foreach ($purchases as $p) {
            $total_paid += $p->payment_lines->sum('amount');
        }

        return $this->success([
            'total_purchase' => $purchases->sum('final_total'),
            'total_paid' => $total_paid,
            'total_due' => $purchases->sum('final_total') - $total_paid,
            'count' => $purchases->count(),
            'purchases' => $purchases,
        ]);
    }

    public function profitLoss(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $profit_data = $this->transactionUtil->getProfitLossDetails(
            $business_id, $location_id, $start_date, $end_date
        );

        return $this->success($profit_data);
    }

    public function localCashier(Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $request->input('user_id');
        $location_id = $request->input('location_id');
        $start_date = $request->input('start_date', Carbon::now()->startOfDay()->format('Y-m-d'));
        $end_date = $request->input('end_date', Carbon::now()->endOfDay()->format('Y-m-d'));

        $permitted_locations = $this->getPermittedLocations();
        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('Invalid location');
        }

        $sell_query = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final');

        if ($location_id) {
            $sell_query->where('location_id', $location_id);
        } elseif ($permitted_locations != 'all') {
            $sell_query->whereIn('location_id', $permitted_locations);
        }
        if ($user_id) {
            $sell_query->where('created_by', $user_id);
        }
        $sell_query->whereDate('transaction_date', '>=', $start_date)
            ->whereDate('transaction_date', '<=', $end_date);

        $sales = $sell_query->get();
        $total_sale = $sales->sum('final_total');

        $payment_query = TransactionPayment::whereHas('transaction', function ($q) use ($business_id, $location_id, $user_id, $start_date, $end_date, $permitted_locations) {
            $q->where('business_id', $business_id)->where('type', 'sell')->where('status', 'final');
            if ($location_id) {
                $q->where('location_id', $location_id);
            } elseif ($permitted_locations != 'all') {
                $q->whereIn('location_id', $permitted_locations);
            }
            if ($user_id) {
                $q->where('created_by', $user_id);
            }
            $q->whereDate('transaction_date', '>=', $start_date)
                ->whereDate('transaction_date', '<=', $end_date);
        });

        $customer_payment = (clone $payment_query)->sum('amount');

        $expense_query = Transaction::where('business_id', $business_id)
            ->where('type', 'expense');
        if ($location_id) {
            $expense_query->where('location_id', $location_id);
        } elseif ($permitted_locations != 'all') {
            $expense_query->whereIn('location_id', $permitted_locations);
        }
        if ($user_id) {
            $expense_query->where('created_by', $user_id);
        }
        $expense_query->whereDate('transaction_date', '>=', $start_date)
            ->whereDate('transaction_date', '<=', $end_date);
        $expenses = $expense_query->sum('final_total');

        $sell_return_paid = $this->transactionUtil->getTotalSellReturnPaid($business_id, $start_date, $end_date, $location_id);
        $actual_income = $total_sale - $sell_return_paid;

        $due = Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->where('balance', '>', 0)
            ->sum('balance');

        $user_summary = $sales->groupBy('created_by')->map(function ($items, $key) {
            $user = \App\User::find($key);
            return [
                'user_id' => $key,
                'user_name' => $user ? $user->user_full_name : 'Unknown',
                'total_sale' => $items->sum('final_total'),
                'count' => $items->count(),
            ];
        })->values();

        $location_summary = $sales->groupBy('location_id')->map(function ($items, $key) {
            $location = \App\BusinessLocation::find($key);
            return [
                'location_id' => $key,
                'location_name' => $location ? $location->name : 'Unknown',
                'total_sale' => $items->sum('final_total'),
                'count' => $items->count(),
            ];
        })->values();

        $customer_group_summary = $sales->groupBy('customer_group_id')->map(function ($items, $key) {
            $group = \App\CustomerGroup::find($key);
            return [
                'customer_group_id' => $key,
                'customer_group_name' => $group ? $group->name : 'General',
                'total_sale' => $items->sum('final_total'),
                'count' => $items->count(),
            ];
        })->values();

        $payment_method_summary = TransactionPayment::whereHas('transaction', function ($q) use ($business_id, $location_id, $start_date, $end_date, $permitted_locations) {
            $q->where('business_id', $business_id)->where('type', 'sell')->where('status', 'final');
            if ($location_id) {
                $q->where('location_id', $location_id);
            } elseif ($permitted_locations != 'all') {
                $q->whereIn('location_id', $permitted_locations);
            }
            $q->whereDate('transaction_date', '>=', $start_date)->whereDate('transaction_date', '<=', $end_date);
        })
        ->select('method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
        ->groupBy('method')
        ->get();

        $sale_detail_rows = $sales->map(function ($sale) {
            return [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'transaction_date' => $sale->transaction_date,
                'final_total' => $sale->final_total,
                'payment_status' => $sale->payment_status,
            ];
        });

        $customer_payment_detail_rows = TransactionPayment::whereHas('transaction', function ($q) use ($business_id, $location_id, $start_date, $end_date, $permitted_locations) {
            $q->where('business_id', $business_id)->where('type', 'sell')->where('status', 'final');
            if ($location_id) {
                $q->where('location_id', $location_id);
            } elseif ($permitted_locations != 'all') {
                $q->whereIn('location_id', $permitted_locations);
            }
            $q->whereDate('transaction_date', '>=', $start_date)->whereDate('transaction_date', '<=', $end_date);
        })
        ->with(['transaction', 'created_user'])
        ->get()
        ->map(function ($payment) {
            return [
                'id' => $payment->id,
                'payment_ref_no' => $payment->payment_ref_no,
                'paid_on' => $payment->paid_on,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'created_by' => $payment->created_user->user_full_name ?? '',
                'invoice_no' => $payment->transaction->invoice_no ?? '',
            ];
        });

        $collection_payment_detail_rows = [];

        $expense_detail_rows = $expense_query->get()->map(function ($expense) {
            return [
                'id' => $expense->id,
                'ref_no' => $expense->ref_no,
                'transaction_date' => $expense->transaction_date,
                'final_total' => $expense->final_total,
                'category' => $expense->expense_category->name ?? '',
                'additional_notes' => $expense->additional_notes,
            ];
        });

        return $this->success([
            'total_sale' => $total_sale,
            'actual_income' => $actual_income,
            'customer_payment' => $customer_payment,
            'collection_payment' => 0,
            'expenses' => $expenses,
            'due' => $due,
            'user_summary' => $user_summary,
            'location_summary' => $location_summary,
            'customer_group_summary' => $customer_group_summary,
            'brand_summary' => [],
            'payment_method_summary' => $payment_method_summary,
            'sale_detail_rows' => $sale_detail_rows,
            'customer_payment_detail_rows' => $customer_payment_detail_rows,
            'collection_payment_detail_rows' => $collection_payment_detail_rows,
            'expense_detail_rows' => $expense_detail_rows,
        ]);
    }
}

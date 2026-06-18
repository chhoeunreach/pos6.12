<?php

namespace App\Http\Controllers\MobileApi;

use App\Utils\TransactionUtil;
use App\Utils\ProductUtil;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;

/**
 * @group Dashboard
 * Dashboard metrics and overview data
 */
class DashboardController extends BaseController
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

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('You do not have access to this location');
        }

        $permitted_locations = $this->getPermittedLocations();

        $sell_totals = $this->transactionUtil->getSellTotals(
            $business_id, $start_date, $end_date, $location_id, $user_id
        );

        $total_sale = $sell_totals['final_total'] ?? 0;
        $total_sell_return_paid = $this->transactionUtil->getTotalSellReturnPaid(
            $business_id, $start_date, $end_date, $location_id
        );
        $actual_income = $total_sale - $total_sell_return_paid;

        $expense_report = $this->transactionUtil->getExpenseReport(
            $business_id, $location_id, $start_date, $end_date, $user_id
        );
        $expenses = $expense_report['total_expense'] ?? 0;

        $total_purchase = $this->transactionUtil->getPurchaseTotals(
            $business_id, $start_date, $end_date, $location_id, $user_id
        );
        $total_purchase = $total_purchase['final_total'] ?? 0;

        $customer_payment = $this->transactionUtil->getTotalPaymentWithCommission(
            $business_id, $start_date, $end_date, $location_id, $user_id
        );

        $collection_payment = 0;
        $collection_payment_query = \App\Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->where('payment_status', '!=', 'paid');

        if ($location_id) {
            $collection_payment_query->where('location_id', $location_id);
        } elseif ($permitted_locations != 'all') {
            $collection_payment_query->whereIn('location_id', $permitted_locations);
        }

        if ($start_date && $end_date) {
            $collection_payment_query->whereBetween('transaction_date', [$start_date, $end_date]);
        }

        $due = \App\Contact::where('business_id', $business_id)
            ->whereIn('type', ['customer', 'both'])
            ->where('balance', '>', 0)
            ->sum('balance');

        $low_stock_count = 0;
        $low_stock_query = \App\Product::where('business_id', $business_id)
            ->where('enable_stock', 1)
            ->where('is_inactive', 0)
            ->active();

        if ($permitted_locations != 'all') {
            $low_stock_query->whereHas('product_locations', function ($q) use ($permitted_locations) {
                $q->whereIn('location_id', $permitted_locations);
            });
        }

        $products = $low_stock_query->select('id', 'name', 'sku', 'alert_quantity')
            ->with(['variations.variation_location_details' => function ($q) use ($permitted_locations) {
                if ($permitted_locations != 'all') {
                    $q->whereIn('location_id', $permitted_locations);
                }
            }])
            ->get();

        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                foreach ($variation->variation_location_details as $detail) {
                    if ($detail->qty_available <= $product->alert_quantity) {
                        $low_stock_count++;
                    }
                }
            }
        }

        $recent_sales_query = \App\Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final');

        if ($location_id) {
            $recent_sales_query->where('location_id', $location_id);
        } elseif ($permitted_locations != 'all') {
            $recent_sales_query->whereIn('location_id', $permitted_locations);
        }

        $recent_sales = $recent_sales_query->with(['contact:id,name', 'createdByUser:id,surname,first_name,last_name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_no' => $sale->invoice_no,
                    'final_total' => $sale->final_total,
                    'payment_status' => $sale->payment_status,
                    'transaction_date' => $sale->transaction_date,
                    'contact_name' => $sale->contact->name ?? '',
                    'created_by' => $sale->createdByUser->user_full_name ?? '',
                ];
            });

        $top_products_query = \App\TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_sell_lines.product_id', '=', 'products.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final');

        if ($location_id) {
            $top_products_query->where('transactions.location_id', $location_id);
        } elseif ($permitted_locations != 'all') {
            $top_products_query->whereIn('transactions.location_id', $permitted_locations);
        }

        if ($start_date && $end_date) {
            $top_products_query->whereBetween('transactions.transaction_date', [$start_date, $end_date]);
        }

        $top_products = $top_products_query->selectRaw('products.name, SUM(transaction_sell_lines.quantity) as total_qty, SUM(transaction_sell_lines.unit_price_inc_tax * transaction_sell_lines.quantity) as total_amount')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_amount', 'desc')
            ->limit(5)
            ->get();

        return $this->success([
            'total_sale' => $total_sale,
            'actual_income' => $actual_income,
            'customer_payment' => $customer_payment,
            'collection_payment' => $collection_payment,
            'expenses' => $expenses,
            'due' => $due,
            'low_stock_count' => $low_stock_count,
            'recent_sales' => $recent_sales,
            'top_products' => $top_products,
        ]);
    }
}

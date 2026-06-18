<?php

namespace App\Http\Controllers\MobileApi;

use App\BusinessLocation;
use App\Contact;
use App\Http\Requests\MobileApi\CreatePosSaleRequest;
use App\Http\Resources\Mobile\ProductResource;
use App\Http\Resources\Mobile\TransactionResource;
use App\Product;
use App\TaxRate;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group POS
 * Point of Sale operations
 */
class PosController extends BaseController
{
    protected $transactionUtil;
    protected $productUtil;
    protected $businessUtil;
    protected $moduleUtil;
    protected $contactUtil;
    protected $dummyPaymentLine;

    public function __construct(
        TransactionUtil $transactionUtil,
        ProductUtil $productUtil,
        BusinessUtil $businessUtil,
        ModuleUtil $moduleUtil,
        ContactUtil $contactUtil
    ) {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
        $this->contactUtil = $contactUtil;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => ''];
    }

    public function settings(Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $business = \App\Business::with('currency')->findOrFail($business_id);

        $locations = $this->getPermittedLocations();
        $location_query = BusinessLocation::where('business_id', $business_id)->active();
        if ($locations != 'all') {
            $location_query->whereIn('id', $locations);
        }
        $business_locations = $location_query->get();

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $tax_rates = TaxRate::where('business_id', $business_id)->get();
        $payment_types = $this->getPaymentTypes();

        $currencies = \App\Currency::all();

        $business_locations_array = [];
        foreach ($business_locations as $loc) {
            $business_locations_array[] = [
                'id' => $loc->id,
                'name' => $loc->name,
                'location_id' => $loc->location_id,
                'selling_price_group_id' => $loc->selling_price_group_id,
                'default_payment_accounts' => $loc->default_payment_accounts,
                'invoice_scheme_id' => $loc->invoice_scheme_id,
                'invoice_layout_id' => $loc->invoice_layout_id,
                'sale_invoice_scheme_id' => $loc->sale_invoice_scheme_id,
            ];
        }

        return $this->success([
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'currency' => $business->currency,
            ],
            'locations' => $business_locations_array,
            'walk_in_customer' => $walk_in_customer,
            'tax_rates' => $tax_rates,
            'payment_types' => $payment_types,
            'currencies' => $currencies,
        ]);
    }

    public function products(Request $request)
    {
        $business_id = $this->getBusinessId();
        $location_id = $request->input('location_id');

        if ($location_id && !$this->checkLocationAccess($location_id)) {
            return $this->unauthorized('You do not have access to this location');
        }

        $query = Product::where('products.business_id', $business_id)
            ->active()
            ->productForSales()
            ->with(['variations.product_variation', 'variations.variation_location_details' => function ($q) use ($location_id) {
                if ($location_id) {
                    $q->where('location_id', $location_id);
                }
            }, 'brand', 'category', 'unit']);

        $category_id = $request->input('category_id');
        $brand_id = $request->input('brand_id');
        $search = $request->input('search');

        if ($category_id) {
            $query->where('products.category_id', $category_id);
        }
        if ($brand_id) {
            $query->where('products.brand_id', $brand_id);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        if ($location_id) {
            $query->forLocation($location_id);
        }

        $perPage = $request->input('per_page', 50);
        $products = $query->orderBy('products.name')->paginate($perPage);

        return $this->success($products);
    }

    public function validateCart(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variation_id' => 'required|exists:variations,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_price_inc_tax' => 'required|numeric|min:0',
            'products.*.item_tax' => 'nullable|numeric',
            'products.*.tax_id' => 'nullable|exists:tax_rates,id',
            'products.*.line_discount_type' => 'nullable|in:fixed,percentage',
            'products.*.line_discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
            'location_id' => 'nullable|exists:business_locations,id',
        ]);

        $products_data = $request->input('products');

        $errors = [];
        $warnings = [];

        foreach ($products_data as $index => $product) {
            $variation = \App\Variation::with('product')->find($product['variation_id']);
            if (!$variation) {
                $errors[] = "Product at index {$index} not found.";
                continue;
            }

            if ($request->location_id) {
                $stock = \App\VariationLocationDetails::where('variation_id', $product['variation_id'])
                    ->where('location_id', $request->location_id)
                    ->first();

                if ($stock && $variation->product->enable_stock && $product['quantity'] > $stock->qty_available) {
                    $warnings[] = "{$variation->product->name}: Requested {$product['quantity']} but only {$stock->qty_available} available.";
                }
            }
        }

        $discount = [
            'discount_type' => $request->input('discount_type'),
            'discount_amount' => $request->input('discount_amount', 0),
        ];
        $invoice_total = $this->productUtil->calculateInvoiceTotal($products_data, $request->input('tax_rate_id'), $discount);

        return $this->success([
            'total_before_tax' => $invoice_total['total_before_tax'],
            'tax' => $invoice_total['tax'],
            'discount' => $invoice_total['discount'],
            'final_total' => $invoice_total['final_total'],
            'item_count' => count($products_data),
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    public function sales(CreatePosSaleRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('You do not have access to this location');
        }

        $contact = Contact::where('business_id', $business_id)->find($input['contact_id']);
        if (!$contact) {
            return $this->error('Contact not found', 404);
        }

        try {
            DB::beginTransaction();

            if (empty($input['transaction_date'])) {
                $input['transaction_date'] = Carbon::now()->toDateTimeString();
            } else {
                $input['transaction_date'] = $this->productUtil->uf_date($input['transaction_date'], true);
            }

            $input['status'] = $input['status'] ?? 'final';
            if ($input['status'] == 'quotation') {
                $input['status'] = 'draft';
                $input['is_quotation'] = 1;
                $input['sub_status'] = 'quotation';
            }

            $input['is_direct_sale'] = 0;
            $input['commission_agent'] = $input['commission_agent'] ?? null;

            $discount = [
                'discount_type' => $input['discount_type'] ?? null,
                'discount_amount' => $input['discount_amount'] ?? 0,
            ];
            $invoice_total = $this->productUtil->calculateInvoiceTotal(
                $input['products'],
                $input['tax_rate_id'] ?? null,
                $discount
            );

            $cg = $this->contactUtil->getCustomerGroup($business_id, $input['contact_id']);
            $input['customer_group_id'] = $cg->id ?? null;

            $transaction = $this->transactionUtil->createSellTransaction($business_id, $input, $invoice_total, $user_id);

            $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $input['location_id']);

            if (!empty($input['payments']) && $input['status'] == 'final') {
                $is_credit_sale = isset($input['is_credit_sale']) && $input['is_credit_sale'] == 1;
                if (!$is_credit_sale) {
                    $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payments'], $business_id, $user_id);
                }
            }

            if ($input['status'] == 'final') {
                $business = \App\Business::find($business_id);
                \App\Business::update_business($business_id, [
                    'default_profit_percent' => $business->default_profit_percent,
                ]);
            }

            $this->transactionUtil->activityLog($transaction, 'added');

            DB::commit();

            $transaction->load(['contact', 'location', 'createdByUser', 'payment_lines', 'sell_lines.product', 'sell_lines.variations']);

            return $this->success(new TransactionResource($transaction), 'Sale created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create sale: ' . $e->getMessage(), 500);
        }
    }

    public function receipt($transaction_id, Request $request)
    {
        $business_id = $this->getBusinessId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('id', $transaction_id)
            ->with([
                'contact',
                'location',
                'sell_lines.product',
                'sell_lines.variations',
                'payment_lines',
                'sales_person',
            ])
            ->first();

        if (!$transaction) {
            return $this->notFound('Transaction not found');
        }

        $location_details = \App\BusinessLocation::find($transaction->location_id);
        $receipt_printer_type = $location_details->receipt_printer_type ?? 'browser';
        $invoice_layout = !empty($transaction->location->invoice_layout_id)
            ? \App\InvoiceLayout::find($transaction->location->invoice_layout_id)
            : null;

        $receipt = $this->transactionUtil->getReceiptDetails(
            $transaction_id,
            $transaction->location_id,
            $invoice_layout,
            \App\Business::find($business_id),
            $location_details,
            $receipt_printer_type
        );

        return $this->success([
            'transaction' => new TransactionResource($transaction),
            'receipt' => $receipt,
            'html_content' => view('sale_pos.receipt', $receipt)->render() ?? '',
        ]);
    }

    protected function getPaymentTypes()
    {
        return [
            'cash' => 'Cash',
            'card' => 'Card',
            'cheque' => 'Cheque',
            'bank_transfer' => 'Bank Transfer',
            'advance' => 'Advance',
            'custom_pay_1' => 'Custom 1',
            'custom_pay_2' => 'Custom 2',
            'custom_pay_3' => 'Custom 3',
            'custom_pay_4' => 'Custom 4',
            'custom_pay_5' => 'Custom 5',
            'custom_pay_6' => 'Custom 6',
            'custom_pay_7' => 'Custom 7',
        ];
    }
}

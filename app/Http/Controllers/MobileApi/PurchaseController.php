<?php

namespace App\Http\Controllers\MobileApi;

use App\Http\Requests\MobileApi\CreatePurchaseRequest;
use App\Http\Requests\MobileApi\UpdatePurchaseRequest;
use App\Http\Resources\Mobile\TransactionResource;
use App\PurchaseLine;
use App\Transaction;
use App\AccountTransaction;
use App\TransactionPayment;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Purchases
 * Purchase transaction management
 */
class PurchaseController extends BaseController
{
    protected $transactionUtil;
    protected $productUtil;
    protected $moduleUtil;

    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request)
    {
        $business_id = $this->getBusinessId();

        $query = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['purchase', 'purchase_return'])
            ->with(['contact', 'location', 'createdByUser', 'payment_lines']);

        $permitted_locations = $this->getPermittedLocations();
        if ($permitted_locations != 'all') {
            $query->whereIn('location_id', $permitted_locations);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('supplier_id')) {
            $query->where('contact_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $perPage = $request->input('per_page', 20);
        $purchases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success(TransactionResource::collection($purchases));
    }

    public function show($id)
    {
        $business_id = $this->getBusinessId();

        $purchase = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['purchase', 'purchase_return'])
            ->with([
                'contact', 'location', 'createdByUser', 'payment_lines.payment_account',
                'purchase_lines.product', 'purchase_lines.variations', 'purchase_lines.line_tax',
            ])
            ->findOrFail($id);

        return $this->success(new TransactionResource($purchase));
    }

    public function store(CreatePurchaseRequest $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('Invalid location');
        }

        try {
            DB::beginTransaction();

            $input['contact_id'] = $input['contact_id'] ?? $input['supplier_id'];
            $input['transaction_date'] = $this->parseMobileDate($input['transaction_date'], true);
            $input['type'] = 'purchase';
            $input['created_by'] = $user_id;
            $input['business_id'] = $business_id;
            $input['products'] = $this->normalizePurchaseProducts($input['products']);
            $totals = $this->calculatePurchaseTotals($input);

            $ref_count = $this->productUtil->setAndGetReferenceCount('purchase', $business_id);

            $transaction_data = [
                'business_id' => $business_id,
                'location_id' => $input['location_id'],
                'type' => 'purchase',
                'status' => $input['status'],
                'contact_id' => $input['contact_id'],
                'ref_no' => $input['ref_no'] ?? $this->productUtil->generateReferenceNumber('purchase', $ref_count, $business_id),
                'transaction_date' => $input['transaction_date'],
                'total_before_tax' => $totals['total_before_tax'],
                'discount_type' => $input['discount_type'] ?? null,
                'discount_amount' => $input['discount_amount'] ?? 0,
                'tax_id' => $input['tax_rate_id'] ?? ($input['tax_id'] ?? null),
                'tax_amount' => $totals['tax_amount'],
                'shipping_charges' => $input['shipping_charges'] ?? 0,
                'final_total' => $totals['final_total'],
                'payment_status' => 'due',
                'additional_notes' => $input['additional_notes'] ?? null,
                'created_by' => $user_id,
                'pay_term_number' => $input['pay_term_number'] ?? null,
                'pay_term_type' => $input['pay_term_type'] ?? null,
            ];

            $transaction = Transaction::create($transaction_data);

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $input['products'], null, false);

            if (!empty($input['payments'])) {
                $input['payments'] = $this->normalizePaymentLines($input['payments']);
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payments'], $business_id, $user_id, false);
                $this->transactionUtil->updatePaymentStatus($transaction->id);
            }

            $this->productUtil->adjustStockOverSelling($transaction);
            $this->transactionUtil->activityLog($transaction, 'added');

            DB::commit();

            return $this->success(new TransactionResource($transaction->fresh()->load([
                'contact',
                'location',
                'payment_lines',
                'purchase_lines.product',
                'purchase_lines.variations',
                'purchase_lines.line_tax',
            ])), 'Purchase created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create purchase: ' . $e->getMessage(), 500);
        }
    }

    public function update(UpdatePurchaseRequest $request, $id)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->with(['purchase_lines'])
            ->findOrFail($id);

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('Invalid location');
        }

        try {
            DB::beginTransaction();

            $input['contact_id'] = $input['contact_id'] ?? $input['supplier_id'];
            $input['transaction_date'] = $this->parseMobileDate($input['transaction_date'], true);
            $input['type'] = 'purchase';
            $input['products'] = $this->normalizePurchaseProducts($input['products']);
            $totals = $this->calculatePurchaseTotals($input);

            $before_status = $transaction->status;

            // Match incoming products with existing purchase lines
            $existingLines = $transaction->purchase_lines->keyBy(function ($line) {
                return $line->product_id . '-' . $line->variation_id . '-' . ($line->lot_number ?? '');
            });

            foreach ($input['products'] as &$product) {
                $key = $product['product_id'] . '-' . $product['variation_id'] . '-' . ($product['lot_number'] ?? '');
                if ($existingLines->has($key)) {
                    $product['purchase_line_id'] = $existingLines[$key]->id;
                }
            }
            unset($product);

            $transaction_data = [
                'location_id' => $input['location_id'],
                'status' => $input['status'],
                'contact_id' => $input['contact_id'],
                'ref_no' => $input['ref_no'] ?? $transaction->ref_no,
                'transaction_date' => $input['transaction_date'],
                'total_before_tax' => $totals['total_before_tax'],
                'discount_type' => $input['discount_type'] ?? null,
                'discount_amount' => $input['discount_amount'] ?? 0,
                'tax_id' => $input['tax_rate_id'] ?? ($input['tax_id'] ?? null),
                'tax_amount' => $totals['tax_amount'],
                'shipping_charges' => $input['shipping_charges'] ?? 0,
                'final_total' => $totals['final_total'],
                'additional_notes' => $input['additional_notes'] ?? null,
                'pay_term_number' => $input['pay_term_number'] ?? null,
                'pay_term_type' => $input['pay_term_type'] ?? null,
            ];

            $transaction->update($transaction_data);

            $this->productUtil->createOrUpdatePurchaseLines(
                $transaction,
                $input['products'],
                null,
                false,
                $before_status
            );

            // Delete existing payment lines and recreate if payments are sent
            if (!empty($input['payments'])) {
                $input['payments'] = $this->normalizePaymentLines($input['payments']);
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payments'], $business_id, $user_id, false);
                $this->transactionUtil->updatePaymentStatus($transaction->id);
            } else {
                $this->transactionUtil->updatePaymentStatus($transaction->id);
            }

            $this->productUtil->adjustStockOverSelling($transaction);
            $this->transactionUtil->activityLog($transaction, 'edited');

            DB::commit();

            return $this->success(new TransactionResource($transaction->fresh()->load([
                'contact',
                'location',
                'payment_lines',
                'purchase_lines.product',
                'purchase_lines.variations',
                'purchase_lines.line_tax',
            ])), 'Purchase updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update purchase: ' . $e->getMessage(), 500);
        }
    }

    private function normalizePurchaseProducts(array $products): array
    {
        return array_map(function (array $product) {
            $purchasePrice = (float) ($product['purchase_price'] ?? $product['unit_cost'] ?? 0);
            $purchasePriceIncTax = (float) ($product['purchase_price_inc_tax'] ?? $product['unit_cost_inc_tax'] ?? $purchasePrice);
            $itemTax = (float) ($product['item_tax'] ?? max($purchasePriceIncTax - $purchasePrice, 0));
            $discountPercent = (float) ($product['discount_percent'] ?? 0);

            if (($product['line_discount_type'] ?? null) === 'percentage') {
                $discountPercent = (float) ($product['line_discount_amount'] ?? $discountPercent);
            } elseif (($product['line_discount_type'] ?? null) === 'fixed' && $purchasePrice > 0) {
                $discountPercent = ((float) ($product['line_discount_amount'] ?? 0) / $purchasePrice) * 100;
            }

            return array_merge($product, [
                'pp_without_discount' => $product['pp_without_discount'] ?? $product['unit_cost_before_discount'] ?? $purchasePrice,
                'discount_percent' => $discountPercent,
                'purchase_price' => $purchasePrice,
                'purchase_price_inc_tax' => $purchasePriceIncTax,
                'item_tax' => $itemTax,
                'purchase_line_tax_id' => $product['purchase_line_tax_id'] ?? ($product['tax_id'] ?? null),
                'mfg_date' => ! empty($product['mfg_date']) ? $this->formatPurchaseLineDate($product['mfg_date']) : null,
                'exp_date' => ! empty($product['exp_date']) ? $this->formatPurchaseLineDate($product['exp_date']) : null,
            ]);
        }, array_values($products));
    }

    private function calculatePurchaseTotals(array $input): array
    {
        $totalBeforeTax = 0.0;
        $lineTaxTotal = 0.0;

        foreach ($input['products'] as $product) {
            $quantity = (float) ($product['quantity'] ?? 0);
            $purchasePrice = (float) ($product['purchase_price'] ?? 0);
            $purchasePriceIncTax = (float) ($product['purchase_price_inc_tax'] ?? $purchasePrice);
            $totalBeforeTax += $quantity * $purchasePrice;
            $lineTaxTotal += $quantity * max($purchasePriceIncTax - $purchasePrice, 0);
        }

        $discount = $this->calculateDiscount($totalBeforeTax, $input['discount_type'] ?? null, (float) ($input['discount_amount'] ?? 0));
        $taxAmount = (float) ($input['tax_amount'] ?? $lineTaxTotal);
        $shipping = (float) ($input['shipping_charges'] ?? 0);
        $finalTotal = max($totalBeforeTax - $discount + $taxAmount + $shipping, 0);

        return [
            'total_before_tax' => $totalBeforeTax,
            'tax_amount' => $taxAmount,
            'final_total' => $finalTotal,
        ];
    }

    private function calculateDiscount(float $amount, ?string $type, float $discount): float
    {
        if ($type === 'percentage') {
            return ($amount * $discount) / 100;
        }

        return $type === 'fixed' ? $discount : 0.0;
    }

    private function parseMobileDate(string $date, bool $withTime = false): string
    {
        return Carbon::parse($date)->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    private function formatPurchaseLineDate(string $date): string
    {
        return Carbon::parse($date)->format(session('business.date_format') ?: 'Y-m-d');
    }

    private function normalizePaymentLines(array $payments): array
    {
        return array_map(function (array $payment) {
            if (! empty($payment['paid_on'])) {
                $payment['paid_on'] = $this->parseMobileDate($payment['paid_on'], true);
            }

            return $payment;
        }, array_values($payments));
    }

    public function payment($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,cheque,bank_transfer,custom_pay_1,custom_pay_2,custom_pay_3,custom_pay_4,custom_pay_5,custom_pay_6,custom_pay_7',
            'paid_on' => 'nullable|date',
            'account_id' => 'nullable|exists:accounts,id',
            'note' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $payment_data = [[
                'amount' => $request->amount,
                'method' => $request->method,
                'paid_on' => $request->filled('paid_on') ? $this->parseMobileDate($request->paid_on, true) : now()->toDateTimeString(),
                'account_id' => $request->account_id,
                'note' => $request->note,
            ]];

            $this->transactionUtil->createOrUpdatePaymentLines($transaction, $payment_data, $business_id, $user_id, false);
            $this->transactionUtil->updatePaymentStatus($transaction->id);

            DB::commit();

            return $this->success([
                'payment_status' => $transaction->fresh()->payment_status,
            ], 'Payment added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Payment failed: ' . $e->getMessage(), 500);
        }
    }

    public function purchaseReturn($id, Request $request)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->findOrFail($id);

        $request->validate([
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.purchase_line_id' => 'required|exists:purchase_lines,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_cost' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $input = $request->only(['transaction_date', 'products']);
            $input['location_id'] = $transaction->location_id;
            $input['contact_id'] = $transaction->contact_id;

            $return_transaction = $this->createPurchaseReturn($input, $business_id, $user_id);

            DB::commit();

            return $this->success(new TransactionResource($return_transaction->load(['contact', 'location', 'purchase_lines.product'])), 'Purchase return processed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Return failed: ' . $e->getMessage(), 500);
        }
    }

    protected function createPurchaseReturn($input, $business_id, $user_id)
    {
        $return_data = [
            'business_id' => $business_id,
            'location_id' => $input['location_id'],
            'type' => 'purchase_return',
            'status' => 'received',
            'contact_id' => $input['contact_id'],
            'transaction_date' => $input['transaction_date'],
            'total_before_tax' => 0,
            'final_total' => 0,
            'created_by' => $user_id,
        ];

        $return = Transaction::create($return_data);
        $return_lines = [];

        foreach ($input['products'] as $product) {
            $purchase_line = PurchaseLine::find($product['purchase_line_id']);
            $return_qty = min($product['quantity'], $purchase_line->quantity_remaining);

            $return_lines[] = new PurchaseLine([
                'product_id' => $purchase_line->product_id,
                'variation_id' => $purchase_line->variation_id,
                'quantity' => $return_qty,
                'unit_cost_before_discount' => $product['unit_cost'],
                'unit_cost' => $product['unit_cost'],
                'unit_cost_inc_tax' => $product['unit_cost'],
                'purchase_line_note' => 'Return',
                'parent_purchase_line_id' => $purchase_line->id,
            ]);

            $this->transactionUtil->decreaseProductQuantityStock($purchase_line->variation_id, $return->location_id, $return_qty);
        }

        $return->purchase_lines()->saveMany($return_lines);

        return $return;
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();

        $transaction = Transaction::where('business_id', $business_id)
            ->whereIn('type', ['purchase', 'purchase_return'])
            ->with(['purchase_lines'])
            ->findOrFail($id);

        if ($this->transactionUtil->isReturnExist($id)) {
            return $this->error('Cannot delete: return exists for this purchase', 400);
        }

        try {
            DB::beginTransaction();

            $transaction_status = $transaction->status;
            $delete_purchase_lines = $transaction->purchase_lines;

            $this->transactionUtil->activityLog($transaction, 'purchase_deleted');

            if ($transaction_status == 'received') {
                $delete_purchase_line_ids = [];
                foreach ($delete_purchase_lines as $purchase_line) {
                    $delete_purchase_line_ids[] = $purchase_line->id;
                    $this->productUtil->decreaseProductQuantity(
                        $purchase_line->product_id,
                        $purchase_line->variation_id,
                        $transaction->location_id,
                        $purchase_line->quantity
                    );
                }
                PurchaseLine::where('transaction_id', $transaction->id)
                    ->whereIn('id', $delete_purchase_line_ids)
                    ->delete();

                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase(
                    $transaction_status,
                    $transaction,
                    $delete_purchase_lines
                );
            }

            $transaction->delete();

            AccountTransaction::where('transaction_id', $id)->delete();

            DB::commit();

            return $this->success(null, 'Purchase deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete purchase: ' . $e->getMessage(), 500);
        }
    }
}

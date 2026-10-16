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

        return $this->success(new TransactionResource($this->attachReturnLines($purchase)));
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

            $this->productUtil->createOrUpdatePurchaseLines($transaction, $input['products'], null, false, null, false);

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
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create purchase: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }

    public function update(UpdatePurchaseRequest $request, $id)
    {
        $business_id = $this->getBusinessId();
        $user_id = $this->getUserId();

        $input = $request->validated();

        if (!$this->checkLocationAccess($input['location_id'])) {
            return $this->unauthorized('Invalid location');
        }

        try {
            DB::beginTransaction();
            $transaction = Transaction::where('business_id', $business_id)->where('type', 'purchase')
                ->lockForUpdate()->findOrFail($id);
            if (! $this->checkLocationAccess($transaction->location_id)) {
                throw new \App\Exceptions\PurchaseSellMismatch('Invalid original purchase location.');
            }
            if ((int) $transaction->location_id !== (int) $input['location_id']) {
                throw new \App\Exceptions\PurchaseSellMismatch('A purchase location cannot be changed. Use a stock transfer to move received stock.');
            }

            $input['contact_id'] = $input['contact_id'] ?? $input['supplier_id'];
            $input['transaction_date'] = $this->parseMobileDate($input['transaction_date'], true);
            $input['type'] = 'purchase';
            $input['products'] = $this->normalizePurchaseProducts($input['products']);
            $totals = $this->calculatePurchaseTotals($input);

            $before_status = $transaction->status;

            $input['products'] = app(\App\Services\PurchaseLineMatcher::class)->match($transaction, $input['products']);

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

            \App\Services\PurchaseStockGuard::assertReceiptDate($transaction, $transaction_data['transaction_date']);
            $transaction->update($transaction_data);

            $deletedLines = $this->productUtil->createOrUpdatePurchaseLines(
                $transaction,
                $input['products'],
                null,
                false,
                $before_status,
                false
            );
            $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($before_status, $transaction, $deletedLines);

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
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update purchase: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
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
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Payment failed: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }

    public function purchaseReturn($id, Request $request)
    {
        if (! auth()->user()->can('purchase.update')) {
            return $this->unauthorized();
        }
        $business_id = $this->getBusinessId();
        $request->validate([
            'transaction_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.purchase_line_id' => 'required|integer|distinct',
            'products.*.quantity' => 'required|numeric|min:0.0001',
        ]);
        try {
            DB::beginTransaction();
            $purchase = Transaction::where('business_id', $business_id)->where('type', 'purchase')
                ->where('status', 'received')->lockForUpdate()->findOrFail($id);
            if (! $this->checkLocationAccess($purchase->location_id)) {
                throw new \App\Exceptions\PurchaseSellMismatch('Invalid purchase location.');
            }
            $lines = $purchase->purchase_lines()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $quantities = $lines->pluck('quantity_returned', 'id')->all();
            foreach ($request->input('products') as $product) {
                $line = $lines->get($product['purchase_line_id']);
                if (! $line) {
                    throw new \App\Exceptions\PurchaseSellMismatch('A return line does not belong to this purchase.');
                }
                // API quantities are base units and add to the cumulative supplier return.
                $quantities[$line->id] += (float) $product['quantity'];
            }
            $total = app(\App\Services\PurchaseReturnStockService::class)->apply($purchase, $quantities);
            $return = Transaction::where('business_id', $business_id)->where('type', 'purchase_return')
                ->where('return_parent_id', $purchase->id)->lockForUpdate()->first();
            $data = ['total_before_tax' => $total, 'tax_amount' => 0, 'final_total' => $total];
            if ($return) {
                // Preserve any existing document-level return tax.
                $data['tax_amount'] = (float) $return->tax_amount;
                $data['final_total'] += $data['tax_amount'];
                $before = $return->replicate();
                $return->update($data);
                $this->transactionUtil->activityLog($return, 'edited', $before);
            } else {
                $count = $this->transactionUtil->setAndGetReferenceCount('purchase_return', $business_id);
                $return = Transaction::create(array_merge($data, [
                    'business_id' => $business_id, 'location_id' => $purchase->location_id,
                    'type' => 'purchase_return', 'status' => 'final', 'contact_id' => $purchase->contact_id,
                    'return_parent_id' => $purchase->id, 'created_by' => $this->getUserId(),
                    'transaction_date' => $this->parseMobileDate($request->input('transaction_date'), true),
                    'ref_no' => $this->transactionUtil->generateReferenceNumber('purchase_return', $count, $business_id),
                ]));
                $this->transactionUtil->activityLog($return, 'added');
            }
            $this->transactionUtil->updatePaymentStatus($return->id, $return->final_total);
            DB::commit();

            return $this->success(new TransactionResource($this->attachReturnLines($return->load(['contact', 'location']))), 'Purchase return processed successfully', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Return failed: '.$e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }

    private function attachReturnLines(Transaction $return): Transaction
    {
        if ($return->type !== 'purchase_return' || empty($return->return_parent_id)) {
            return $return;
        }
        $purchase = Transaction::where('business_id', $return->business_id)->where('type', 'purchase')
            ->find($return->return_parent_id);
        if ($purchase) {
            $lines = $purchase->purchase_lines()->where('quantity_returned', '>', 0)
                ->with(['product', 'variations', 'line_tax'])->orderBy('id')->get();
            foreach ($lines as $line) {
                // Return details are a view of the original receipt, never additional incoming stock.
                $line->quantity = $line->quantity_returned;
            }
            $return->setRelation('purchase_lines', $lines);
        }

        return $return;
    }

    public function destroy($id)
    {
        $business_id = $this->getBusinessId();

        $transaction = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase')
            ->findOrFail($id);

        if ($this->transactionUtil->isReturnExist($id)) {
            return $this->error('Cannot delete: return exists for this purchase', 400);
        }

        try {
            DB::beginTransaction();
            $transaction = Transaction::where('business_id', $business_id)->where('type', 'purchase')
                ->lockForUpdate()->findOrFail($id);
            if (! $this->checkLocationAccess($transaction->location_id)) {
                throw new \App\Exceptions\PurchaseSellMismatch('Invalid purchase location.');
            }
            if ($this->transactionUtil->isReturnExist($id)) {
                throw new \App\Exceptions\PurchaseSellMismatch('Cannot delete a purchase with returns.');
            }
            $transaction->setRelation('purchase_lines', $transaction->purchase_lines()->orderBy('id')->lockForUpdate()->get());

            $transaction_status = $transaction->status;
            $delete_purchase_lines = $transaction->purchase_lines;
            foreach ($delete_purchase_lines as $line) {
                \App\Services\PurchaseStockGuard::assertUnused($line);
            }

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
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to delete purchase: ' . $e->getMessage(), $e instanceof \App\Exceptions\PurchaseSellMismatch ? 422 : 500);
        }
    }
}

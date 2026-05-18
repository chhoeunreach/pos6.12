<?php

namespace Modules\LoanManagement\Services;

use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UltimatePosSellService
{
    public function __construct(
        protected CreateLoanFromSellService $loanFromSellService,
        protected ?TransactionUtil $transactionUtil = null,
        protected ?ProductUtil $productUtil = null
    ) {
        $this->transactionUtil = $this->transactionUtil ?: (class_exists(TransactionUtil::class) ? app(TransactionUtil::class) : null);
        $this->productUtil = $this->productUtil ?: (class_exists(ProductUtil::class) ? app(ProductUtil::class) : null);
    }

    public function searchCustomers(?string $keyword)
    {
        $keyword = trim((string) $keyword);

        if (! Schema::hasTable('contacts')) {
            return collect();
        }

        $query = DB::table('contacts')
            ->select('id', 'name', 'mobile', 'contact_id', 'email')
            ->where(function ($q) {
                $q->where('type', 'customer')->orWhereNull('type');
            })
            ->orderByDesc('id')
            ->limit(25);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%'.$keyword.'%';
                $q->where('name', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('contact_id', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->get();
    }

    public function searchProducts(?string $keyword, $locationId)
    {
        $keyword = trim((string) $keyword);

        if (! Schema::hasTable('products') || ! Schema::hasTable('variations')) {
            return collect();
        }

        $priceExpr = Schema::hasColumn('variations', 'sell_price_inc_tax')
            ? 'COALESCE(v.sell_price_inc_tax, v.default_sell_price, 0)'
            : 'COALESCE(v.default_sell_price, 0)';

        $query = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id');

        $qtyAvailableExpr = '0';
        if (Schema::hasTable('variation_location_details')) {
            $qtyAvailableExpr = 'COALESCE(vld.qty_available, 0)';
            $query->leftJoin('variation_location_details as vld', function ($join) use ($locationId) {
                $join->on('vld.variation_id', '=', 'v.id');
                if (! empty($locationId)) {
                    $join->where('vld.location_id', '=', $locationId);
                }
            });
        }

        $query->selectRaw('p.id as product_id, v.id as variation_id, p.name, p.sku, v.sub_sku, p.enable_stock, COALESCE(v.default_sell_price, 0) as unit_price, '.$priceExpr.' as unit_price_inc_tax, '.$qtyAvailableExpr.' as qty_available')
            ->orderBy('p.name')
            ->limit(30);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%'.$keyword.'%';
                $q->where('p.name', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('v.sub_sku', 'like', $like);
            });
        }

        return $query->get();
    }

    public function getProductByImeiOrLot(?string $keyword, $locationId)
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '' || ! Schema::hasTable('purchase_lines') || ! Schema::hasColumn('purchase_lines', 'lot_number')) {
            return collect();
        }

        $priceExpr = Schema::hasColumn('variations', 'sell_price_inc_tax')
            ? 'COALESCE(v.sell_price_inc_tax, v.default_sell_price, 0)'
            : 'COALESCE(v.default_sell_price, 0)';
        $qtySold = Schema::hasColumn('purchase_lines', 'quantity_sold') ? 'pl.quantity_sold' : '0';
        $qtyAdjusted = Schema::hasColumn('purchase_lines', 'quantity_adjusted') ? 'pl.quantity_adjusted' : '0';
        $qtyReturned = Schema::hasColumn('purchase_lines', 'quantity_returned') ? 'pl.quantity_returned' : '0';

        $query = DB::table('purchase_lines as pl')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->selectRaw('p.id as product_id, v.id as variation_id, p.name, p.sku, v.sub_sku, p.enable_stock, pl.id as lot_no_line_id, pl.lot_number, '.$priceExpr.' as unit_price_inc_tax, COALESCE((pl.quantity - ('.$qtySold.' + '.$qtyAdjusted.' + '.$qtyReturned.')), 0) as qty_available')
            ->where(function ($q) use ($keyword) {
                $q->where('pl.lot_number', 'like', '%'.$keyword.'%');
                if (Schema::hasColumn('purchase_lines', 'sub_unit_id')) {
                    $q->orWhere('pl.id', $keyword);
                }
            })
            ->limit(20);

        if (! empty($locationId)) {
            $query->where('t.location_id', $locationId);
        }

        return $query->get();
    }

    public function createSell(array $data)
    {
        $this->assertSellCreationAvailable();

        return DB::transaction(function () use ($data) {
            $contactId = $this->resolveContactId($data);
            $products = array_values(array_filter($data['products'] ?? [], fn ($row) => ! empty($row['variation_id'])));
            if (empty($products)) {
                throw new \RuntimeException('Please add at least one product.');
            }

            $totalBeforeTax = 0;
            $taxTotal = 0;
            foreach ($products as $row) {
                $qty = max(0, (float) ($row['qty'] ?? 0));
                $unitPrice = max(0, (float) ($row['unit_price'] ?? 0));
                $discount = max(0, (float) ($row['discount'] ?? 0));
                $tax = max(0, (float) ($row['tax'] ?? 0));
                $lineBeforeTax = max(0, ($qty * $unitPrice) - $discount);
                $totalBeforeTax += $lineBeforeTax;
                $taxTotal += $tax;
            }

            $finalTotal = round($totalBeforeTax + $taxTotal, 4);
            $paidAmount = max(0, (float) ($data['payment']['amount'] ?? 0));
            $paymentStatus = $data['payment_status'] ?? ($paidAmount >= $finalTotal ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due'));
            $businessId = (int) (session('business.id') ?: optional(auth()->user())->business_id);
            $locationId = (int) ($data['location_id'] ?? 0);

            $input = [
                'business_id' => $businessId,
                'location_id' => $locationId,
                'type' => 'sell',
                'status' => 'final',
                'contact_id' => $contactId,
                'transaction_date' => ! empty($data['sale_date']) ? Carbon::parse($data['sale_date'])->toDateTimeString() : now()->toDateTimeString(),
                'payment_status' => $paymentStatus,
                'final_total' => $finalTotal,
                'discount_type' => 'fixed',
                'discount_amount' => 0,
                'tax_rate_id' => null,
                'tax_amount' => $taxTotal,
                'sale_note' => $data['note'] ?? null,
                'staff_note' => 'Created from LoanManagement Add Sell modal',
                'source' => 'loan_management',
                'is_direct_sale' => 1,
            ];

            $invoiceTotal = [
                'total_before_tax' => $totalBeforeTax,
                'tax' => $taxTotal,
            ];

            if ($this->transactionUtil && method_exists($this->transactionUtil, 'createSellTransaction')) {
                $transaction = $this->transactionUtil->createSellTransaction($businessId, $input, $invoiceTotal, (int) auth()->id(), false);
            } else {
                $input['created_by'] = auth()->id();
                $input['invoice_no'] = 'LM-'.now()->format('YmdHis').'-'.random_int(100, 999);
                $transactionId = DB::table('transactions')->insertGetId($this->filterMainColumns('transactions', array_merge($input, [
                    'total_before_tax' => $totalBeforeTax,
                    'tax_amount' => $taxTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])));
                $transaction = DB::table('transactions')->where('id', $transactionId)->first();
            }

            $this->createSellLines($transaction, $products);
            $this->createPaymentLines($transaction, [$data['payment'] ?? []]);
            DB::table('transactions')->where('id', $transaction->id)->update($this->filterMainColumns('transactions', [
                'payment_status' => $paymentStatus,
                'updated_at' => now(),
            ]));
            $this->reduceStockUsingUltimatePos($transaction);

            return DB::table('transactions')->where('id', $transaction->id)->first();
        });
    }

    public function createSellLines($transaction, array $products): void
    {
        foreach ($products as $row) {
            $variation = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('v.id', $row['variation_id'])
                ->select('v.*', 'p.id as product_id', 'p.enable_stock')
                ->first();

            if (! $variation) {
                throw new \RuntimeException('Selected product variation was not found.');
            }

            $qty = max(0, (float) ($row['qty'] ?? 0));
            if ($qty <= 0) {
                throw new \RuntimeException('Product quantity must be greater than zero.');
            }

            $unitPrice = max(0, (float) ($row['unit_price'] ?? $variation->default_sell_price ?? 0));
            $discount = max(0, (float) ($row['discount'] ?? 0));
            $tax = max(0, (float) ($row['tax'] ?? 0));
            $priceAfterDiscount = max(0, $unitPrice - ($discount / max(1, $qty)));
            $unitPriceIncTax = $priceAfterDiscount + ($tax / max(1, $qty));

            DB::table('transaction_sell_lines')->insert($this->filterMainColumns('transaction_sell_lines', [
                'transaction_id' => $transaction->id,
                'product_id' => $variation->product_id,
                'variation_id' => $variation->id,
                'quantity' => $qty,
                'unit_price_before_discount' => $unitPrice,
                'unit_price' => $priceAfterDiscount,
                'unit_price_inc_tax' => $unitPriceIncTax,
                'line_discount_type' => 'fixed',
                'line_discount_amount' => $discount,
                'item_tax' => $tax,
                'tax_id' => $row['tax_id'] ?? null,
                'lot_no_line_id' => $row['lot_no_line_id'] ?? null,
                'sell_line_note' => $row['imei_lot'] ?? $row['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function createPaymentLines($transaction, array $payments): void
    {
        $amount = 0;
        foreach ($payments as $payment) {
            $amount += max(0, (float) ($payment['amount'] ?? 0));
        }

        if ($amount <= 0) {
            return;
        }

        $payment = $payments[0] ?? [];
        DB::table('transaction_payments')->insert($this->filterMainColumns('transaction_payments', [
            'transaction_id' => $transaction->id,
            'business_id' => $transaction->business_id ?? session('business.id'),
            'amount' => $amount,
            'method' => $payment['method'] ?? 'cash',
            'paid_on' => ! empty($payment['paid_on']) ? Carbon::parse($payment['paid_on'])->toDateTimeString() : now()->toDateTimeString(),
            'payment_ref_no' => $payment['reference_number'] ?? null,
            'note' => $payment['note'] ?? null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function reduceStockUsingUltimatePos($transaction): void
    {
        if (! $this->productUtil || ! method_exists($this->productUtil, 'decreaseProductQuantity')) {
            throw new \RuntimeException('Ultimate POS sell creation service is not available. Please create sale in POS first, then clone from sales.');
        }

        $lines = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('tsl.transaction_id', $transaction->id)
            ->select('tsl.product_id', 'tsl.variation_id', 'tsl.quantity', 'p.enable_stock')
            ->get();

        foreach ($lines as $line) {
            if ((int) ($line->enable_stock ?? 0) === 1) {
                $this->productUtil->decreaseProductQuantity(
                    (int) $line->product_id,
                    (int) $line->variation_id,
                    (int) $transaction->location_id,
                    (float) $line->quantity,
                    0
                );
            }
        }
    }

    public function formatSellForLoanClone($transactionId): array
    {
        return $this->loanFromSellService->cloneSaleToLoanFormData((int) $transactionId);
    }

    protected function resolveContactId(array $data): int
    {
        if (! empty($data['contact_id'])) {
            return (int) $data['contact_id'];
        }

        $name = trim((string) ($data['customer_name'] ?? ''));
        $phone = trim((string) ($data['customer_phone'] ?? ''));
        if ($name === '' && $phone === '') {
            throw new \RuntimeException('Please select a customer or enter quick customer name/phone.');
        }

        $businessId = (int) (session('business.id') ?: optional(auth()->user())->business_id);

        return (int) DB::table('contacts')->insertGetId($this->filterMainColumns('contacts', [
            'business_id' => $businessId,
            'type' => 'customer',
            'name' => $name !== '' ? $name : 'Customer '.$phone,
            'mobile' => $phone,
            'contact_id' => 'LM-C'.now()->format('YmdHis').random_int(10, 99),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    protected function assertSellCreationAvailable(): void
    {
        foreach (['transactions', 'transaction_sell_lines', 'transaction_payments', 'contacts', 'products', 'variations'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Ultimate POS sell creation service is not available. Please create sale in POS first, then clone from sales.');
            }
        }

        if (! class_exists(ProductUtil::class)) {
            throw new \RuntimeException('Ultimate POS sell creation service is not available. Please create sale in POS first, then clone from sales.');
        }
    }

    protected function filterMainColumns(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return Arr::only($payload, Schema::getColumnListing($table));
    }
}

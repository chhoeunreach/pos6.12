<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;

/** Creates evidenced missing receipts together with an Authority correction. */
class AuthorityTransferReceiptService
{
    public function __construct(private StockTransferReconciliationService $reconciliation, private ProductUtil $products) {}

    public static function needsStaging(array $input): bool
    {
        return collect($input['corrections'])->contains(fn ($row) => $row['source_id'] === 'new' || $row['destination_id'] === 'new' || trim((string) ($row['verified_serial'] ?? '')) !== '');
    }

    public function preview(int $businessId, int $transferId, array $input, int $userId): array
    {
        $this->reconciliation->assertTransactionalTables();
        DB::beginTransaction();
        try {
            $token = $this->fingerprint($businessId, $transferId, $input, $userId);
            [$corrections, $created, $replaced, $serialChanges] = $this->createMissing($businessId, $transferId, $input, $userId);
            $plan = $this->reconciliation->previewManual($businessId, $transferId, $corrections,
                $input['evidence'], $input['transaction_date'] ?? null, true, true, true, true);

            return array_merge($plan, ['token' => $token, 'created_receipts' => $created, 'replaced_missing_allocations' => $replaced, 'filled_source_serials' => $serialChanges]);
        } finally {
            DB::rollBack();
        }
    }

    public function apply(int $businessId, int $transferId, array $input, int $userId, string $token): array
    {
        $this->reconciliation->assertTransactionalTables();

        return DB::transaction(function () use ($businessId, $transferId, $input, $userId, $token) {
            if (!hash_equals($this->fingerprint($businessId, $transferId, $input, $userId), $token)) {
                throw new PurchaseSellMismatch('Stock records or new receipt details changed. Preview the correction again.');
            }
            [$corrections, $created, $replaced, $serialChanges] = $this->createMissing($businessId, $transferId, $input, $userId);
            // Newly generated IDs can differ from preview IDs on MySQL. Verify the actual IDs in this transaction.
            $preview = $this->reconciliation->previewManual($businessId, $transferId, $corrections,
                $input['evidence'], $input['transaction_date'] ?? null, true, true, true, true);
            $plan = $this->reconciliation->applyManual($businessId, $transferId, $corrections,
                $input['evidence'], $preview['token'], $input['transaction_date'] ?? null, true, true, true, true);

            return array_merge($plan, ['created_receipts' => $created, 'replaced_missing_allocations' => $replaced, 'filled_source_serials' => $serialChanges]);
        });
    }

    private function fingerprint(int $businessId, int $transferId, array $input, int $userId): string
    {
        $choices = collect($input['corrections'])->map(function ($row) {
            $choice = [(int) $row['sell_line_id'], (string) $row['source_id'], (string) $row['destination_id'], trim((string) ($row['verified_serial'] ?? ''))];
            if ($row['source_id'] === 'new') {
                $choice[] = [trim((string) ($row['new_source']['serial'] ?? '')), (float) $row['new_source']['quantity'],
                    $row['new_source']['date'], (float) $row['new_source']['cost']];
            }

            return $choice;
        })->sortBy(fn ($row) => $row[0])->values()->all();
        $choices = [$choices, trim($input['evidence']), $input['transaction_date'] ?? null];
        // Capture the affected products' history, including competing movements and transfer receipts.
        $variationIds = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->pluck('variation_id');
        $purchaseTransactionIds = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('p.variation_id', $variationIds)->pluck('t.id');
        $saleTransactionIds = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->where('t.business_id', $businessId)->whereIn('s.variation_id', $variationIds)->pluck('t.id');
        $receiptIds = DB::table('transactions')->where('business_id', $businessId)->where('transfer_parent_id', $transferId)->pluck('id');
        $ids = $purchaseTransactionIds->merge($saleTransactionIds)->merge($receiptIds)->push($transferId)->unique();
        $transactions = DB::table('transactions')->where('business_id', $businessId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $transactionIds = $transactions->pluck('id');
        $purchases = DB::table('purchase_lines')->whereIn('transaction_id', $transactionIds)->orderBy('id')->lockForUpdate()->get();
        $sales = DB::table('transaction_sell_lines')->whereIn('transaction_id', $transactionIds)->orderBy('id')->lockForUpdate()->get();
        $mappings = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $sales->pluck('id'))
            ->orderBy('id')->lockForUpdate()->get();
        $locations = DB::table('business_locations')->where('business_id', $businessId)->pluck('id');
        $balances = DB::table('variation_location_details')->whereIn('location_id', $locations)->whereIn('variation_id', $variationIds)
            ->orderBy('id')->lockForUpdate()->get();

        return hash_hmac('sha256', json_encode([$businessId, $transferId, $userId, $choices, $transactions, $purchases,
            $sales, $mappings, $balances, \App\Utils\BusinessUtil::allowsOverselling($businessId)], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function createMissing(int $businessId, int $transferId, array $input, int $userId): array
    {
        $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->lockForUpdate()->findOrFail($transferId);
        $receipt = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
            ->where('transfer_parent_id', $transferId)->lockForUpdate()->firstOrFail();
        if ($transfer->status !== 'final' || $receipt->status !== 'received' || (int) $transfer->location_id === (int) $receipt->location_id) {
            throw new PurchaseSellMismatch('Missing receipts can only be added to a completed, received transfer between different locations.');
        }
        $effectiveDate = $input['transaction_date'] ?? $transfer->transaction_date;
        $created = [];
        $replaced = [];
        $serialChanges = [];
        $corrections = [];
        foreach ($input['corrections'] as $correction) {
            $line = $transfer->sell_lines()->where('id', $correction['sell_line_id'])->lockForUpdate()->firstOrFail();
            $product = DB::table('products')->where('id', $line->product_id)->where('business_id', $businessId)->first();
            if (!$product || !$product->enable_stock || (float) $line->quantity <= 0 || (float) $line->quantity_returned > 0) {
                throw new PurchaseSellMismatch('Only positive stock-managed lines without returns can receive missing records.');
            }
            $verifiedSerial = trim((string) ($correction['verified_serial'] ?? ''));
            if ($correction['source_id'] !== 'new' && $verifiedSerial !== '') {
                $source = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('p.id', $correction['source_id'])->where('t.business_id', $businessId)
                    ->where('t.location_id', $transfer->location_id)->where('t.status', 'received')
                    ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
                    ->where('p.product_id', $line->product_id)->where('p.variation_id', $line->variation_id)->first(['p.*']);
                if (!$source || (trim((string) $source->lot_number) !== '' && $source->lot_number !== $verifiedSerial)) {
                    throw new PurchaseSellMismatch('Only a missing source serial can be filled here. Select the actual source receipt.');
                }
                $allowed = collect($input['corrections'])->filter(fn ($row) => (string) $row['source_id'] === (string) $source->id
                    && trim((string) ($row['verified_serial'] ?? '')) === $verifiedSerial)->pluck('sell_line_id');
                $otherTransfers = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                    ->whereNotIn('s.id', $allowed)->where(function ($query) use ($source) {
                        $query->where('s.lot_no_line_id', $source->id)->orWhereIn('s.id', DB::table('transaction_sell_lines_purchase_lines')
                            ->where('purchase_line_id', $source->id)->select('sell_line_id'));
                    })->where(function ($query) use ($businessId) {
                        $query->where('t.type', '!=', 'sell')->orWhere('t.business_id', '!=', $businessId);
                    })->exists();
                if ($otherTransfers) {
                    throw new PurchaseSellMismatch('This blank source receipt also supplies another transfer. Correct its connected transfer records together before filling the source serial.');
                }
                if ($source->lot_number !== $verifiedSerial) {
                    DB::table('purchase_lines')->where('id', $source->id)->update(['lot_number' => $verifiedSerial, 'updated_at' => now()]);
                    $serialChanges[] = ['batch_id' => (int) $source->id, 'before' => $source->lot_number, 'after' => $verifiedSerial];
                }
            }
            if ($correction['source_id'] === 'new') {
                $details = $correction['new_source'];
                $serial = trim((string) ($details['serial'] ?? ''));
                $serial = $serial === '' ? null : $serial;
                if ((float) $details['quantity'] + 0.0001 < (float) $line->quantity
                    || \Carbon::parse($details['date'])->gt(\Carbon::parse($effectiveDate))) {
                    throw new PurchaseSellMismatch('Enter sufficient evidenced receipt quantity and its actual receipt date on or before delivery.');
                }
                $duplicate = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('t.business_id', $businessId)->where('t.location_id', $transfer->location_id)->where('t.status', 'received')
                    ->where('p.product_id', $line->product_id)->where('p.variation_id', $line->variation_id)
                    ->where('p.lot_number', $serial)->where('t.transaction_date', $details['date'])->exists();
                if ($duplicate) {
                    throw new PurchaseSellMismatch('This serial already has a source receipt at that date. Select the existing receipt instead of creating a duplicate.');
                }
                $total = (float) $details['quantity'] * (float) $details['cost'];
                $sourceReceipt = Transaction::create([
                    'business_id' => $businessId, 'location_id' => $transfer->location_id, 'type' => 'opening_stock',
                    'status' => 'received', 'opening_stock_product_id' => $line->product_id,
                    'transaction_date' => $details['date'], 'created_by' => $userId, 'payment_status' => 'paid',
                    'total_before_tax' => $total, 'final_total' => $total,
                    'additional_notes' => 'Authority missing source receipt for '.($transfer->ref_no ?: '#'.$transferId)
                        .' / line #'.$line->id.'. '.$input['evidence'],
                ]);
                $source = $sourceReceipt->purchase_lines()->create([
                    'product_id' => $line->product_id, 'variation_id' => $line->variation_id,
                    'quantity' => $details['quantity'], 'lot_number' => $serial, 'sub_unit_id' => $line->sub_unit_id,
                    'pp_without_discount' => $details['cost'], 'purchase_price' => $details['cost'],
                    'purchase_price_inc_tax' => $details['cost'], 'item_tax' => 0, 'tax_id' => null,
                ]);
                $correction['source_id'] = $source->id;
                $this->products->updateProductQuantity($transfer->location_id, $line->product_id, $line->variation_id,
                    (float) $details['quantity'], 0, null, false);
                // A newly evidenced receipt may replace a genuinely deleted deduction target.
                // Never remove a reference to an existing batch, including another business's batch.
                $missingMappings = DB::table('transaction_sell_lines_purchase_lines as m')
                    ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
                    ->where('m.sell_line_id', $line->id)->where('m.purchase_line_id', '>', 0)->whereNull('p.id')
                    ->orderBy('m.id')->get(['m.*']);
                foreach ($missingMappings as $mapping) {
                    if ((float) $mapping->qty_returned > 0) {
                        throw new PurchaseSellMismatch('Reconcile returned quantities before replacing a missing deducted receipt.');
                    }
                    $replaced[] = ['sell_line_id' => (int) $line->id, 'mapping' => $mapping, 'replacement_batch_id' => (int) $source->id];
                }
                DB::table('transaction_sell_lines_purchase_lines')->whereIn('id', $missingMappings->pluck('id'))->delete();
                $created[] = ['kind' => 'source', 'sell_line_id' => (int) $line->id, 'batch_id' => (int) $source->id,
                    'transaction_id' => (int) $sourceReceipt->id, 'serial' => $serial, 'quantity' => (float) $details['quantity'],
                    'date' => $details['date'], 'cost' => (float) $details['cost'], 'location_id' => (int) $transfer->location_id];
            }
            if ($correction['destination_id'] === 'new') {
                $source = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('p.id', $correction['source_id'])->where('t.business_id', $businessId)
                    ->where('t.location_id', $transfer->location_id)->where('t.status', 'received')
                    ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
                    ->where('p.product_id', $line->product_id)->where('p.variation_id', $line->variation_id)->first(['p.*']);
                if (!$source) {
                    throw new PurchaseSellMismatch('Select a valid source receipt before adding the missing receiving batch.');
                }
                $expected = $transfer->sell_lines()->where('product_id', $line->product_id)->where('variation_id', $line->variation_id)->sum('quantity');
                $received = $receipt->purchase_lines()->where('product_id', $line->product_id)->where('variation_id', $line->variation_id)->sum('quantity');
                if ((float) $expected - (float) $received + 0.0001 < (float) $line->quantity) {
                    throw new PurchaseSellMismatch('Receiving quantity is already recorded. Select an existing receiving batch; adding another would duplicate stock.');
                }
                $destination = $receipt->purchase_lines()->create([
                    'product_id' => $line->product_id, 'variation_id' => $line->variation_id, 'quantity' => $line->quantity,
                    'lot_number' => $source->lot_number, 'mfg_date' => $source->mfg_date, 'exp_date' => $source->exp_date,
                    'purchase_price' => $line->unit_price, 'purchase_price_inc_tax' => $line->unit_price_inc_tax,
                    'item_tax' => 0, 'tax_id' => null, 'sub_unit_id' => $line->sub_unit_id,
                ]);
                // The completed transfer already moved the location quantity. Restore its batch record only.
                $correction['destination_id'] = $destination->id;
                $created[] = ['kind' => 'receiving', 'sell_line_id' => (int) $line->id, 'batch_id' => (int) $destination->id,
                    'transaction_id' => (int) $receipt->id, 'serial' => $source->lot_number, 'quantity' => (float) $line->quantity,
                    'date' => (string) $effectiveDate, 'location_id' => (int) $receipt->location_id];
            }
            $corrections[] = ['sell_line_id' => $line->id, 'source_id' => $correction['source_id'], 'destination_id' => $correction['destination_id']];
        }

        return [$corrections, $created, $replaced, $serialChanges];
    }
}

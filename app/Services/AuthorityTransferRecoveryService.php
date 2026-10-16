<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/** Restores original receipt identities, then runs the reviewed business reconciliation. */
class AuthorityTransferRecoveryService
{
    public function __construct(private AuthorityFullRepairService $full) {}

    public function preview(int $businessId, int $transferId, int $userId, string $reason, array $backup): array
    {
        DB::beginTransaction();
        try {
            $before = $this->full->previewLocked($businessId, $userId, $reason);
            $token = $this->token($before['token'], $transferId, $backup);
            $changes = $this->restore($businessId, $transferId, $backup);
            $after = $this->full->previewLocked($businessId, $userId, $reason);
            $this->assertConnectedRecords($changes, $after, $transferId);
            return ['business_id' => $businessId, 'transfer_id' => $transferId, 'reason' => $reason,
                'backup_sha256' => $backup['sha256'], 'receipt_repairs' => $changes, 'reconciliation' => $after,
                'can_apply' => count($changes) > 0 || $after['can_apply'],
                'token' => $token];
        } finally { DB::rollBack(); }
    }

    public function apply(int $businessId, int $transferId, int $userId, string $reason, array $backup, string $token, ?string $ip = null): array
    {
        return DB::transaction(function () use ($businessId, $transferId, $userId, $reason, $backup, $token, $ip) {
            $before = $this->full->previewLocked($businessId, $userId, $reason);
            if (!hash_equals($this->token($before['token'], $transferId, $backup), $token)) {
                throw new PurchaseSellMismatch('Stock records or backup details changed. Preview receipt recovery again.');
            }
            $changes = $this->restore($businessId, $transferId, $backup);
            $after = $this->full->previewLocked($businessId, $userId, $reason);
            $this->assertConnectedRecords($changes, $after, $transferId);
            if (!$changes && !$after['can_apply']) { throw new PurchaseSellMismatch('There are no recovery changes to apply.'); }
            $reconciliation = $after['can_apply']
                ? $this->full->apply($businessId, $userId, $reason, $after['token'], $ip) : $after;
            $log = ['user_id' => $userId, 'business_id' => $businessId, 'location_id' => null,
                'action_type' => 'authority_transfer_receipt_recovery', 'reference_type' => 'sell_transfer',
                'reference_id' => $transferId, 'reason' => $reason,
                'old_data' => json_encode(['backup_sha256' => $backup['sha256'], 'receipts' => array_column($changes, 'before')], JSON_THROW_ON_ERROR),
                'new_data' => json_encode(['receipts' => array_column($changes, 'after'), 'remaining_unresolved' => count($reconciliation['unresolved'])], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now()];
            $logId = DB::table('smart_stock_action_logs')->insertGetId($log);
            return ['receipt_repairs' => $changes, 'reconciliation' => $reconciliation, 'audit_log_id' => $logId,
                'message' => count($changes).' original receiving lots restored or corrected. Original receipt IDs and later movement links were preserved. '
                    .count($reconciliation['unresolved']).' business-wide records still require review. Audit log #'.$logId.'.'];
        }, 3);
    }

    private function token(string $stateToken, int $transferId, array $backup): string
    {
        $receiptIds = array_map(fn ($row) => (int) $row['id'], $backup['receipts']);
        $receiptTransactionId = (int) ($backup['receipts'][0]['transaction_id'] ?? 0);
        $current = DB::table('purchase_lines')->where(function ($query) use ($receiptIds, $receiptTransactionId) {
            $query->whereIn('id', $receiptIds)->orWhere('transaction_id', $receiptTransactionId);
        })->orderBy('id')->lockForUpdate()->get();
        return hash_hmac('sha256', json_encode(['receipt-recovery-v1', $stateToken, $transferId, $backup, $current], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function restore(int $businessId, int $transferId, array $backup): array
    {
        $transfer = Transaction::where('business_id', $businessId)->where('type', 'sell_transfer')->lockForUpdate()->findOrFail($transferId);
        $receiving = Transaction::where('business_id', $businessId)->where('type', 'purchase_transfer')
            ->where('transfer_parent_id', $transferId)->lockForUpdate()->get();
        if ($transfer->status !== 'final' || $receiving->count() !== 1 || $receiving->first()->status !== 'received') {
            throw new PurchaseSellMismatch('Recovery requires one completed outgoing document and one received destination document.');
        }
        $receipt = $receiving->first();
        foreach ([$transfer, $receipt] as $document) {
            $original = $backup['transactions'][$document->id] ?? null;
            foreach (['id', 'business_id', 'location_id', 'type', 'status', 'ref_no', 'transfer_parent_id', 'transaction_date'] as $field) {
                if (!$original || (string) ($original[$field] ?? '') !== (string) ($document->$field ?? '')) {
                    throw new PurchaseSellMismatch('The backup transfer identity, status, locations or dates differ from the current document. Resolve that document change first.');
                }
            }
        }
        $expected = []; $sourceIds = [];
        $lines = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->orderBy('id')->lockForUpdate()->get();
        foreach ($lines as $line) {
            $product = DB::table('products')->where('business_id', $businessId)->where('id', $line->product_id)->first();
            if (!$product || !$product->enable_stock || $line->quantity <= 0 || $line->quantity_returned != 0) {
                throw new PurchaseSellMismatch('Recover positive stock-managed transfer lines without returns only.');
            }
            $mappings = DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->orderBy('id')->lockForUpdate()->get();
            if ($mappings->isEmpty() || abs((float) $mappings->sum('quantity') - (float) $line->quantity) > 0.0001) {
                throw new PurchaseSellMismatch('Verify outgoing allocations before recovering receiving lots.');
            }
            foreach ($mappings as $mapping) {
                $source = DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('p.id', $mapping->purchase_line_id)->lockForUpdate()
                    ->first(['p.*', 't.business_id', 't.location_id', 't.status', 't.type', 't.transaction_date']);
                if (!$source || $source->business_id != $businessId || $source->location_id != $transfer->location_id
                    || $source->status !== 'received' || !in_array($source->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                    || $source->transaction_date > (string) $transfer->transaction_date || $source->product_id != $line->product_id
                    || $source->variation_id != $line->variation_id || $mapping->quantity <= 0 || $mapping->qty_returned != 0
                    || $mapping->stock_adjustment_line_id !== null
                    || (!empty($line->lot_no_line_id) && $line->lot_no_line_id != $source->id)) {
                    throw new PurchaseSellMismatch('An outgoing source allocation is invalid; no receiving records were restored.');
                }
                $key = $this->batchKey((array) $source, $line->unit_price, $line->unit_price_inc_tax);
                $expected[$key] = ($expected[$key] ?? 0) + (float) $mapping->quantity;
                $sourceIds[] = (int) $source->id;
            }
        }
        if (!$expected) { throw new PurchaseSellMismatch('This transfer has no verified outgoing lots.'); }
        $restored = []; $actual = [];
        foreach ($backup['receipts'] ?? [] as $row) {
            foreach (['id', 'transaction_id', 'product_id', 'variation_id'] as $field) {
                if (!isset($row[$field]) || !ctype_digit((string) $row[$field]) || (int) $row[$field] <= 0 || (int) $row[$field] > 4294967295) {
                    throw new PurchaseSellMismatch('The backup has an invalid receiving receipt identity.');
                }
            }
            if ((int) $row['transaction_id'] !== (int) $receipt->id || isset($restored[(int) $row['id']]) || in_array((int) $row['id'], $sourceIds, true)) {
                throw new PurchaseSellMismatch('The backup has duplicate or conflicting receipt IDs.');
            }
            foreach (['quantity', 'purchase_price', 'purchase_price_inc_tax'] as $field) {
                if (!isset($row[$field]) || !is_numeric($row[$field]) || !is_finite((float) $row[$field]) || (float) $row[$field] < 0) {
                    throw new PurchaseSellMismatch('Backup receipt quantities and costs must be valid nonnegative numbers.');
                }
            }
            if ((float) $row['quantity'] <= 0 || (float) ($row['quantity_returned'] ?? 0) != 0
                || (float) ($row['item_tax'] ?? 0) != 0 || !empty($row['tax_id'])) {
                throw new PurchaseSellMismatch('Recover original positive, untaxed transfer receipts without supplier returns.');
            }
            foreach (['lot_number', 'mfg_date', 'exp_date'] as $field) {
                if (isset($row[$field]) && (strlen((string) $row[$field]) > 191 || str_contains((string) $row[$field], "\0"))) {
                    throw new PurchaseSellMismatch('The backup has an invalid lot or date value.');
                }
            }
            $key = $this->batchKey($row, $row['purchase_price'], $row['purchase_price_inc_tax']);
            $actual[$key] = ($actual[$key] ?? 0) + (float) $row['quantity'];
            $restored[(int) $row['id']] = $row;
        }
        ksort($actual); ksort($expected);
        if (array_keys($actual) !== array_keys($expected)) {
            throw new PurchaseSellMismatch('The backup does not contain the complete original receiving serials and costs matching the outgoing lots. Select a backup from before the damaging edit.');
        }
        foreach ($expected as $key => $quantity) {
            if (abs($quantity - $actual[$key]) > 0.0001) { throw new PurchaseSellMismatch('Backup receiving quantities do not match the outgoing transfer. No rows were restored.'); }
        }
        $existing = DB::table('purchase_lines')->where('transaction_id', $receipt->id)->lockForUpdate()->get()->keyBy('id');
        if ($existing->keys()->diff(array_keys($restored))->isNotEmpty()) {
            throw new PurchaseSellMismatch('Current receiving rows are absent from this backup. Recover from a compatible original backup instead of deleting current lots.');
        }
        if (DB::table('transactions')->where('return_parent_id', $receipt->id)->exists()) {
            throw new PurchaseSellMismatch('This receiving document has a supplier return. Recover its original return evidence together before restoring these lots.');
        }
        $changes = [];
        foreach ($restored as $id => $row) {
            $current = DB::table('purchase_lines')->where('id', $id)->lockForUpdate()->first();
            if ($current && ((int) $current->transaction_id !== (int) $receipt->id
                || (int) $current->product_id !== (int) $row['product_id'] || (int) $current->variation_id !== (int) $row['variation_id'])) {
                throw new PurchaseSellMismatch('Original receipt ID #'.$id.' now belongs to a different document or product.');
            }
            $counters = $this->consumption($id, $row, $businessId, (int) $receipt->location_id, (string) $receipt->transaction_date);
            if ((float) ($current->quantity_returned ?? 0) != 0 || array_sum($counters) > (float) $row['quantity'] + 0.0001) {
                throw new PurchaseSellMismatch('Receipt #'.$id.' has returns or consumption beyond its original quantity. Review its connected movements first.');
            }
            if ($current && ((float) $current->quantity_adjusted > $counters['quantity_adjusted'] + 0.0001
                || (float) $current->mfg_quantity_used > $counters['mfg_quantity_used'] + 0.0001)) {
                throw new PurchaseSellMismatch('Receipt #'.$id.' has adjustment or manufacturing consumption without complete allocation evidence.');
            }
            foreach (['pp_without_discount', 'discount_percent', 'secondary_unit_quantity'] as $field) {
                if (isset($row[$field]) && (!is_numeric($row[$field]) || !is_finite((float) $row[$field]) || (float) $row[$field] < 0)) {
                    throw new PurchaseSellMismatch('The backup contains an invalid receipt price, discount or secondary quantity.');
                }
            }
            $values = ['transaction_id' => (int) $receipt->id, 'product_id' => (int) $row['product_id'], 'variation_id' => (int) $row['variation_id'],
                'quantity' => $row['quantity'], 'lot_number' => $row['lot_number'] ?? null,
                'mfg_date' => $row['mfg_date'] ?? null, 'exp_date' => $row['exp_date'] ?? null,
                'purchase_price' => $row['purchase_price'], 'purchase_price_inc_tax' => $row['purchase_price_inc_tax'],
                'pp_without_discount' => $row['pp_without_discount'] ?? $row['purchase_price'],
                'discount_percent' => $row['discount_percent'] ?? 0,
                'sub_unit_id' => $row['sub_unit_id'] ?? null,
                'item_tax' => 0, 'tax_id' => null, 'quantity_returned' => 0] + $counters;
            if (\Illuminate\Support\Facades\Schema::hasColumn('purchase_lines', 'secondary_unit_quantity')) {
                $values['secondary_unit_quantity'] = $row['secondary_unit_quantity'] ?? 0;
            }
            $different = !$current;
            $numericFields = ['transaction_id', 'product_id', 'variation_id', 'quantity', 'purchase_price',
                'purchase_price_inc_tax', 'pp_without_discount', 'discount_percent', 'secondary_unit_quantity',
                'item_tax', 'quantity_returned', 'quantity_sold', 'quantity_adjusted', 'mfg_quantity_used'];
            foreach ($values as $field => $value) {
                if (!$current) { continue; }
                $old = $current->$field ?? null;
                $same = $value === null ? $old === null
                    : (in_array($field, $numericFields, true) && is_numeric($value) && is_numeric($old)
                        ? abs((float) $value - (float) $old) <= 0.0001 : (string) $value === (string) $old);
                if (!$same) { $different = true; }
            }
            if (!$different) { continue; }
            $values['updated_at'] = now();
            if ($current) { DB::table('purchase_lines')->where('id', $id)->update($values); }
            else { DB::table('purchase_lines')->insert(['id' => $id, 'created_at' => $row['created_at'] ?? now()] + $values); }
            $changes[] = ['receipt_id' => $id, 'before' => $current ? (array) $current : null,
                'after' => (array) DB::table('purchase_lines')->where('id', $id)->first()];
        }
        return $changes;
    }

    private function consumption(int $id, array $row, int $businessId, int $locationId, string $date): array
    {
        $counters = ['quantity_sold' => 0.0, 'quantity_adjusted' => 0.0, 'mfg_quantity_used' => 0.0];
        foreach (DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $id)->lockForUpdate()->get() as $mapping) {
            if (($mapping->sell_line_id === null) === ($mapping->stock_adjustment_line_id === null)
                || $mapping->quantity <= 0 || $mapping->qty_returned < 0 || $mapping->qty_returned > $mapping->quantity) {
                throw new PurchaseSellMismatch('Receipt #'.$id.' has an invalid allocation.');
            }
            $adjustment = $mapping->stock_adjustment_line_id !== null;
            $line = DB::table($adjustment ? 'stock_adjustment_lines' : 'transaction_sell_lines')
                ->where('id', $adjustment ? $mapping->stock_adjustment_line_id : $mapping->sell_line_id)->lockForUpdate()->first();
            $movement = $line ? DB::table('transactions')->where('id', $line->transaction_id)->lockForUpdate()->first() : null;
            $types = $adjustment ? ['stock_adjustment'] : ['sell', 'sell_transfer', 'production_sell'];
            if (!$line || !$movement || $movement->business_id != $businessId || $movement->location_id != $locationId
                || $line->product_id != $row['product_id'] || $line->variation_id != $row['variation_id']
                || !in_array($movement->type, $types, true) || (!$adjustment && $movement->status !== 'final')
                || $movement->transaction_date < $date || ($adjustment && $mapping->qty_returned != 0)) {
                throw new PurchaseSellMismatch('A movement linked to original receipt #'.$id.' has a different business, product, location, date or status.');
            }
            $field = $adjustment ? 'quantity_adjusted' : ($movement->type === 'production_sell' ? 'mfg_quantity_used' : 'quantity_sold');
            $counters[$field] += (float) $mapping->quantity - (float) $mapping->qty_returned;
        }
        foreach (['transaction_sell_lines', 'stock_adjustment_lines'] as $table) {
            foreach (DB::table($table)->where('lot_no_line_id', $id)->lockForUpdate()->get() as $line) {
                $movement = DB::table('transactions')->where('id', $line->transaction_id)->first();
                if (!$movement || $movement->business_id != $businessId || $movement->location_id != $locationId
                    || $line->product_id != $row['product_id'] || $line->variation_id != $row['variation_id']) {
                    throw new PurchaseSellMismatch('An original receipt is selected by a different business, product or location.');
                }
            }
        }
        return $counters;
    }

    private function batchKey(array $row, $cost, $costIncTax): string
    {
        return json_encode([(int) $row['product_id'], (int) $row['variation_id'], (string) ($row['lot_number'] ?? ''),
            (string) ($row['mfg_date'] ?? ''), (string) ($row['exp_date'] ?? ''), number_format((float) $cost, 4, '.', ''), number_format((float) $costIncTax, 4, '.', '')], JSON_THROW_ON_ERROR);
    }

    private function assertConnectedRecords(array $changes, array $plan, int $transferId): void
    {
        $ids = array_column($changes, 'receipt_id');
        $outgoingIds = DB::table('transaction_sell_lines')->where('transaction_id', $transferId)->pluck('id');
        $sources = DB::table('transaction_sell_lines_purchase_lines')->whereIn('sell_line_id', $outgoingIds)->pluck('purchase_line_id');
        if (array_intersect(array_merge($ids, $sources->all()), $plan['blocked_receipt_ids'] ?? [])) {
            throw new PurchaseSellMismatch('A source or restored receiving lot still has unresolved stock movements. Correct those connected records before applying recovery.');
        }
        $lineIds = DB::table('transaction_sell_lines_purchase_lines')->whereIn('purchase_line_id', $ids)->whereNotNull('sell_line_id')->pluck('sell_line_id')->merge($outgoingIds);
        foreach ($plan['unresolved'] as $issue) {
            if (in_array($issue['reference'], array_map(fn ($id) => 'Receipt #'.$id, $ids), true)
                || ($issue['line_id'] !== null && $lineIds->contains($issue['line_id']))) {
                throw new PurchaseSellMismatch('A restored receipt still has unresolved linked movements: '.$issue['reason']);
            }
        }
    }
}

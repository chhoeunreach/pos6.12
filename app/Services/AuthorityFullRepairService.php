<?php

namespace App\Services;

use App\Business;
use App\Exceptions\PurchaseSellMismatch;
use App\Utils\ProductUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Builds a business-wide proposal without inventing receipts or changing recorded movements. */
class AuthorityFullRepairService
{
    private const VERSION = 'authority-full-preview-v1';
    private const EPSILON = 0.0001;

    public function __construct(private ProductUtil $products, private LotStockAuditService $audit)
    {
    }

    public function preview(int $businessId, int $userId, string $reason): array
    {
        $this->raiseLimits();
        return $this->plan($this->snapshot($businessId), $businessId, $userId, $reason);
    }

    /** Used by receipt recovery so its preview and apply share the same locked state. */
    public function previewLocked(int $businessId, int $userId, string $reason): array
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new \LogicException('A locked repair preview requires a database transaction.');
        }
        $this->raiseLimits();
        $this->assertTransactionalTables();
        return $this->plan($this->snapshot($businessId, true), $businessId, $userId, $reason);
    }

    public function apply(int $businessId, int $userId, string $reason, string $token, ?string $ip = null): array
    {
        $this->raiseLimits();
        $this->assertTransactionalTables();
        return DB::transaction(function () use ($businessId, $userId, $reason, $token, $ip) {
            $state = $this->snapshot($businessId, true);
            $plan = $this->plan($state, $businessId, $userId, $reason);
            if (! hash_equals($plan['token'], $token)) {
                throw new PurchaseSellMismatch('Stock records changed. Generate a fresh full repair preview before applying.');
            }
            if (! $plan['can_apply']) {
                throw new PurchaseSellMismatch('This preview has no validated changes to apply. Review the unresolved records.');
            }
            foreach ($plan['allocation_repairs'] as $repair) {
                // Only replace this business's validated movement rows; keep adjustment/manufacturing rows intact.
                DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $repair['line_id'])->delete();
                DB::table('transaction_sell_lines_purchase_lines')->insert([
                    'sell_line_id' => $repair['line_id'], 'purchase_line_id' => $repair['source_id'],
                    'quantity' => $repair['quantity'], 'qty_returned' => $repair['quantity_returned'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($repair['selected_before'] !== $repair['selected_after']) {
                    DB::table('transaction_sell_lines')->where('id', $repair['line_id'])->update([
                        'lot_no_line_id' => $repair['selected_after'], 'updated_at' => now(),
                    ]);
                }
            }
            foreach ($plan['counter_repairs'] as $repair) {
                DB::table('purchase_lines')->where('id', $repair['receipt_id'])->update([
                    'quantity_sold' => $repair['after'], 'updated_at' => now(),
                ]);
            }
            foreach ($plan['location_repairs'] as $repair) {
                $this->products->fixVariationStockMisMatch($businessId, $repair['variation_id'], $repair['location_id'], $repair['after']);
            }
            $afterAudit = $this->audit->audit($businessId);
            $plan['after_audit'] = [
                'sale_line_issues' => count($afterAudit['sale_line_issues']),
                'stock_issues' => count($afterAudit['stock_issues']),
                'transfer_issues' => count($afterAudit['transfer_issues']),
            ];
            // The audit is mandatory and shares the stock transaction: a logging failure rolls everything back.
            $log = [
                'user_id' => $userId, 'business_id' => $businessId, 'location_id' => null,
                'action_type' => 'authority_full_mismatch_fix', 'reference_type' => 'authority_full_preview',
                'reference_id' => null, 'reason' => $reason,
                'old_data' => json_encode(['allocation_repairs' => $plan['allocation_repairs'],
                    'counter_repairs' => $plan['counter_repairs'], 'location_repairs' => $plan['location_repairs']], JSON_THROW_ON_ERROR),
                'new_data' => json_encode(['after_audit' => $plan['after_audit'], 'unresolved' => $plan['unresolved']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ];
            if (Schema::hasColumn('smart_stock_action_logs', 'ip_address')) { $log['ip_address'] = $ip; }
            $logId = DB::table('smart_stock_action_logs')->insertGetId($log);
            $plan['audit_log_id'] = $logId;
            $plan['message'] = 'Full repair applied: '.count($plan['allocation_repairs']).' allocations, '
                .count($plan['counter_repairs']).' receipt counters and '.count($plan['location_repairs'])
                .' location balances. Audit log #'.$logId.'. Unresolved records remain listed for review.';
            return $plan;
        }, 3);
    }

    private function snapshot(int $businessId, bool $lock = false): array
    {
        $read = function ($query) use ($lock) { return ($lock ? $query->lockForUpdate() : $query)->get(); };
        $business = ($lock ? Business::lockForUpdate() : Business::query())->findOrFail($businessId);
        $locations = $read(DB::table('business_locations')->where('business_id', $businessId)
            ->select('id')->orderBy('id'));
        $products = $read(DB::table('products')->where('business_id', $businessId)
            ->select('id', 'business_id', 'enable_stock')->orderBy('id'));
        $variations = $read(DB::table('variations')->whereIn('product_id',
            DB::table('products')->where('business_id', $businessId)->select('id'))
            ->select('id', 'product_id', 'product_variation_id')->orderBy('id'));
        $transactions = $read(DB::table('transactions')->where('business_id', $businessId)
            ->select('id', 'business_id', 'status', 'type', 'location_id', 'transaction_date', 'ref_no', 'invoice_no', 'transfer_parent_id')
            ->orderBy('id'));
        $receipts = $read(DB::table('purchase_lines')->whereIn('transaction_id',
            DB::table('transactions')->where('business_id', $businessId)->select('id'))
            ->select('id', 'transaction_id', 'product_id', 'variation_id', 'quantity', 'quantity_returned',
                'quantity_adjusted', 'mfg_quantity_used', 'quantity_sold', 'lot_number', 'mfg_date', 'exp_date')
            ->orderBy('id'));
        $lines = $read(DB::table('transaction_sell_lines')->whereIn('transaction_id',
            DB::table('transactions')->where('business_id', $businessId)->select('id'))
            ->select('id', 'transaction_id', 'product_id', 'variation_id', 'lot_no_line_id', 'quantity', 'quantity_returned')
            ->orderBy('id'));
        $adjustments = $read(DB::table('stock_adjustment_lines')->whereIn('transaction_id',
            DB::table('transactions')->where('business_id', $businessId)->select('id'))
            ->select('id', 'transaction_id', 'product_id', 'variation_id', 'quantity')->orderBy('id'));
        $mappings = $read(DB::table('transaction_sell_lines_purchase_lines')->where(function ($query) use ($businessId) {
            $query->whereIn('sell_line_id', DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                ->where('t.business_id', $businessId)->select('s.id'))
                ->orWhereIn('purchase_line_id', DB::table('purchase_lines as p')->join('transactions as t', 't.id', '=', 'p.transaction_id')
                    ->where('t.business_id', $businessId)->select('p.id'))
                ->orWhereIn('stock_adjustment_line_id', DB::table('stock_adjustment_lines as a')->join('transactions as t', 't.id', '=', 'a.transaction_id')
                    ->where('t.business_id', $businessId)->select('a.id'));
        })->select('id', 'sell_line_id', 'purchase_line_id', 'stock_adjustment_line_id', 'quantity', 'qty_returned')->orderBy('id'));
        $balances = $read(DB::table('variation_location_details')->whereIn('location_id', $locations->pluck('id'))
            ->select('id', 'variation_id', 'location_id', 'product_id', 'product_variation_id', 'qty_available')->orderBy('id'));
        return compact('business', 'locations', 'products', 'variations', 'transactions', 'receipts', 'lines', 'adjustments', 'mappings', 'balances');
    }

    private function plan(array $state, int $businessId, int $userId, string $reason): array
    {
        if ($userId <= 0 || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500) {
            throw new PurchaseSellMismatch('Provide an administrator and a repair reason of 10 to 500 characters.');
        }
        $tx = $state['transactions']->keyBy('id');
        $receipts = $state['receipts']->keyBy('id');
        $lines = $state['lines']->keyBy('id');
        $products = $state['products']->keyBy('id');
        $variations = $state['variations']->keyBy('id');
        $linesByTransaction = $lines->groupBy('transaction_id');
        $receiptsByTransaction = $receipts->groupBy('transaction_id');
        $receiptsByVariation = $receipts->groupBy('variation_id');
        $byLine = $state['mappings']->whereNotNull('sell_line_id')->groupBy('sell_line_id');
        $allowOverselling = filter_var(json_decode($state['business']->pos_settings ?: '{}', true)['allow_overselling'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $changes = []; $blockedLots = []; $blockedLocations = []; $unresolved = []; $effective = [];
        $blockLot = function ($id) use (&$blockedLots, &$blockedLocations, $receipts, $tx) {
            if (! $receipts->has($id)) { return; }
            $blockedLots[(int) $id] = true;
            $p = $receipts[$id]; $t = $tx->get($p->transaction_id);
            if ($t) { $blockedLocations[$p->variation_id.':'.$t->location_id] = true; }
        };
        $blockLine = function ($line, string $why) use (&$unresolved, &$blockedLocations, $byLine, $tx, $blockLot) {
            $t = $tx->get($line->transaction_id);
            $unresolved['line-'.$line->id] = ['reference' => $t?->ref_no ?: ($t?->invoice_no ?: '#'.$line->transaction_id),
                'line_id' => (int) $line->id, 'reason' => $why];
            $blockedLocations[$line->variation_id.':'.$t?->location_id] = true;
            $blockLot($line->lot_no_line_id);
            foreach ($byLine->get($line->id, collect()) as $mapping) { $blockLot($mapping->purchase_line_id); }
        };
        $validSource = function ($source, $line, $movement) use ($tx, $businessId) {
            $receipt = $source ? $tx->get($source->transaction_id) : null;
            return $receipt && (int) $receipt->business_id === $businessId && $receipt->status === 'received'
                && in_array($receipt->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                && (int) $receipt->location_id === (int) $movement->location_id
                && (int) $source->product_id === (int) $line->product_id && (int) $source->variation_id === (int) $line->variation_id
                && ($movement->type !== 'sell_transfer' || $receipt->transaction_date <= $movement->transaction_date);
        };
        foreach ($receipts as $receipt) {
            $variation = $variations->get($receipt->variation_id);
            if (! $products->has($receipt->product_id) || ! $variation
                || (int) $variation->product_id !== (int) $receipt->product_id
                || $receipt->quantity < 0 || $receipt->quantity_returned < 0 || $receipt->quantity_returned > $receipt->quantity
                || $receipt->quantity_adjusted < 0 || $receipt->mfg_quantity_used < 0) {
                $blockLot($receipt->id);
                $unresolved['receipt-'.$receipt->id] = ['reference' => 'Receipt #'.$receipt->id, 'line_id' => null,
                    'reason' => 'Receipt product identity or recorded quantities are invalid.'];
            }
        }
        foreach ($lines as $line) {
            $movement = $tx->get($line->transaction_id);
            if (! $movement || ! in_array($movement->type, ['sell', 'sell_transfer'], true) || $movement->status !== 'final') { continue; }
            $mapped = $byLine->get($line->id, collect());
            $effective[$line->id] = $mapped->map(fn ($m) => (array) $m)->all();
            $product = $products->get($line->product_id); $variation = $variations->get($line->variation_id);
            if (! $product || ! $variation || (int) $variation->product_id !== (int) $line->product_id
                || ! $state['locations']->contains('id', $movement->location_id)) {
                $blockLine($line, 'Product, variation or location does not belong to this business.'); continue;
            }
            if (! $product->enable_stock) {
                if ($mapped->isNotEmpty()) { $blockLine($line, 'A non-stock product has inventory allocations.'); }
                continue;
            }
            if (! is_finite((float) $line->quantity) || $line->quantity <= 0 || $line->quantity_returned < 0
                || $line->quantity_returned > $line->quantity) {
                $blockLine($line, 'Invalid movement or return quantities.'); continue;
            }
            $invalid = $mapped->contains(fn ($m) => $m->quantity <= 0 || $m->qty_returned < 0 || $m->qty_returned > $m->quantity
                || (! empty($m->stock_adjustment_line_id))
                || ($m->purchase_line_id ? ! $validSource($receipts->get($m->purchase_line_id), $line, $movement)
                    : ! ($allowOverselling && empty($line->lot_no_line_id))));
            if ($invalid) { $blockLine($line, 'An allocation is invalid, missing, foreign, unreceived, or dated after the transfer.'); continue; }
            $sourceId = (int) $line->lot_no_line_id;
            $selectedAfter = $sourceId;
            if ($sourceId && ! $receipts->has($sourceId)) {
                if ($mapped->count() !== 1 || abs((float) $mapped->sum('quantity') - $line->quantity) > self::EPSILON
                    || abs((float) $mapped->sum('qty_returned') - $line->quantity_returned) > self::EPSILON) {
                    $blockLine($line, 'Missing selected receipt has no unique complete allocation to verify.'); continue;
                }
                $sourceId = $selectedAfter = (int) $mapped->first()->purchase_line_id;
            }
            if (! $sourceId) {
                if (abs((float) $mapped->sum('quantity') - $line->quantity) > self::EPSILON
                    || abs((float) $mapped->sum('qty_returned') - $line->quantity_returned) > self::EPSILON) {
                    $blockLine($line, 'FIFO allocations are incomplete or duplicated. Choose the actual receipts manually.');
                }
                continue;
            }
            if (! $validSource($receipts->get($sourceId), $line, $movement)) {
                $blockLine($line, 'Selected receipt does not match the product, location, received status or delivery date.'); continue;
            }
            if ($line->quantity_returned > 0 && $mapped->contains(fn ($m) => (int) $m->purchase_line_id !== $sourceId)) {
                $blockLine($line, 'Returned stock uses a different receipt. Verify its original return records manually.'); continue;
            }
            $alreadyCorrect = $selectedAfter === (int) $line->lot_no_line_id && $mapped->count() === 1
                && (int) $mapped->first()->purchase_line_id === $sourceId
                && abs((float) $mapped->first()->quantity - $line->quantity) <= self::EPSILON
                && abs((float) $mapped->first()->qty_returned - $line->quantity_returned) <= self::EPSILON;
            if ($alreadyCorrect) { continue; }
            $changes[$line->id] = ['line_id' => (int) $line->id, 'transaction_id' => (int) $movement->id,
                'reference' => $movement->ref_no ?: ($movement->invoice_no ?: '#'.$movement->id),
                'source_id' => $sourceId, 'serial' => $receipts[$sourceId]->lot_number,
                'selected_before' => (int) $line->lot_no_line_id, 'selected_after' => $selectedAfter,
                'quantity' => (float) $line->quantity, 'quantity_returned' => (float) $line->quantity_returned,
                'before' => $mapped->map(fn ($m) => (array) $m)->all()];
            $effective[$line->id] = [['purchase_line_id' => $sourceId, 'quantity' => $line->quantity, 'qty_returned' => $line->quantity_returned]];
        }
        // A transfer's receiving batches must corroborate its proposed source serials and quantities.
        $destinationsByParent = $tx->where('type', 'purchase_transfer')->groupBy('transfer_parent_id');
        foreach ($tx->where('type', 'sell_transfer')->where('status', 'final') as $movement) {
            $transferLines = $linesByTransaction->get($movement->id, collect());
            $destinations = $destinationsByParent->get($movement->id, collect());
            $destination = $destinations->first(); $expected = []; $actual = [];
            $valid = $destinations->count() === 1 && $destination->status === 'received'
                && (int) $destination->location_id !== (int) $movement->location_id
                && $state['locations']->contains('id', $destination->location_id);
            foreach ($transferLines as $line) {
                if (! ($products->get($line->product_id)?->enable_stock)) { continue; }
                if (isset($unresolved['line-'.$line->id])) { $valid = false; }
                foreach ($effective[$line->id] ?? [] as $mapping) {
                    $source = $receipts->get($mapping['purchase_line_id']);
                    $key = $this->batchKey($line, $source);
                    $expected[$key] = ($expected[$key] ?? 0) + (float) $mapping['quantity'];
                }
            }
            foreach ($receiptsByTransaction->get($destination?->id, collect()) as $receipt) {
                if (! ($products->get($receipt->product_id)?->enable_stock)) { continue; }
                $key = $this->batchKey($receipt, $receipt);
                $actual[$key] = ($actual[$key] ?? 0) + (float) $receipt->quantity;
            }
            foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $key) {
                if (abs(($expected[$key] ?? 0) - ($actual[$key] ?? 0)) > self::EPSILON) { $valid = false; }
            }
            if (! $valid) {
                $unresolved['transfer-'.$movement->id] = ['reference' => $movement->ref_no ?: '#'.$movement->id,
                    'line_id' => null, 'reason' => 'Completed transfer has missing or conflicting receiving records.'];
                foreach ($transferLines as $line) {
                    // A line already blocked for a precise reason keeps it; re-blocking here only masks
                    // the true root cause behind the generic transfer message.
                    if (isset($unresolved['line-'.$line->id])) { continue; }
                    $blockLine($line, 'Transfer source and receiving serial quantities do not agree. Use the individual Authority correction.');
                }
                foreach ($receiptsByTransaction->get($destination?->id, collect()) as $receipt) { $blockLot($receipt->id); }
            }
        }
        $blockAuxiliary = function ($line, $movement, string $reference) use (&$unresolved, &$blockedLocations, $receiptsByVariation, $tx, $blockLot) {
            $blockedLocations[$line->variation_id.':'.$movement->location_id] = true;
            foreach ($receiptsByVariation->get($line->variation_id, collect()) as $receipt) {
                if ((int) $receipt->product_id === (int) $line->product_id
                    && (int) $tx->get($receipt->transaction_id)?->location_id === (int) $movement->location_id) {
                    $blockLot($receipt->id);
                }
            }
            $unresolved[$reference] = ['reference' => $reference, 'line_id' => null,
                'reason' => 'Adjustment or manufacturing consumption is missing or invalid. Verify its source receipts first.'];
        };
        // Verify adjustment/manufacturing consumption before rebuilding any related sold counter.
        $adjusted = []; $manufactured = []; $auxiliary = [];
        // Orphan/foreign/draft mappings must never be hidden by rebuilding receipt counters.
        foreach ($state['mappings'] as $mapping) {
            if (empty($mapping->sell_line_id) && ! empty($mapping->stock_adjustment_line_id)) {
                $adjustment = $state['adjustments']->firstWhere('id', $mapping->stock_adjustment_line_id);
                $source = $receipts->get($mapping->purchase_line_id);
                $movement = $adjustment ? $tx->get($adjustment->transaction_id) : null;
                $receiptTx = $source ? $tx->get($source->transaction_id) : null;
                if (! $adjustment || ! $source || ! $movement || $movement->type !== 'stock_adjustment'
                    || ! $receiptTx || $receiptTx->status !== 'received'
                    || (int) $movement->location_id !== (int) $receiptTx->location_id
                    || (int) $adjustment->product_id !== (int) $source->product_id
                    || (int) $adjustment->variation_id !== (int) $source->variation_id
                    || $mapping->quantity <= 0 || $mapping->qty_returned > 0) {
                    $blockLot($mapping->purchase_line_id);
                    if ($adjustment && $movement) { $blockAuxiliary($adjustment, $movement, 'Adjustment #'.$adjustment->id); }
                    $unresolved['mapping-'.$mapping->id] = ['reference' => 'Adjustment allocation #'.$mapping->id,
                        'line_id' => null, 'reason' => 'Stock adjustment allocation does not match its receipt and location.'];
                }
                if ($adjustment && $source && $movement && $movement->type === 'stock_adjustment') {
                    $adjusted[$source->id] = ($adjusted[$source->id] ?? 0) + (float) $mapping->quantity;
                    $key = 'adjustment-'.$adjustment->id;
                    $auxiliary[$key]['quantity'] = (float) $adjustment->quantity;
                    $auxiliary[$key]['mapped'] = ($auxiliary[$key]['mapped'] ?? 0) + (float) $mapping->quantity;
                    $auxiliary[$key]['sources'][] = $source->id;
                }
                continue;
            }
            $line = $lines->get($mapping->sell_line_id); $movement = $line ? $tx->get($line->transaction_id) : null;
            if ($movement && $movement->type === 'production_sell' && $movement->status === 'final') {
                $source = $receipts->get($mapping->purchase_line_id);
                if (! $validSource($source, $line, $movement) || $mapping->quantity <= 0 || $mapping->qty_returned != 0) {
                    $blockLot($mapping->purchase_line_id);
                    $blockAuxiliary($line, $movement, 'Manufacturing line #'.$line->id);
                    $unresolved['mapping-'.$mapping->id] = ['reference' => 'Manufacturing allocation #'.$mapping->id,
                        'line_id' => (int) $line->id, 'reason' => 'Manufacturing allocation does not match its receipt and location.'];
                } elseif ($source) {
                    $manufactured[$source->id] = ($manufactured[$source->id] ?? 0) + (float) $mapping->quantity;
                    $key = 'manufacturing-'.$line->id;
                    $auxiliary[$key]['quantity'] = (float) $line->quantity;
                    $auxiliary[$key]['mapped'] = ($auxiliary[$key]['mapped'] ?? 0) + (float) $mapping->quantity;
                    $auxiliary[$key]['sources'][] = $source->id;
                }
            }
            if (! $movement || $movement->status !== 'final' || ! in_array($movement->type, ['sell', 'sell_transfer', 'production_sell'], true)) {
                $blockLot($mapping->purchase_line_id);
                $unresolved['mapping-'.$mapping->id] = ['reference' => 'Allocation #'.$mapping->id, 'line_id' => $mapping->sell_line_id,
                    'reason' => 'Allocation has a missing, foreign, incomplete or unsupported movement.'];
            }
        }
        foreach ($state['adjustments'] as $line) {
            $movement = $tx->get($line->transaction_id);
            if ($movement && $movement->type === 'stock_adjustment' && $line->quantity > 0
                && ($products->get($line->product_id)?->enable_stock) && ! isset($auxiliary['adjustment-'.$line->id])) {
                $blockAuxiliary($line, $movement, 'Adjustment #'.$line->id);
            }
        }
        foreach ($lines as $line) {
            $movement = $tx->get($line->transaction_id);
            if ($movement && $movement->type === 'production_sell' && $movement->status === 'final' && $line->quantity > 0
                && ($products->get($line->product_id)?->enable_stock) && ! isset($auxiliary['manufacturing-'.$line->id])) {
                $blockAuxiliary($line, $movement, 'Manufacturing line #'.$line->id);
            }
        }
        foreach ($auxiliary as $key => $usage) {
            if (abs($usage['quantity'] - $usage['mapped']) > self::EPSILON) {
                foreach ($usage['sources'] as $sourceId) { $blockLot($sourceId); }
                $unresolved[$key] = ['reference' => $key, 'line_id' => null,
                    'reason' => 'Adjustment or manufacturing allocation quantity does not match its document.'];
            }
        }
        foreach ($receipts as $receipt) {
            if (abs((float) $receipt->quantity_adjusted - ($adjusted[$receipt->id] ?? 0)) > self::EPSILON
                || abs((float) $receipt->mfg_quantity_used - ($manufactured[$receipt->id] ?? 0)) > self::EPSILON) {
                $blockLot($receipt->id);
                $unresolved['receipt-aux-'.$receipt->id] = ['reference' => 'Receipt #'.$receipt->id, 'line_id' => null,
                    'reason' => 'Adjustment or manufacturing counters do not agree with their allocations. Reconcile these movements first.'];
            }
        }
        // Propagate blockers through shared receipts, then evaluate aggregate capacity for the whole proposal.
        do {
            $removed = false;
            foreach ($changes as $id => $change) {
                $sourceIds = array_merge([$change['source_id']], array_column($change['before'], 'purchase_line_id'));
                if (collect($sourceIds)->contains(fn ($sourceId) => isset($blockedLots[$sourceId]))) {
                    $blockLine($lines[$id], 'A related receipt has unresolved movements. Review the connected records first.');
                    $effective[$id] = $change['before']; unset($changes[$id]); $removed = true;
                }
            }
            $sold = [];
            foreach ($effective as $mappings) {
                foreach ($mappings as $mapping) {
                    $id = (int) $mapping['purchase_line_id'];
                    $sold[$id] = ($sold[$id] ?? 0) + (float) $mapping['quantity'] - (float) $mapping['qty_returned'];
                }
            }
            foreach ($receipts as $receipt) {
                $t = $tx->get($receipt->transaction_id);
                if (! $t || $t->status !== 'received' || ! in_array($t->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)) { continue; }
                $available = (float) $receipt->quantity - (float) $receipt->quantity_returned - (float) $receipt->quantity_adjusted
                    - (float) $receipt->mfg_quantity_used - ($sold[$receipt->id] ?? 0);
                if ($available < -self::EPSILON && ! $allowOverselling && ! isset($blockedLots[$receipt->id])) {
                    $blockLot($receipt->id); $removed = true;
                    $unresolved['receipt-'.$receipt->id] = ['reference' => 'Receipt #'.$receipt->id, 'line_id' => null,
                        'reason' => 'Recorded movements exceed this receipt. No receipt quantity or delivery date will be invented.'];
                }
            }
        } while ($removed);
        $counters = [];
        foreach ($receipts as $receipt) {
            $t = $tx->get($receipt->transaction_id);
            if (isset($blockedLots[$receipt->id]) || ! $t || $t->status !== 'received'
                || ! in_array($t->type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)) { continue; }
            if (abs((float) $receipt->quantity_sold - ($sold[$receipt->id] ?? 0)) > self::EPSILON) {
                $counters[] = ['receipt_id' => (int) $receipt->id, 'serial' => $receipt->lot_number,
                    'before' => (float) $receipt->quantity_sold, 'after' => $sold[$receipt->id] ?? 0];
            }
        }
        $locations = []; $locationDetails = [];
        $balanceKeys = $state['balances']->map(fn ($row) => $row->variation_id.':'.$row->location_id)->flip();
        foreach ($receipts as $receipt) {
            $receiptTx = $tx->get($receipt->transaction_id);
            if ($receiptTx && $receiptTx->status === 'received' && ($products->get($receipt->product_id)?->enable_stock)
                && ! $balanceKeys->has($receipt->variation_id.':'.$receiptTx->location_id)) {
                $key = $receipt->variation_id.':'.$receiptTx->location_id;
                $unresolved['location-'.$key] = ['reference' => 'Variation #'.$receipt->variation_id.' / location #'.$receiptTx->location_id,
                    'line_id' => null, 'reason' => 'The location balance row is missing. Review this product location before creating a balance.'];
            }
        }
        foreach ($state['balances']->groupBy(fn ($row) => $row->variation_id.':'.$row->location_id) as $key => $balances) {
            if (isset($blockedLocations[$key])) { continue; }
            $balance = $balances->first(); $variation = $variations->get($balance->variation_id);
            if (! $variation || $balances->contains(fn ($row) => (int) $row->product_id !== (int) $variation->product_id
                || (int) $row->product_variation_id !== (int) $variation->product_variation_id)) {
                $unresolved['location-'.$key] = ['reference' => 'Variation #'.$balance->variation_id.' / location #'.$balance->location_id,
                    'line_id' => null, 'reason' => 'Location balance product identity is invalid.'];
                continue;
            }
            if (! ($products->get($variation->product_id)?->enable_stock)) { continue; }
            if (! isset($locationDetails[$balance->location_id])) {
                $locationDetails[$balance->location_id] = $this->products->getVariationStockMisMatch($businessId, null, $balance->location_id)->keyBy('variation_id');
            }
            $details = $locationDetails[$balance->location_id]->get($balance->variation_id);
            if (! $details || ! is_finite((float) $details->total_stock_calculated)) { continue; }
            $before = (float) $balances->sum('qty_available'); $after = (float) $details->total_stock_calculated;
            $ledger = 0.0;
            foreach ($receiptsByVariation->get($balance->variation_id, collect()) as $receipt) {
                $receiptTx = $tx->get($receipt->transaction_id);
                if ($receiptTx && (int) $receiptTx->location_id === (int) $balance->location_id
                    && $receiptTx->status === 'received' && in_array($receiptTx->type,
                        ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)) {
                    $ledger += (float) $receipt->quantity - (float) $receipt->quantity_returned
                        - (float) $receipt->quantity_adjusted - (float) $receipt->mfg_quantity_used - ($sold[$receipt->id] ?? 0);
                }
            }
            if (abs($ledger - $after) > self::EPSILON) {
                $unresolved['location-'.$key] = ['reference' => 'Variation #'.$balance->variation_id.' / location #'.$balance->location_id,
                    'line_id' => null, 'reason' => 'Receipt balances and movement totals disagree. Reconcile the underlying documents before updating location stock.'];
                continue;
            }
            if (abs($before - $after) > self::EPSILON || $balances->count() > 1) {
                $locations[] = ['product_id' => (int) $variation->product_id, 'variation_id' => (int) $variation->id,
                    'location_id' => (int) $balance->location_id, 'before' => $before, 'after' => $after,
                    'before_rows' => $balances->map(fn ($row) => (array) $row)->all()];
            }
        }
        if (! is_string(config('app.key')) || config('app.key') === '') {
            throw new PurchaseSellMismatch('Configure the application key before generating a full repair preview.');
        }
        $token = hash_hmac('sha256', json_encode([self::VERSION, $businessId, $userId, $reason, $state], JSON_THROW_ON_ERROR), (string) config('app.key'));
        return ['business_id' => $businessId, 'reason' => $reason, 'token' => $token,
            'allocation_repairs' => array_values($changes), 'counter_repairs' => $counters, 'location_repairs' => $locations,
            'blocked_receipt_ids' => array_keys($blockedLots),
            'unresolved' => array_values(array_map(fn ($key, $value) => $value + ['key' => $key],
                array_keys($unresolved), array_values($unresolved))),
            'can_apply' => count($changes) + count($counters) + count($locations) > 0];
    }

    /** The full-business scan loads the whole ledger into memory, so grant bulk-operation headroom. */
    private function raiseLimits(): void
    {
        @set_time_limit(0);
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '') {
            return;
        }
        $bytes = $this->memoryLimitInBytes((string) $limit);
        if ($bytes !== -1 && $bytes < 1073741824) {
            @ini_set('memory_limit', '1024M');
        }
    }

    private function memoryLimitInBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }
        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Map each unresolved record to a concrete correction entry point the administrator can open.
     * Transfer documents and their sale lines link to the Authority correction form; records that
     * require a different tool (receipt, location balance, adjustment) have no single link.
     */
    public function unresolvedActionLinks(int $businessId, array $unresolved): array
    {
        $transferKeys = []; $lineIds = [];
        foreach ($unresolved as $row) {
            $key = (string) ($row['key'] ?? '');
            if (str_starts_with($key, 'transfer-')) {
                $transferKeys[$key] = (int) substr($key, strlen('transfer-'));
            } elseif (str_starts_with($key, 'line-')) {
                $lineIds[(int) substr($key, strlen('line-'))] = $key;
            }
        }
        $links = [];
        foreach ($transferKeys as $key => $id) {
            $links[$key] = $this->correctionUrl($businessId, $id);
        }
        if ($lineIds) {
            $rows = DB::table('transaction_sell_lines as s')->join('transactions as t', 't.id', '=', 's.transaction_id')
                ->where('t.business_id', $businessId)->where('t.type', 'sell_transfer')
                ->whereIn('s.id', array_keys($lineIds))->get(['s.id', 't.id as transaction_id']);
            foreach ($rows as $row) {
                $links[$lineIds[(int) $row->id]] = $this->correctionUrl($businessId, (int) $row->transaction_id);
            }
        }

        return $links;
    }

    private function correctionUrl(int $businessId, int $transferId): string
    {
        $exists = DB::table('transactions')->where('business_id', $businessId)
            ->where('id', $transferId)->where('type', 'sell_transfer')->exists();
        if (! $exists) {
            return '';
        }

        return route('ssi.authority.index', ['transfer_id' => $transferId, 'modal' => 1]);
    }

    private function batchKey($line, $receipt): string
    {
        return json_encode([(int) $line->product_id, (int) $line->variation_id,
            (string) $receipt?->lot_number, (string) $receipt?->mfg_date, (string) $receipt?->exp_date]);
    }

    private function assertTransactionalTables(): void
    {
        $tables = ['business', 'business_locations', 'products', 'variations', 'stock_adjustment_lines',
            'transactions', 'purchase_lines', 'transaction_sell_lines', 'transaction_sell_lines_purchase_lines',
            'variation_location_details', 'smart_stock_action_logs'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) { throw new PurchaseSellMismatch('Full repair requires the stock and action-log tables. Complete the module installation first.'); }
        }
        if (DB::connection()->getDriverName() !== 'mysql') { return; }
        $names = array_map(fn ($table) => DB::connection()->getTablePrefix().$table, $tables);
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', $names)->get();
        if ($engines->count() !== count($names) || $engines->contains(fn ($row) => strtolower((string) $row->ENGINE) !== 'innodb')) {
            throw new PurchaseSellMismatch('Full repair requires transactional InnoDB stock and action-log tables.');
        }
    }
}

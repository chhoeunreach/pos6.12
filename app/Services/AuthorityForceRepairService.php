<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Super-admin force repair.
 *
 * Where the reviewed engine refuses to touch records it cannot verify, this service rebuilds the
 * missing/incorrect information first (starting with transfer receiving serials), then hands the
 * result back to the reviewed engine so counters and location balances are recomputed on the now
 * consistent ledger. Every change is staged inside one transaction, previewed, confirmed by token
 * and written to smart_stock_action_logs.
 */
class AuthorityForceRepairService
{
    use Concerns\RaisesBulkMemoryLimits;

    private const VERSION = 'authority-force-preview-v1';
    private const EPSILON = 0.0001;

    public function __construct(private AuthorityFullRepairService $full)
    {
    }

    public function preview(int $businessId, int $userId, string $reason): array
    {
        $this->raiseLimits();
        DB::beginTransaction();
        try {
            $before = $this->full->previewLocked($businessId, $userId, $reason);
            $this->applyAllocationRepairs($before['allocation_repairs']);
            $reallocations = $this->reallocateDatedAfterLines($businessId);
            $changes = $this->reconcileTransferReceiving($businessId);
            $changes['reallocations'] = $reallocations['rows'];
            $changes['updated'] += $reallocations['updated'];
            $after = $this->full->previewLocked($businessId, $userId, $reason);

            return $this->buildResult($businessId, $reason, $changes, $after,
                $this->token($businessId, $userId, $reason, $changes, $before['token']));
        } finally {
            DB::rollBack();
        }
    }

    public function apply(int $businessId, int $userId, string $reason, string $token, ?string $ip = null): array
    {
        $this->raiseLimits();

        return DB::transaction(function () use ($businessId, $userId, $reason, $token, $ip) {
            $before = $this->full->previewLocked($businessId, $userId, $reason);
            $this->applyAllocationRepairs($before['allocation_repairs']);
            $reallocations = $this->reallocateDatedAfterLines($businessId);
            $changes = $this->reconcileTransferReceiving($businessId);
            $changes['reallocations'] = $reallocations['rows'];
            $changes['updated'] += $reallocations['updated'];
            $after = $this->full->previewLocked($businessId, $userId, $reason);
            if (! hash_equals($this->token($businessId, $userId, $reason, $changes, $before['token']), $token)) {
                throw new PurchaseSellMismatch('Stock records changed. Generate a fresh force repair preview before applying.');
            }
            $reconciliation = $after['can_apply']
                ? $this->full->apply($businessId, $userId, $reason, $after['token'], $ip)
                : $after;
            $result = $this->buildResult($businessId, $reason, $changes, $reconciliation, $token);
            $log = [
                'user_id' => $userId, 'business_id' => $businessId, 'location_id' => null,
                'action_type' => 'authority_force_repair', 'reference_type' => 'authority_force_preview',
                'reference_id' => null, 'reason' => $reason,
                'old_data' => json_encode(['transfer_receipt_repairs' => $changes['transfers'],
                    'source_reallocations' => count($changes['reallocations'] ?? [])], JSON_THROW_ON_ERROR),
                'new_data' => json_encode(['remaining_unresolved' => count($reconciliation['unresolved'] ?? []),
                    'is_fully_verified' => $result['is_fully_verified'],
                    'remaining_receiving_mismatches' => $result['remaining_receiving_mismatches']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ];
            if (Schema::hasColumn('smart_stock_action_logs', 'ip_address')) {
                $log['ip_address'] = $ip;
            }
            $logId = DB::table('smart_stock_action_logs')->insertGetId($log);
            $result['audit_log_id'] = $logId;
            $result['message'] = count($changes['transfers']).' transfer receiving document repair(s) applied, '
                .count($changes['reallocations'] ?? []).' dated allocation(s) re-pointed; '
                .count($result['remaining_receiving_mismatches']).' receiving quantity mismatch(es) remain; '
                .count($reconciliation['unresolved'] ?? []).' record(s) still require review. '
                .($result['is_fully_verified'] ? 'Full verification passed. ' : 'Full verification is incomplete. ')
                .'Audit log #'.$logId.'.';

            return $result;
        }, 3);
    }

    private function buildResult(int $businessId, string $reason, array $changes, array $reconciliation, string $token): array
    {
        $changed = $changes['updated'] + $changes['inserted'] + $changes['deleted'];
        $remaining = $this->receivingQuantityMismatches($businessId);

        return [
            'business_id' => $businessId, 'reason' => $reason, 'token' => $token,
            'transfer_repairs' => $changes['transfers'],
            'reallocations' => $changes['reallocations'] ?? [],
            'changed_rows' => $changed, 'deleted_rows' => $changes['deleted'],
            'reconciliation' => $reconciliation,
            'remaining_receiving_mismatches' => $remaining,
            'is_fully_verified' => ! $remaining && empty($reconciliation['unresolved']),
            'can_apply' => $changed > 0 || ! empty($reconciliation['can_apply']),
        ];
    }

    /** Compare documents directly so incomplete source allocations cannot hide a shortage. */
    private function receivingQuantityMismatches(int $businessId): array
    {
        $sent = DB::table('transaction_sell_lines')->groupBy('transaction_id', 'product_id', 'variation_id')
            ->selectRaw('transaction_id, product_id, variation_id, SUM(quantity) as quantity');
        $received = DB::table('purchase_lines')->groupBy('transaction_id', 'product_id', 'variation_id')
            ->selectRaw('transaction_id, product_id, variation_id, SUM(quantity) as quantity');

        return DB::table('transactions as destination')
            ->join('transactions as source', 'source.id', '=', 'destination.transfer_parent_id')
            ->joinSub($sent, 'sent', 'sent.transaction_id', '=', 'source.id')
            ->leftJoinSub($received, 'received', function ($join) {
                $join->on('received.transaction_id', '=', 'destination.id')
                    ->on('received.product_id', '=', 'sent.product_id')
                    ->on('received.variation_id', '=', 'sent.variation_id');
            })
            ->join('variations as v', 'v.id', '=', 'sent.variation_id')
            ->where('source.business_id', $businessId)->where('destination.business_id', $businessId)
            ->where('source.type', 'sell_transfer')->where('source.status', 'final')
            ->where('destination.type', 'purchase_transfer')->where('destination.status', 'received')
            ->whereRaw('ABS(sent.quantity - COALESCE(received.quantity, 0)) > CAST(? AS DECIMAL(20, 8))', [self::EPSILON])
            ->orderBy('destination.id')->orderBy('sent.variation_id')
            ->get(['source.id as transfer_id', 'source.ref_no as source_reference', 'destination.id as receipt_id',
                'destination.ref_no as receiving_reference', 'v.sub_sku as sku', 'sent.variation_id',
                'sent.quantity as sent_quantity', DB::raw('COALESCE(received.quantity, 0) as received_quantity')])
            ->map(fn ($row) => (array) $row)->all();
    }

    private function token(int $businessId, int $userId, string $reason, array $changes, string $beforeToken): string
    {
        if (! is_string(config('app.key')) || config('app.key') === '') {
            throw new PurchaseSellMismatch('Configure the application key before generating a force repair preview.');
        }

        // Inserted row IDs and created/updated timestamps are not stable across a rolled-back preview
        // (MySQL keeps its AUTO_INCREMENT counter), so the token must ignore them. The before-state
        // token plus these scrubbed, deterministic repairs uniquely identify the proposed operation.
        return hash_hmac('sha256', json_encode([self::VERSION, $businessId, $userId, $reason,
            $this->scrub($changes), $beforeToken], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** Strip volatile fields (generated IDs, timestamps) from the change set for stable token hashing. */
    private function scrub(array $values): array
    {
        $clean = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = $this->scrub($value);
                continue;
            }
            if ($key === 'created_at' || $key === 'updated_at') {
                continue;
            }
            $clean[$key] = $value;
        }
        if (array_key_exists('action', $values)) {
            // Row IDs may belong to rows inserted earlier in this same run (chained transfers), so
            // they are not stable across a rolled-back preview. The pre-state token already pins the
            // original rows; the action and lot/quantity values pin the intended repair.
            unset($clean['id']);
        }

        return $clean;
    }

    /**
     * Apply the reviewed engine's validated allocation repairs so the transfer source batches used by
     * the reconciliation match exactly what the reviewed engine will expect afterwards. The reviewed
     * engine re-runs its own allocation pass at the end, so this only aligns the intermediate state.
     */
    private function applyAllocationRepairs(array $repairs): void
    {
        foreach ($repairs as $repair) {
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
    }

    /**
     * Reconcile the receiving purchase lines of the given completed transfers only, inside the caller's
     * transaction. Used by the Authority correction form so a related transfer's receiving serials are
     * rebuilt (scoped force repair) before the correction is validated.
     */
    public function reconcileReceivingForTransfers(int $businessId, array $transferIds): array
    {
        $this->raiseLimits();
        $only = array_values(array_unique(array_map('intval', $transferIds)));

        return $this->reconcileTransferReceiving($businessId, $only);
    }

    /**
     * Rewrites the receiving purchase lines of every completed transfer so their serials, lot dates
     * and quantities corroborate the batches that physically left. Existing row IDs are preserved
     * (sales linked to them stay intact); only missing lots are inserted and unused extras removed.
     * Passing $onlyTransferIds scopes the repair to those transfers.
     */
    private function reconcileTransferReceiving(int $businessId, ?array $onlyTransferIds = null): array
    {
        $destinations = DB::table('transactions')
            ->where('business_id', $businessId)->where('type', 'purchase_transfer')->where('status', 'received')
            ->whereNotNull('transfer_parent_id')
            ->select('id', 'transfer_parent_id', 'location_id')->get()
            ->groupBy('transfer_parent_id')
            ->filter(fn ($rows) => $rows->count() === 1);
        if ($onlyTransferIds !== null) {
            $only = array_map('intval', $onlyTransferIds);
            $destinations = $destinations->filter(fn ($rows, $transferId) => in_array((int) $transferId, $only, true));
        }
        if ($destinations->isEmpty()) {
            return ['transfers' => [], 'updated' => 0, 'inserted' => 0, 'deleted' => 0,
                'mismatched_before' => 0, 'mismatched_after' => 0, 'passes' => 0];
        }
        $sources = DB::table('transactions')->where('business_id', $businessId)->where('type', 'sell_transfer')
            ->where('status', 'final')->whereIn('id', $destinations->keys())
            ->select('id', 'ref_no', 'location_id')->get()->keyBy('id');
        $destinationIds = $destinations->values()->flatMap(fn ($rows) => $rows->pluck('id'))->all();
        $transferKeys = $destinations->keys()->all();
        $unverified = $this->unverifiedTransferIds($businessId, $sources->keys()->all());

        $transfers = []; $updated = 0; $inserted = 0; $deleted = 0; $mismatchedBefore = 0; $passes = 0;
        // A transfer's receiving document is frequently the source of a later transfer, so a single
        // pass only propagates one hop. Re-run until the ledger is internally consistent (bounded).
        for ($pass = 1; $pass <= 50; $pass++) {
            $passes = $pass;
            $expected = $this->expectedBatches($businessId, $onlyTransferIds);
            $actual = $this->actualBatches($businessId, $destinationIds, $transferKeys);
            $changedThisPass = false;
            foreach ($destinations as $transferId => $rows) {
                $movement = $sources->get($transferId);
                $destination = $rows->first();
                if (! $movement || (int) $destination->location_id === (int) $movement->location_id) {
                    continue;
                }
                if (isset($unverified[$transferId])) {
                    if ($pass === 1) { $mismatchedBefore++; }
                    continue;
                }
                $expectedGroups = $expected[$transferId] ?? [];
                if (! $expectedGroups) {
                    continue;
                }
                $actualGroups = $actual[$destination->id] ?? [];
                if ($this->groupsAgree($expectedGroups, $actualGroups)) {
                    continue;
                }
                if ($pass === 1) {
                    $mismatchedBefore++;
                }
                $result = $this->reconcileTransfer($businessId, (int) $destination->id, $expectedGroups, $actualGroups);
                if (! $result['updated'] && ! $result['inserted'] && ! $result['deleted']) {
                    continue;
                }
                $changedThisPass = true;
                $actual[$destination->id] = $this->actualBatches($businessId, [(int) $destination->id], [(int) $transferId])[$destination->id] ?? [];
                $updated += $result['updated']; $inserted += $result['inserted']; $deleted += $result['deleted'];
                $transfers[] = ['transfer_id' => (int) $transferId, 'reference' => $movement->ref_no ?: '#'.$transferId,
                    'destination_id' => (int) $destination->id, 'rows' => $result['rows'], 'warnings' => $result['warnings']];
            }
            if (! $changedThisPass) {
                break;
            }
        }

        $mismatchedAfter = 0;
        $finalExpected = $this->expectedBatches($businessId, $onlyTransferIds);
        $recheck = $this->actualBatches($businessId, $destinationIds, $transferKeys);
        foreach ($destinations as $transferId => $rows) {
            if (isset($unverified[$transferId])) {
                $mismatchedAfter++;
                continue;
            }
            if (! ($finalExpected[$transferId] ?? [])) {
                continue;
            }
            $destination = $rows->first();
            if (! $this->groupsAgree($finalExpected[$transferId] ?? [], $recheck[$destination->id] ?? [])) {
                $mismatchedAfter++;
            }
        }

        return ['transfers' => $transfers, 'updated' => $updated, 'inserted' => $inserted, 'deleted' => $deleted,
            'mismatched_before' => $mismatchedBefore, 'mismatched_after' => $mismatchedAfter, 'passes' => $passes];
    }

    /** A partial or invalid deduction is not evidence for rewriting the receiving document. */
    private function unverifiedTransferIds(int $businessId, array $transferIds): array
    {
        if (! $transferIds) { return []; }
        $rows = DB::table('transaction_sell_lines as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->join('products as pr', 'pr.id', '=', 's.product_id')
            ->leftJoin('transaction_sell_lines_purchase_lines as m', 'm.sell_line_id', '=', 's.id')
            ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
            ->leftJoin('transactions as receipt', 'receipt.id', '=', 'p.transaction_id')
            ->whereIn('t.id', $transferIds)->where('t.business_id', $businessId)->where('pr.enable_stock', 1)
            ->get(['s.id as line_id', 's.transaction_id', 's.product_id', 's.variation_id', 's.lot_no_line_id', 'pr.business_id as product_business_id',
                's.quantity', 's.quantity_returned', 't.location_id', 't.transaction_date',
                'm.id as mapping_id', 'm.quantity as mapped_quantity', 'm.qty_returned', 'm.purchase_line_id',
                'm.stock_adjustment_line_id', 'p.product_id as source_product_id', 'p.variation_id as source_variation_id',
                'receipt.business_id as source_business_id', 'receipt.location_id as source_location_id',
                'receipt.status as source_status', 'receipt.type as source_type', 'receipt.transaction_date as receipt_date'])
            ->groupBy('line_id');
        $allowOverselling = \App\Utils\BusinessUtil::allowsOverselling($businessId);
        $blocked = [];
        foreach ($rows as $allocations) {
            $line = $allocations->first();
            $invalid = (int) $line->product_business_id !== $businessId || (float) $line->quantity <= 0
                || abs((float) $allocations->sum('mapped_quantity') - (float) $line->quantity) > self::EPSILON
                || abs((float) $allocations->sum('qty_returned') - (float) $line->quantity_returned) > self::EPSILON;
            foreach ($allocations as $row) {
                if (! $row->mapping_id || (float) $row->mapped_quantity <= 0 || (float) $row->qty_returned < 0
                    || (float) $row->qty_returned > (float) $row->mapped_quantity || $row->stock_adjustment_line_id) {
                    $invalid = true; break;
                }
                if ((int) $row->purchase_line_id === 0 && $allowOverselling && empty($row->lot_no_line_id)) {
                    continue;
                }
                if ((int) $row->source_business_id !== $businessId || (int) $row->source_location_id !== (int) $row->location_id
                    || (int) $row->source_product_id !== (int) $row->product_id
                    || (int) $row->source_variation_id !== (int) $row->variation_id || $row->source_status !== 'received'
                    || ! in_array($row->source_type, ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'], true)
                    || $row->receipt_date > $row->transaction_date
                    || (! empty($row->lot_no_line_id) && (int) $row->lot_no_line_id !== (int) $row->purchase_line_id)) {
                    $invalid = true; break;
                }
            }
            if ($invalid) { $blocked[(int) $line->transaction_id] = true; }
        }

        return $blocked;
    }

    /**
     * Re-points transfer source allocations that were linked to a receipt delivered after the transfer
     * to an earlier receipt that was actually on hand. A candidate must match the product, variation and
     * source location, be received on or before the transfer date, and still carry enough free quantity,
     * so no stock is invented. Receiving batches are reconciled afterwards to follow the new lots.
     */
    private function reallocateDatedAfterLines(int $businessId): array
    {
        $allowed = ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'];
        $transfers = DB::table('transactions')->where('business_id', $businessId)
            ->where('type', 'sell_transfer')->where('status', 'final')
            ->select('id', 'location_id', 'transaction_date', 'ref_no')->get()->keyBy('id');
        if ($transfers->isEmpty()) {
            return ['updated' => 0, 'rows' => []];
        }

        $lines = DB::table('transaction_sell_lines')->whereIn('transaction_id', $transfers->keys())
            ->select('id', 'transaction_id', 'product_id', 'variation_id', 'quantity', 'quantity_returned', 'lot_no_line_id')
            ->orderBy('id')->get();
        if ($lines->isEmpty()) {
            return ['updated' => 0, 'rows' => []];
        }

        $mappings = DB::table('transaction_sell_lines_purchase_lines')
            ->whereIn('sell_line_id', $lines->pluck('id'))
            ->select('id', 'sell_line_id', 'purchase_line_id', 'quantity', 'qty_returned', 'stock_adjustment_line_id')
            ->get()->groupBy('sell_line_id');

        $sourceIds = $mappings->flatten(1)->pluck('purchase_line_id')->unique()->filter()->all();
        $sources = $sourceIds ? DB::table('purchase_lines as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->whereIn('p.id', $sourceIds)
            ->select('p.id', 'p.product_id', 'p.variation_id', 't.business_id', 't.status', 't.type',
                't.location_id', 't.transaction_date')->get()->keyBy('id') : collect();

        $stockProducts = DB::table('products')->where('business_id', $businessId)
            ->where('enable_stock', 1)->pluck('id')->flip();

        // Committed consumption of every receipt by any allocation (sales and stock adjustments).
        $consumed = [];
        foreach (DB::table('transaction_sell_lines_purchase_lines as m')
            ->join('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->where('t.business_id', $businessId)
            ->selectRaw('m.purchase_line_id as pid, SUM(m.quantity - m.qty_returned) as qty')
            ->groupBy('m.purchase_line_id')->get() as $row) {
            $consumed[(int) $row->pid] = (float) $row->qty;
        }

        // Valid candidate receipts grouped by product:variation:location, oldest first.
        $candidates = [];
        foreach (DB::table('purchase_lines as p')
            ->join('transactions as t', 't.id', '=', 'p.transaction_id')
            ->join('products as pr', fn ($join) => $join->on('pr.id', '=', 'p.product_id')->where('pr.enable_stock', 1))
            ->where('t.business_id', $businessId)->where('t.status', 'received')->whereIn('t.type', $allowed)
            ->select('p.id', 'p.product_id', 'p.variation_id', 'p.quantity', 'p.quantity_returned',
                'p.quantity_adjusted', 'p.mfg_quantity_used', 't.location_id', 't.transaction_date')->get() as $row) {
            $candidates[(int) $row->product_id.':'.(int) $row->variation_id.':'.(int) $row->location_id][] = [
                'id' => (int) $row->id, 'date' => (string) $row->transaction_date,
                'base' => (float) $row->quantity - (float) $row->quantity_returned
                    - (float) $row->quantity_adjusted - (float) $row->mfg_quantity_used,
            ];
        }
        foreach ($candidates as &$list) {
            usort($list, fn ($left, $right) => [$left['date'], $left['id']] <=> [$right['date'], $right['id']]);
        }
        unset($list);

        $rows = []; $updated = 0;
        foreach ($lines as $line) {
            if (! isset($stockProducts[(int) $line->product_id])) { continue; }
            $map = $mappings->get($line->id, collect())->first();
            if (! $map || $mappings[$line->id]->count() !== 1 || $map->stock_adjustment_line_id
                || (float) $map->qty_returned != (float) $line->quantity_returned) {
                continue;
            }
            $source = $sources->get((int) $map->purchase_line_id);
            $movement = $transfers->get((int) $line->transaction_id);
            if (! $source || ! $movement || $source->transaction_date <= $movement->transaction_date) { continue; }
            // Only re-point when every other validity condition holds and the sole problem is the date.
            if ((int) $source->business_id !== $businessId || $source->status !== 'received'
                || ! in_array($source->type, $allowed, true)
                || (int) $source->location_id !== (int) $movement->location_id
                || (int) $source->product_id !== (int) $line->product_id
                || (int) $source->variation_id !== (int) $line->variation_id) {
                continue;
            }
            $quantity = (float) $line->quantity;
            $key = (int) $line->product_id.':'.(int) $line->variation_id.':'.(int) $movement->location_id;
            $chosen = null;
            foreach ($candidates[$key] ?? [] as $candidate) {
                if ($candidate['date'] > $movement->transaction_date || $candidate['id'] === (int) $map->purchase_line_id) { continue; }
                if ($candidate['base'] - ($consumed[$candidate['id']] ?? 0) + 0.0001 >= $quantity) { $chosen = $candidate; break; }
            }
            if (! $chosen) { continue; }

            DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->delete();
            DB::table('transaction_sell_lines_purchase_lines')->insert([
                'sell_line_id' => (int) $line->id, 'purchase_line_id' => $chosen['id'],
                'quantity' => $quantity, 'qty_returned' => (float) $line->quantity_returned,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! empty($line->lot_no_line_id)) {
                DB::table('transaction_sell_lines')->where('id', $line->id)
                    ->update(['lot_no_line_id' => $chosen['id'], 'updated_at' => now()]);
            }
            $consumed[(int) $map->purchase_line_id] = ($consumed[(int) $map->purchase_line_id] ?? 0) - $quantity;
            $consumed[$chosen['id']] = ($consumed[$chosen['id']] ?? 0) + $quantity - (float) $line->quantity_returned;
            $rows[] = ['action' => 'reallocated', 'line_id' => (int) $line->id,
                'reference' => $movement->ref_no ?: '#'.$movement->id,
                'source_before' => (int) $map->purchase_line_id, 'source_after' => $chosen['id'], 'quantity' => $quantity];
            $updated++;
        }

        return ['updated' => $updated, 'rows' => $rows];
    }

    /** Aggregated source batches per completed transfer, keyed by transfer then batch identity. */
    private function expectedBatches(int $businessId, ?array $onlyTransferIds = null): array
    {
        $rows = DB::table('transactions as t')
            ->join('transaction_sell_lines as s', 's.transaction_id', '=', 't.id')
            ->join('transaction_sell_lines_purchase_lines as m', 'm.sell_line_id', '=', 's.id')
            ->join('products as pr', function ($join) {
                $join->on('pr.id', '=', 's.product_id')->where('pr.enable_stock', 1);
            })
            ->leftJoin('purchase_lines as p', 'p.id', '=', 'm.purchase_line_id')
            ->where('t.business_id', $businessId)->where('t.type', 'sell_transfer')->where('t.status', 'final')
            ->when($onlyTransferIds !== null, fn ($query) => $query->whereIn('t.id', $onlyTransferIds))
            ->groupBy('t.id', 's.product_id', 's.variation_id', 'p.lot_number', 'p.mfg_date', 'p.exp_date')
            ->selectRaw('t.id as transfer_id, s.product_id, s.variation_id, p.lot_number, p.mfg_date, p.exp_date, '
                .'SUM(m.quantity * COALESCE(p.purchase_price, 0)) / NULLIF(SUM(m.quantity), 0) as purchase_price, '
                .'SUM(m.quantity * COALESCE(p.purchase_price_inc_tax, 0)) / NULLIF(SUM(m.quantity), 0) as purchase_price_inc_tax, '
                .'SUM(m.quantity) as qty')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->transfer_id][$this->groupKey($row->product_id, $row->variation_id)][] = [
                'product_id' => (int) $row->product_id, 'variation_id' => (int) $row->variation_id,
                'lot' => $this->text($row->lot_number), 'mfg' => $this->text($row->mfg_date), 'exp' => $this->text($row->exp_date),
                'qty' => (float) $row->qty,
                'purchase_price' => $row->purchase_price, 'purchase_price_inc_tax' => $row->purchase_price_inc_tax,
            ];
        }

        return $grouped;
    }

    /** Aggregated destination batches per receiving document, keyed by document then batch identity. */
    private function actualBatches(int $businessId, array $destinationIds, array $transferIds): array
    {
        if (! $destinationIds) {
            return [];
        }
        $rows = DB::table('purchase_lines as r')
            ->join('transactions as d', 'd.id', '=', 'r.transaction_id')
            ->join('products as pr', function ($join) {
                $join->on('pr.id', '=', 'r.product_id')->where('pr.enable_stock', 1);
            })
            ->where('d.business_id', $businessId)->where('d.type', 'purchase_transfer')->where('d.status', 'received')
            ->whereIn('d.transfer_parent_id', $transferIds)
            ->groupBy('d.id', 'r.product_id', 'r.variation_id', 'r.lot_number', 'r.mfg_date', 'r.exp_date')
            ->selectRaw('d.id as destination_id, r.product_id, r.variation_id, r.lot_number, r.mfg_date, r.exp_date, SUM(r.quantity) as qty')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->destination_id][$this->groupKey($row->product_id, $row->variation_id)][] = [
                'lot' => $this->text($row->lot_number), 'mfg' => $this->text($row->mfg_date),
                'exp' => $this->text($row->exp_date), 'qty' => (float) $row->qty,
            ];
        }

        return $grouped;
    }

    private function groupsAgree(array $expectedGroups, array $actualGroups): bool
    {
        $keys = array_unique(array_merge(array_keys($expectedGroups), array_keys($actualGroups)));
        foreach ($keys as $key) {
            $left = $expectedGroups[$key] ?? []; $right = $actualGroups[$key] ?? [];
            if (count($left) !== count($right)) {
                return false;
            }
            $l = $this->signatures($left); $r = $this->signatures($right);
            sort($l); sort($r);
            if ($l !== $r) {
                return false;
            }
        }

        return true;
    }

    private function signatures(array $entries): array
    {
        return array_map(fn ($e) => $e['lot']."\x1f".$e['mfg']."\x1f".$e['exp']."\x1f".number_format($e['qty'], 4, '.', ''), $entries);
    }

    private function reconcileTransfer(int $businessId, int $destinationId, array $expectedGroups, array $actualGroups): array
    {
        $rows = DB::table('purchase_lines')->where('transaction_id', $destinationId)
            ->orderBy('id')->get()->keyBy('id');
        $hasSecondary = Schema::hasColumn('purchase_lines', 'secondary_unit_quantity');
        $output = ['updated' => 0, 'inserted' => 0, 'deleted' => 0, 'rows' => [], 'warnings' => []];

        $keys = array_unique(array_merge(array_keys($expectedGroups), array_keys($actualGroups)));
        foreach ($keys as $key) {
            $expected = $expectedGroups[$key] ?? [];
            $actualIds = array_values(array_filter($rows->keys()->all(),
                fn ($id) => $this->groupKey($rows[$id]->product_id, $rows[$id]->variation_id) === $key));
            $result = $this->reconcileGroup($businessId, $destinationId, $rows, $actualIds, $expected, $hasSecondary);
            $output['updated'] += $result['updated'];
            $output['inserted'] += $result['inserted'];
            $output['deleted'] += $result['deleted'];
            $output['rows'] = array_merge($output['rows'], $result['rows']);
            $output['warnings'] = array_merge($output['warnings'], $result['warnings']);
        }

        return $output;
    }

    private function reconcileGroup(int $businessId, int $destinationId, $rows, array $actualIds, array $expected, bool $hasSecondary): array
    {
        $result = ['updated' => 0, 'inserted' => 0, 'deleted' => 0, 'rows' => [], 'warnings' => []];
        $matchedRows = [];
        $pending = $expected;

        // Reconcile the total across every row of the same lot. Assigning the full
        // batch quantity to just one row double-counts other rows used by later sales.
        foreach ($pending as $index => $entry) {
            $matchingIds = array_values(array_filter($actualIds, function ($id) use ($rows, $entry) {
                $row = $rows[$id];
                return $entry['lot'] === $this->text($row->lot_number)
                    && $entry['mfg'] === $this->text($row->mfg_date)
                    && $entry['exp'] === $this->text($row->exp_date);
            }));
            if (! $matchingIds) {
                continue;
            }
            $difference = $entry['qty'] - array_sum(array_map(fn ($id) => (float) $rows[$id]->quantity, $matchingIds));
            foreach ($matchingIds as $id) {
                if (abs($difference) <= self::EPSILON) {
                    break;
                }
                $row = $rows[$id];
                // Never shrink a receipt already consumed or referenced by another movement.
                if ($difference < 0 && $this->rowIsConsumed($id, $row)) {
                    continue;
                }
                $change = $difference > 0 ? $difference : -min((float) $row->quantity, -$difference);
                $target = array_merge($entry, ['qty' => (float) $row->quantity + $change]);
                $update = $this->rowUpdate($row, $target);
                if ($update) {
                    DB::table('purchase_lines')->where('id', $id)->update($update);
                    $result['updated']++;
                    $result['rows'][] = ['id' => (int) $id, 'action' => 'updated', 'before' => $this->rowValues($row), 'after' => $update];
                }
                $difference -= $change;
            }
            if (abs($difference) > self::EPSILON) {
                $result['warnings'][] = 'Receiving lot '.$entry['lot'].' cannot be reduced to its sent quantity because later movements reference it.';
            }
            $matchedRows = array_merge($matchedRows, $matchingIds);
            unset($pending[$index]);
        }
        $pending = array_values($pending);
        $unmatchedRows = array_values(array_diff($actualIds, $matchedRows));

        // Second pass: pair the remaining rows with the remaining expected batches in insertion order.
        foreach ($pending as $index => $entry) {
            if (! isset($unmatchedRows[$index])) {
                break;
            }
            $id = $unmatchedRows[$index];
            $row = $rows[$id];
            $update = $this->rowUpdate($row, $entry);
            if ($update) {
                DB::table('purchase_lines')->where('id', $id)->update($update);
                $result['updated']++;
                $result['rows'][] = ['id' => (int) $id, 'action' => 'updated', 'before' => $this->rowValues($row), 'after' => $update];
            }
            $matchedRows[] = $id;
            unset($pending[$index]);
        }
        $pending = array_values($pending);
        $unmatchedRows = array_values(array_diff($actualIds, $matchedRows));

        // Expected batches with no surviving row: insert them.
        foreach ($pending as $entry) {
            $inserted = $this->insertBatch($destinationId, $entry, $hasSecondary);
            $result['inserted']++;
            $result['rows'][] = ['id' => $inserted['id'], 'action' => 'inserted', 'before' => null, 'after' => $inserted['values']];
        }

        // Surplus rows: remove only when nothing references them, otherwise leave and warn.
        foreach ($unmatchedRows as $id) {
            $row = $rows[$id];
            if ($this->rowIsConsumed($id, $row)) {
                $result['warnings'][] = 'Receiving row #'.$id.' extra lot cannot be removed because movements reference it.';
                continue;
            }
            DB::table('purchase_lines')->where('id', $id)->delete();
            $result['deleted']++;
            $result['rows'][] = ['id' => (int) $id, 'action' => 'deleted', 'before' => $this->rowValues($row), 'after' => null];
        }

        return $result;
    }

    private function rowUpdate($row, array $entry): array
    {
        $update = [];
        if ($this->text($row->lot_number) !== $entry['lot']) {
            $update['lot_number'] = $entry['lot'] === '' ? null : $entry['lot'];
        }
        if ($this->text($row->mfg_date) !== $entry['mfg']) {
            $update['mfg_date'] = $entry['mfg'] === '' ? null : $entry['mfg'];
        }
        if ($this->text($row->exp_date) !== $entry['exp']) {
            $update['exp_date'] = $entry['exp'] === '' ? null : $entry['exp'];
        }
        if (abs((float) $row->quantity - $entry['qty']) > self::EPSILON) {
            $update['quantity'] = round($entry['qty'], 4);
        }
        if ($update) {
            $update['updated_at'] = now();
        }

        return $update;
    }

    private function insertBatch(int $destinationId, array $entry, bool $hasSecondary): array
    {
        $values = [
            'transaction_id' => $destinationId,
            'product_id' => $entry['product_id'], 'variation_id' => $entry['variation_id'],
            'quantity' => round($entry['qty'], 4), 'lot_number' => $entry['lot'] === '' ? null : $entry['lot'],
            'mfg_date' => $entry['mfg'] === '' ? null : $entry['mfg'], 'exp_date' => $entry['exp'] === '' ? null : $entry['exp'],
            'purchase_price' => $entry['purchase_price'] ?? 0, 'purchase_price_inc_tax' => $entry['purchase_price_inc_tax'] ?? 0,
            'pp_without_discount' => $entry['purchase_price'] ?? 0, 'discount_percent' => 0,
            'sub_unit_id' => null, 'item_tax' => 0, 'tax_id' => null,
            'quantity_returned' => 0, 'quantity_sold' => 0, 'quantity_adjusted' => 0, 'mfg_quantity_used' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ];
        if ($hasSecondary) {
            $values['secondary_unit_quantity'] = 0;
        }
        $id = DB::table('purchase_lines')->insertGetId($values);

        return ['id' => (int) $id, 'values' => $values];
    }

    private function rowIsConsumed(int $id, $row): bool
    {
        if ((float) $row->quantity_sold > 0 || (float) $row->quantity_returned > 0
            || (float) $row->quantity_adjusted > 0 || (float) $row->mfg_quantity_used > 0) {
            return true;
        }

        return DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $id)->exists()
            || DB::table('transaction_sell_lines')->where('lot_no_line_id', $id)->exists()
            || DB::table('stock_adjustment_lines')->where('lot_no_line_id', $id)->exists();
    }

    private function rowValues($row): array
    {
        return ['lot_number' => $row->lot_number, 'mfg_date' => $row->mfg_date, 'exp_date' => $row->exp_date,
            'quantity' => (float) $row->quantity];
    }

    private function groupKey($productId, $variationId): string
    {
        return $productId.':'.$variationId;
    }

    private function text($value): string
    {
        return trim((string) $value);
    }
}

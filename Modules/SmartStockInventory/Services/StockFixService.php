<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Modules\SmartStockInventory\Models\SmartStockFixLog;

class StockFixService
{
    public function recalculateProductStock(int $productId, int $variationId, int $locationId): array
    {
        $currentQty = (float) DB::table('variation_location_details')->where('variation_id', $variationId)->where('product_id', $productId)->where('location_id', $locationId)->value('qty_available');
        $purchased = (float) DB::table('purchase_lines')->join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')->where('t.type', 'purchase')->where('t.location_id', $locationId)->where('purchase_lines.variation_id', $variationId)->sum('purchase_lines.quantity');
        $sold = (float) DB::table('transaction_sell_lines')->join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')->where('t.type', 'sell')->where('t.status', 'final')->where('t.location_id', $locationId)->where('transaction_sell_lines.variation_id', $variationId)->sum('transaction_sell_lines.quantity');
        $adjusted = (float) DB::table('stock_adjustment_lines')->join('transactions as t', 't.id', '=', 'stock_adjustment_lines.transaction_id')->where('t.type', 'stock_adjustment')->where('t.location_id', $locationId)->where('stock_adjustment_lines.variation_id', $variationId)->sum('stock_adjustment_lines.quantity');
        $calculated = $purchased - $sold + $adjusted;
        return compact('currentQty', 'purchased', 'sold', 'adjusted', 'calculated');
    }

    public function detectMismatch(array $filters): array
    {
        $query = DB::table('variation_location_details as vld')->join('variations as v', 'v.id', '=', 'vld.variation_id')->join('products as p', 'p.id', '=', 'v.product_id')->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->where('p.business_id', $filters['business_id'])->whereIn('vld.location_id', $filters['location_ids'])->where('vld.qty_available', '<', 0);

        $purchaseRef = trim((string) ($filters['purchase_ref'] ?? ''));
        $purchaseFrom = $filters['purchase_date_from'] ?? null;
        $purchaseTo = $filters['purchase_date_to'] ?? null;

        if ($purchaseRef !== '' || ! empty($purchaseFrom) || ! empty($purchaseTo)) {
            $query->whereExists(function ($sub) use ($purchaseRef, $purchaseFrom, $purchaseTo) {
                $sub->select(DB::raw(1))
                    ->from('purchase_lines as pl')
                    ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                    ->whereColumn('pl.variation_id', 'vld.variation_id')
                    ->whereColumn('t.location_id', 'vld.location_id')
                    ->where('t.type', 'purchase')
                    ->when($purchaseRef !== '', fn ($q) => $q->where('t.ref_no', 'like', "%{$purchaseRef}%"))
                    ->when(! empty($purchaseFrom), fn ($q) => $q->whereDate('t.transaction_date', '>=', $purchaseFrom))
                    ->when(! empty($purchaseTo), fn ($q) => $q->whereDate('t.transaction_date', '<=', $purchaseTo));
            });
        }

        return $query
            ->select('p.id as product_id', 'v.id as variation_id', 'vld.location_id', 'p.name as product', 'v.sub_sku as sku', 'bl.name as location', 'vld.qty_available as available_qty')->limit(500)->get()
            ->map(fn ($n) => ['product_id' => (int) $n->product_id, 'variation_id' => (int) $n->variation_id, 'location_id' => (int) $n->location_id, 'product' => $n->product, 'sku' => $n->sku, 'location' => $n->location, 'problem' => 'Negative stock', 'severity' => 'critical', 'available_qty' => (float) $n->available_qty])->all();
    }

    public function previewFix(int $productId, int $variationId, int $locationId, string $problem = 'negative_stock'): array
    {
        $snapshot = $this->recalculateProductStock($productId, $variationId, $locationId);
        return ['problem' => $problem, 'current_system_qty' => $snapshot['currentQty'], 'correct_calculated_qty' => max(0, (float) $snapshot['calculated']), 'affected_transaction_ids' => $this->transactionIds($variationId, $locationId), 'risk_level' => ((float) $snapshot['currentQty'] < 0 ? 'high' : 'medium')];
    }

    public function fixNegativeStock(int $productId, int $variationId, int $locationId, string $reason = 'auto_fix_negative_stock'): SmartStockFixLog { return $this->writeQtyFix('negative_stock', $productId, $variationId, $locationId, $reason); }
    public function fixMismatchStock(int $productId, int $variationId, int $locationId, string $reason = 'fix_mismatch_stock'): SmartStockFixLog { return $this->writeQtyFix('mismatch_stock', $productId, $variationId, $locationId, $reason); }
    public function fixWrongLocationStock(int $productId, int $variationId, int $locationId, string $reason = 'fix_wrong_location_stock'): SmartStockFixLog { return $this->writeQtyFix('wrong_location_stock', $productId, $variationId, $locationId, $reason); }
    public function rebuildStockByLocation(int $productId, int $variationId, int $locationId, string $reason = 'rebuild_stock_by_location'): SmartStockFixLog { return $this->writeQtyFix('rebuild_stock_by_location', $productId, $variationId, $locationId, $reason); }
    public function recalculateAvailableQty(int $productId, int $variationId, int $locationId, string $reason = 'recalculate_available_qty'): SmartStockFixLog { return $this->writeQtyFix('recalculate_available_qty', $productId, $variationId, $locationId, $reason); }
    public function fixDuplicateIMEI(int $productId, int $variationId, int $locationId, string $reason = 'fix_duplicate_imei'): SmartStockFixLog { return $this->logOnlyFix('duplicate_imei', $productId, $variationId, $locationId, $reason); }
    public function fixDuplicateLot(int $productId, int $variationId, int $locationId, string $reason = 'fix_duplicate_lot'): SmartStockFixLog { return $this->logOnlyFix('duplicate_lot', $productId, $variationId, $locationId, $reason); }
    public function fixBrokenPurchaseSellMapping(int $productId, int $variationId, int $locationId, string $reason = 'fix_broken_purchase_sell_mapping'): SmartStockFixLog { return $this->logOnlyFix('broken_purchase_sell_mapping', $productId, $variationId, $locationId, $reason); }
    public function fixBrokenStockAdjustment(int $productId, int $variationId, int $locationId, string $reason = 'fix_broken_stock_adjustment'): SmartStockFixLog { return $this->logOnlyFix('broken_stock_adjustment', $productId, $variationId, $locationId, $reason); }
    public function fixPendingTransferIssue(int $productId, int $variationId, int $locationId, string $reason = 'fix_pending_transfer_issue'): SmartStockFixLog { return $this->logOnlyFix('pending_transfer_issue', $productId, $variationId, $locationId, $reason); }
    public function fixOrphanSellLines(int $productId, int $variationId, int $locationId, string $reason = 'fix_orphan_sell_lines'): SmartStockFixLog { return $this->logOnlyFix('orphan_sell_lines', $productId, $variationId, $locationId, $reason); }

    public function rollbackFix(int $fixLogId, ?string $reason = null): array
    {
        $log = SmartStockFixLog::find($fixLogId);
        if (! $log || ! $log->rollbackable || $log->is_rollback) return ['ok' => false, 'msg' => 'Rollback not allowed'];
        $target = DB::table('variation_location_details')->where('product_id', $log->product_id)->where('variation_id', $log->variation_id)->where('location_id', $log->location_id)->first();
        if (! $target) return ['ok' => false, 'msg' => 'Rollback target missing'];
        if ((float) $target->qty_available !== (float) $log->new_qty) return ['ok' => false, 'msg' => 'Unsafe rollback: stock has changed after fix'];
        DB::table('variation_location_details')->where('product_id', $log->product_id)->where('variation_id', $log->variation_id)->where('location_id', $log->location_id)->update(['qty_available' => $log->old_qty]);
        $rollback = SmartStockFixLog::create(['business_id' => $log->business_id, 'location_id' => $log->location_id, 'product_id' => $log->product_id, 'variation_id' => $log->variation_id, 'fix_type' => 'rollback_fix', 'problem_type' => $log->problem_type, 'old_qty' => $log->new_qty, 'new_qty' => $log->old_qty, 'reason' => $reason ?: 'rollback', 'risk_level' => 'high', 'reference_type' => $log->reference_type, 'reference_id' => $log->reference_id, 'before_payload' => $log->after_payload, 'after_payload' => $log->before_payload, 'is_rollback' => 1, 'rollbackable' => 0, 'rollback_of_fix_log_id' => $log->id, 'created_by' => auth()->id()]);
        $this->audit('rollback_fix', $log->location_id, 'fix_log', $log->id, ['qty_available' => $log->new_qty], ['qty_available' => $log->old_qty], $reason ?: 'rollback');
        return ['ok' => true, 'log' => $rollback];
    }

    private function writeQtyFix(string $type, int $productId, int $variationId, int $locationId, string $reason): SmartStockFixLog
    {
        $snapshot = $this->recalculateProductStock($productId, $variationId, $locationId);
        $newQty = max(0, (float) $snapshot['calculated']);
        DB::table('variation_location_details')->where('variation_id', $variationId)->where('product_id', $productId)->where('location_id', $locationId)->update(['qty_available' => $newQty]);
        $log = SmartStockFixLog::create(['business_id' => session('user.business_id'), 'location_id' => $locationId, 'product_id' => $productId, 'variation_id' => $variationId, 'fix_type' => $type, 'problem_type' => $type, 'old_qty' => $snapshot['currentQty'], 'new_qty' => $newQty, 'reason' => $reason, 'risk_level' => ((float) $snapshot['currentQty'] < 0 ? 'high' : 'medium'), 'reference_type' => 'variation_location_details', 'reference_id' => $variationId, 'before_payload' => json_encode($snapshot), 'after_payload' => json_encode(['qty_available' => $newQty, 'calculated' => $snapshot['calculated']]), 'is_rollback' => 0, 'rollbackable' => 1, 'created_by' => auth()->id()]);
        $this->audit('fix_' . $type, $locationId, 'variation', $variationId, ['qty_available' => $snapshot['currentQty']], ['qty_available' => $newQty], $reason);
        return $log;
    }

    private function logOnlyFix(string $type, int $productId, int $variationId, int $locationId, string $reason): SmartStockFixLog
    {
        $payload = ['note' => 'No direct change to original sell/purchase/transfer/adjustment transactions', 'affected_transaction_ids' => $this->transactionIds($variationId, $locationId)];
        $log = SmartStockFixLog::create(['business_id' => session('user.business_id'), 'location_id' => $locationId, 'product_id' => $productId, 'variation_id' => $variationId, 'fix_type' => $type, 'problem_type' => $type, 'old_qty' => null, 'new_qty' => null, 'reason' => $reason, 'risk_level' => 'medium', 'reference_type' => 'variation', 'reference_id' => $variationId, 'before_payload' => json_encode($payload), 'after_payload' => json_encode($payload), 'is_rollback' => 0, 'rollbackable' => 0, 'created_by' => auth()->id()]);
        $this->audit('fix_' . $type, $locationId, 'variation', $variationId, $payload, $payload, $reason);
        return $log;
    }

    private function transactionIds(int $variationId, int $locationId): array
    {
        return DB::table('transactions as t')->leftJoin('purchase_lines as pl', 'pl.transaction_id', '=', 't.id')->leftJoin('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->where('t.location_id', $locationId)->where(function ($q) use ($variationId) { $q->where('pl.variation_id', $variationId)->orWhere('tsl.variation_id', $variationId); })->limit(100)->pluck('t.id')->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function audit(string $actionType, ?int $locationId, ?string $refType, ?int $refId, $old, $new, ?string $reason): void
    {
        SmartStockActionLog::create(['user_id' => auth()->id(), 'business_id' => (int) session('user.business_id'), 'location_id' => $locationId, 'action_type' => $actionType, 'reference_type' => $refType, 'reference_id' => $refId, 'old_data' => json_encode($old), 'new_data' => json_encode($new), 'reason' => $reason]);
    }
}

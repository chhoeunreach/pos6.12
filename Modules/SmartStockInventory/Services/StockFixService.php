<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\SmartStockActionLog;
use Modules\SmartStockInventory\Models\SmartStockFixLog;

class StockFixService
{
    public function recalculateProductStock(int $productId, int $variationId, int $locationId): array
    {
        $businessId = (int) session('user.business_id');
        abort_unless(DB::table('business_locations')->where('id', $locationId)->where('business_id', $businessId)->exists(), 404);
        $details = app(\App\Utils\ProductUtil::class)->getVariationStockMisMatch($businessId, $variationId, $locationId)->first();
        abort_unless($details && (int) $details->product_id === $productId && $details->enable_stock, 404);

        return [
            'currentQty' => (float) $details->stock,
            'calculated' => (float) $details->total_stock_calculated,
            'opening_stock' => (float) $details->total_opening_stock,
            'purchased' => (float) $details->total_purchased,
            'purchase_returns' => (float) $details->total_purchase_return + (float) $details->total_combined_purchase_return,
            'sold' => (float) $details->total_sold,
            'sell_returns' => (float) $details->total_sell_return,
            'adjusted' => (float) $details->total_adjusted,
            'transferred_in' => (float) $details->total_purchase_transfered,
            'transferred_out' => (float) $details->total_sell_transfered,
            'manufactured' => (float) $details->total_manufactured,
            'production_used' => (float) $details->total_ingredients_used,
        ];
    }

    public function detectMismatch(array $filters): array
    {
        return app(\App\Services\StockMismatchDetectorService::class)->detectQuantityMismatches($filters);
    }

    public function previewFix(int $productId, int $variationId, int $locationId, string $problem = 'negative_stock'): array
    {
        $snapshot = $this->recalculateProductStock($productId, $variationId, $locationId);
        return ['problem' => $problem, 'current_system_qty' => $snapshot['currentQty'], 'correct_calculated_qty' => (float) $snapshot['calculated'], 'affected_transaction_ids' => $this->transactionIds($variationId, $locationId), 'risk_level' => ((float) $snapshot['currentQty'] < 0 ? 'high' : 'medium')];
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
        return DB::transaction(function () use ($type, $productId, $variationId, $locationId, $reason) {
            DB::table('variation_location_details')->where('variation_id', $variationId)->where('product_id', $productId)->where('location_id', $locationId)->lockForUpdate()->get();
            $snapshot = $this->recalculateProductStock($productId, $variationId, $locationId);
            $newQty = (float) $snapshot['calculated'];
            app(\App\Utils\ProductUtil::class)->fixVariationStockMisMatch((int) session('user.business_id'), $variationId, $locationId, $newQty);
            $log = SmartStockFixLog::create(['business_id' => session('user.business_id'), 'location_id' => $locationId, 'product_id' => $productId, 'variation_id' => $variationId, 'fix_type' => $type, 'problem_type' => $type, 'old_qty' => $snapshot['currentQty'], 'new_qty' => $newQty, 'reason' => $reason, 'risk_level' => ((float) $snapshot['currentQty'] < 0 ? 'high' : 'medium'), 'reference_type' => 'variation_location_details', 'reference_id' => $variationId, 'before_payload' => json_encode($snapshot), 'after_payload' => json_encode(['qty_available' => $newQty, 'calculated' => $snapshot['calculated']]), 'is_rollback' => 0, 'rollbackable' => 1, 'created_by' => auth()->id()]);
            $this->audit('fix_' . $type, $locationId, 'variation', $variationId, ['qty_available' => $snapshot['currentQty']], ['qty_available' => $newQty], $reason);
            return $log;
        });
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
            ->where('t.business_id', (int) session('user.business_id'))->where('t.location_id', $locationId)->where(function ($q) use ($variationId) { $q->where('pl.variation_id', $variationId)->orWhere('tsl.variation_id', $variationId); })->limit(100)->pluck('t.id')->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private function audit(string $actionType, ?int $locationId, ?string $refType, ?int $refId, $old, $new, ?string $reason): void
    {
        $userName = trim((string) ((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')));
        if ($userName === '') {
            $userName = (string) (auth()->user()->username ?? '');
        }

        SmartStockActionLog::create([
            'user_id' => auth()->id(),
            'user_name' => $userName,
            'business_id' => (int) session('user.business_id'),
            'module_name' => 'SmartStockInventory',
            'table_name' => $refType ? ('smart_' . trim($refType, '_')) : null,
            'record_id' => $refId,
            'location_id' => $locationId,
            'action_type' => $actionType,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'old_data' => json_encode($old),
            'new_data' => json_encode($new),
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }
}

<?php

namespace Modules\SmartStockInventory\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\SmartInventoryApproval;
use Modules\SmartStockInventory\Models\SmartInventoryAuditLog;
use Modules\SmartStockInventory\Models\SmartInventoryFreezeLog;
use Modules\SmartStockInventory\Models\SmartInventoryRecount;
use Modules\SmartStockInventory\Models\SmartInventoryVerification;
use Modules\SmartStockInventory\Models\SmartStockInventoryLine;
use Modules\SmartStockInventory\Models\SmartStockInventorySession;

class InventoryWorkflowService
{
    public function createSession(int $businessId, int $userId, array $data): SmartStockInventorySession
    {
        $session = SmartStockInventorySession::create([
            'business_id' => $businessId,
            'location_id' => $data['location_id'],
            'name' => $data['session_name'],
            'description' => $data['description'] ?? null,
            'warehouse' => $data['warehouse'] ?? null,
            'count_type' => $data['count_type'] ?? 'full_count',
            'count_method' => $data['count_method'] ?? 'manual',
            'count_by' => $data['count_by'] ?? 'product',
            'start_date' => $data['start_date'] ?? now(),
            'end_date' => $data['end_date'] ?? null,
            'blind_count' => (int) ($data['blind_count'] ?? 0),
            'status' => 'draft',
            'created_by' => $userId,
        ]);
        return $session;
    }

    public function logAudit(int $businessId, ?int $sessionId, ?int $lineId, string $action, Request $request, $old, $new): void
    {
        SmartInventoryAuditLog::create([
            'business_id' => $businessId,
            'session_id' => $sessionId,
            'line_id' => $lineId,
            'user_id' => auth()->id(),
            'action' => $action,
            'device' => substr((string) $request->userAgent(), 0, 180),
            'ip_address' => $request->ip(),
            'old_value' => $old ? json_encode($old) : null,
            'new_value' => $new ? json_encode($new) : null,
        ]);
    }

    public function verifyLine(int $businessId, SmartStockInventoryLine $line, float $verifiedQty, int $verifiedBy, float $recountThreshold): array
    {
        $status = abs($verifiedQty - (float) $line->actual_qty) > 0.0001 ? 'verification_failed' : 'verification_passed';
        $needRecount = abs($verifiedQty - (float) $line->actual_qty) >= $recountThreshold;

        SmartInventoryVerification::create([
            'business_id' => $businessId,
            'session_id' => $line->session_id,
            'line_id' => $line->id,
            'first_count_by' => $line->counted_by_user_id,
            'verified_by' => $verifiedBy,
            'first_qty' => $line->actual_qty,
            'verified_qty' => $verifiedQty,
            'status' => $status,
        ]);

        $old = $line->toArray();
        $line->verified_by_user_id = $verifiedBy;
        $line->verification_status = $status;
        $line->recount_required = $needRecount ? 1 : 0;
        $line->save();

        if ($needRecount) {
            SmartInventoryRecount::create([
                'business_id' => $businessId,
                'session_id' => $line->session_id,
                'line_id' => $line->id,
                'recount_reason' => 'Mismatch exceeds threshold',
                'recount_by' => $verifiedBy,
                'recount_date' => now(),
                'before_qty' => $line->actual_qty,
                'after_qty' => $verifiedQty,
            ]);
        }

        return ['old' => $old, 'new' => $line->toArray(), 'need_recount' => $needRecount];
    }

    public function approveSession(SmartStockInventorySession $session, string $level, string $note = ''): SmartInventoryApproval
    {
        $approval = SmartInventoryApproval::create([
            'business_id' => $session->business_id,
            'session_id' => $session->id,
            'approved_by' => auth()->id(),
            'approval_level' => $level,
            'status' => 'approved',
            'note' => $note,
            'approved_at' => now(),
        ]);

        $session->status = $level === 'manager' ? 'approved' : 'pending_review';
        $session->approved_by = auth()->id();
        $session->approved_at = now();
        $session->save();

        return $approval;
    }

    public function freezeSession(int $businessId, int $sessionId, string $freezeType, ?int $locationId, ?int $productId): SmartInventoryFreezeLog
    {
        return SmartInventoryFreezeLog::create([
            'business_id' => $businessId,
            'session_id' => $sessionId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'freeze_type' => $freezeType,
            'is_active' => 1,
            'created_by' => auth()->id(),
        ]);
    }

    public function adjustmentPreview(int $sessionId): array
    {
        $lines = DB::table('smart_stock_inventory_lines')
            ->where('session_id', $sessionId)
            ->whereNull('deleted_at')
            ->get();

        return $lines->map(function ($line) {
            $old = (float) $line->system_qty;
            $new = (float) $line->actual_qty;
            return [
                'line_id' => $line->id,
                'product' => $line->product_name,
                'sku' => $line->sku,
                'old_qty' => $old,
                'new_qty' => $new,
                'difference' => $new - $old,
                'stock_value_difference' => 0,
            ];
        })->all();
    }
}

<?php

namespace Modules\SmartStockInventory\Services\Enterprise;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SmartStockInventory\Models\Enterprise\SsiApproval;
use Modules\SmartStockInventory\Models\Enterprise\SsiAudit;
use Modules\SmartStockInventory\Models\Enterprise\SsiInvestigation;
use Modules\SmartStockInventory\Models\Enterprise\SsiSetting;
use Modules\SmartStockInventory\Repositories\Enterprise\SsiAuditRepository;

class SsiAuditService
{
    public const APPROVAL_LEVELS = [
        'counter',
        'supervisor',
        'warehouse_manager',
        'inventory_manager',
        'general_manager',
    ];

    public function __construct(private SsiAuditRepository $audits, private SsiLogService $logs)
    {
    }

    public function create(int $businessId, int $userId, array $data, Request $request): SsiAudit
    {
        $settings = SsiSetting::firstOrCreate(
            ['business_id' => $businessId],
            ['approval_levels' => self::APPROVAL_LEVELS]
        );

        $data['settings'] = array_merge($data['settings'] ?? [], [
            'approval_levels' => $settings->approval_levels ?: self::APPROVAL_LEVELS,
        ]);

        return DB::transaction(function () use ($businessId, $userId, $data, $request) {
            $audit = $this->audits->createAudit($businessId, $userId, $data);

            foreach (($audit->settings['approval_levels'] ?? self::APPROVAL_LEVELS) as $index => $level) {
                SsiApproval::create([
                    'business_id' => $businessId,
                    'audit_id' => $audit->id,
                    'approval_level' => $level,
                    'sequence' => $index + 1,
                    'status' => $index === 0 ? 'pending' : 'waiting',
                    'requested_by' => $userId,
                ]);
            }

            $this->logs->log($businessId, 'create_audit', [
                'audit_id' => $audit->id,
                'subject_type' => 'ssi_audits',
                'subject_id' => $audit->id,
                'new_values' => $audit->toArray(),
            ], $request);

            return $audit;
        });
    }

    public function start(SsiAudit $audit, Request $request): SsiAudit
    {
        $old = $audit->toArray();
        $audit->status = 'in_progress';
        $audit->started_at = $audit->started_at ?: now();
        $audit->save();
        $this->logs->log($audit->business_id, 'start_audit', ['audit_id' => $audit->id, 'old_values' => $old, 'new_values' => $audit->toArray()], $request);

        return $audit;
    }

    public function verifyItem(int $businessId, int $itemId, float $verifiedQty, int $userId, Request $request): void
    {
        $item = DB::table('ssi_audit_items')->where('business_id', $businessId)->where('id', $itemId)->first();
        abort_unless($item, 404);

        $difference = $verifiedQty - (float) $item->expected_qty;
        DB::table('ssi_audit_items')->where('id', $itemId)->update([
            'counted_qty' => $verifiedQty,
            'difference_qty' => $difference,
            'verification_status' => abs($difference) < 0.0001 ? 'matched' : 'mismatch',
            'mismatch_type' => $difference < 0 ? 'missing_imei' : ($difference > 0 ? 'extra_stock' : null),
            'verified_by' => $userId,
            'verified_at' => now(),
            'recount_required' => abs($difference) > 0.0001,
            'updated_at' => now(),
        ]);

        if (abs($difference) > 0.0001) {
            $this->createInvestigation($businessId, (int) $item->audit_id, $itemId, $difference < 0 ? 'missing_imei' : 'extra_imei', $userId, $request);
        }

        $this->logs->log($businessId, 'verify_item', [
            'audit_id' => (int) $item->audit_id,
            'audit_item_id' => $itemId,
            'log_type' => 'audit',
            'old_values' => (array) $item,
            'new_values' => ['verified_qty' => $verifiedQty, 'difference_qty' => $difference],
        ], $request);
    }

    public function approve(SsiAudit $audit, string $level, int $userId, ?string $note, Request $request): SsiApproval
    {
        return DB::transaction(function () use ($audit, $level, $userId, $note, $request) {
            $approval = SsiApproval::where('business_id', $audit->business_id)
                ->where('audit_id', $audit->id)
                ->where('approval_level', $level)
                ->firstOrFail();

            abort_unless(in_array($approval->status, ['pending', 'waiting'], true), 422, 'This approval step is not open.');

            $approval->status = 'approved';
            $approval->approved_by = $userId;
            $approval->approved_at = now();
            $approval->note = $note;
            $approval->save();

            SsiApproval::where('audit_id', $audit->id)
                ->where('sequence', $approval->sequence + 1)
                ->where('status', 'waiting')
                ->update(['status' => 'pending', 'updated_at' => now()]);

            $hasPending = SsiApproval::where('audit_id', $audit->id)->whereIn('status', ['pending', 'waiting'])->exists();
            $audit->status = $hasPending ? 'pending_approval' : 'approved';
            $audit->save();

            $this->logs->log($audit->business_id, 'approve_' . $level, [
                'audit_id' => $audit->id,
                'subject_type' => 'ssi_approvals',
                'subject_id' => $approval->id,
                'log_type' => 'approval',
                'new_values' => $approval->toArray(),
            ], $request);

            return $approval;
        });
    }

    public function createInvestigation(int $businessId, int $auditId, ?int $itemId, string $type, int $userId, Request $request): SsiInvestigation
    {
        $caseNo = 'INV-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        $investigation = SsiInvestigation::create([
            'business_id' => $businessId,
            'audit_id' => $auditId,
            'audit_item_id' => $itemId,
            'case_no' => $caseNo,
            'case_type' => $type,
            'status' => 'open',
            'priority' => in_array($type, ['lost', 'wrong_transfer'], true) ? 'high' : 'normal',
            'opened_by' => $userId,
            'opened_at' => now(),
            'notes' => $request->input('notes'),
            'payload' => $request->except(['_token']),
        ]);

        $this->logs->log($businessId, 'create_investigation', [
            'audit_id' => $auditId,
            'audit_item_id' => $itemId,
            'subject_type' => 'ssi_investigations',
            'subject_id' => $investigation->id,
            'log_type' => 'activity',
            'new_values' => $investigation->toArray(),
        ], $request);

        return $investigation;
    }
}

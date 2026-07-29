<?php

namespace Modules\HrSellManagement\Services;

use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HrSellManagement\Entities\HrSellApproval;
use Modules\HrSellManagement\Entities\HrSellNote;
use Modules\HrSellManagement\Entities\HrSellRecord;
use Modules\HrSellManagement\Entities\HrSellSetting;

class HrSellService
{
    public function __construct(private HrSellLogService $logs)
    {
    }

    public function setting(int $businessId): HrSellSetting
    {
        return HrSellSetting::firstOrCreate(
            ['business_id' => $businessId],
            [
                'commission_type' => config('hrsellmanagement.commission_default_type', 'percent'),
                'commission_value' => config('hrsellmanagement.commission_default_value', 0),
                'approval_levels' => config('hrsellmanagement.approval_levels', ['supervisor', 'manager']),
            ]
        );
    }

    public function linkTransaction(int $businessId, int $transactionId, int $hrUserId, int $createdBy, array $data, Request $request): HrSellRecord
    {
        $transaction = Transaction::where('business_id', $businessId)
            ->where('type', 'sell')
            ->whereIn('status', ['final', 'draft'])
            ->findOrFail($transactionId);
        $setting = $this->setting($businessId);
        $commissionType = $data['commission_type'] ?? $setting->commission_type;
        $commissionValue = (float) ($data['commission_value'] ?? $setting->commission_value);

        return DB::transaction(function () use ($businessId, $transaction, $hrUserId, $createdBy, $data, $request, $setting, $commissionType, $commissionValue) {
            $record = HrSellRecord::updateOrCreate(
                ['business_id' => $businessId, 'transaction_id' => $transaction->id],
                [
                    'location_id' => $transaction->location_id,
                    'hr_user_id' => $hrUserId,
                    'supervisor_id' => $data['supervisor_id'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'approval_status' => $setting->require_approval ? 'pending' : 'approved',
                    'commission_type' => $commissionType,
                    'commission_value' => $commissionValue,
                    'sale_total' => (float) $transaction->final_total,
                    'paid_total' => $this->paidTotal((int) $transaction->id),
                    'due_total' => max(0, (float) $transaction->final_total - $this->paidTotal((int) $transaction->id)),
                    'commission_amount' => $this->commissionAmount((float) $transaction->final_total, $commissionType, $commissionValue),
                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'follow_up_status' => $data['follow_up_status'] ?? 'none',
                    'internal_note' => $data['internal_note'] ?? null,
                    'created_by' => $createdBy,
                    'updated_by' => $createdBy,
                ]
            );

            foreach (($setting->approval_levels ?: ['supervisor', 'manager']) as $level) {
                HrSellApproval::firstOrCreate([
                    'business_id' => $businessId,
                    'hr_sell_record_id' => $record->id,
                    'level' => $level,
                ]);
            }

            $this->logs->log($businessId, 'link_transaction', [
                'hr_sell_record_id' => $record->id,
                'new_data' => $record->toArray(),
            ], $request);

            return $record;
        });
    }

    public function updateRecord(HrSellRecord $record, array $data, Request $request): HrSellRecord
    {
        $old = $record->toArray();
        $record->fill([
            'hr_user_id' => $data['hr_user_id'] ?? $record->hr_user_id,
            'supervisor_id' => $data['supervisor_id'] ?? $record->supervisor_id,
            'status' => $data['status'] ?? $record->status,
            'commission_type' => $data['commission_type'] ?? $record->commission_type,
            'commission_value' => $data['commission_value'] ?? $record->commission_value,
            'follow_up_date' => $data['follow_up_date'] ?? $record->follow_up_date,
            'follow_up_status' => $data['follow_up_status'] ?? $record->follow_up_status,
            'internal_note' => $data['internal_note'] ?? $record->internal_note,
            'updated_by' => auth()->id(),
        ]);
        $record->commission_amount = $this->commissionAmount((float) $record->sale_total, $record->commission_type, (float) $record->commission_value);
        $record->save();
        $this->logs->log((int) $record->business_id, 'update_record', ['hr_sell_record_id' => $record->id, 'old_data' => $old, 'new_data' => $record->toArray()], $request);

        return $record;
    }

    public function approve(HrSellRecord $record, string $level, string $status, ?string $note, Request $request): HrSellApproval
    {
        return DB::transaction(function () use ($record, $level, $status, $note, $request) {
            $approval = HrSellApproval::where('hr_sell_record_id', $record->id)->where('level', $level)->firstOrFail();
            $approval->status = $status;
            $approval->approved_by = auth()->id();
            $approval->approved_at = now();
            $approval->note = $note;
            $approval->save();

            $record->approval_status = $status === 'rejected'
                ? 'rejected'
                : (HrSellApproval::where('hr_sell_record_id', $record->id)->where('status', '!=', 'approved')->exists() ? 'pending' : 'approved');
            $record->save();

            $this->logs->log((int) $record->business_id, 'approval_' . $status, ['hr_sell_record_id' => $record->id, 'new_data' => $approval->toArray(), 'note' => $note], $request);

            return $approval;
        });
    }

    public function addNote(HrSellRecord $record, array $data, Request $request): HrSellNote
    {
        $note = HrSellNote::create([
            'business_id' => $record->business_id,
            'hr_sell_record_id' => $record->id,
            'note_type' => $data['note_type'],
            'note' => $data['note'],
            'next_follow_up_date' => $data['next_follow_up_date'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if (! empty($data['next_follow_up_date'])) {
            $record->follow_up_date = $data['next_follow_up_date'];
            $record->follow_up_status = 'scheduled';
            $record->save();
        }

        $this->logs->log((int) $record->business_id, 'add_note', ['hr_sell_record_id' => $record->id, 'new_data' => $note->toArray()], $request);

        return $note;
    }

    private function commissionAmount(float $saleTotal, string $type, float $value): float
    {
        return $type === 'fixed' ? $value : round($saleTotal * ($value / 100), 4);
    }

    private function paidTotal(int $transactionId): float
    {
        return (float) DB::table('transaction_payments')
            ->where('transaction_id', $transactionId)
            ->sum('amount');
    }
}

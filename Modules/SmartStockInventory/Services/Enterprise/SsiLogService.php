<?php

namespace Modules\SmartStockInventory\Services\Enterprise;

use Illuminate\Http\Request;
use Modules\SmartStockInventory\Models\Enterprise\SsiLog;

class SsiLogService
{
    public function log(int $businessId, string $action, array $context = [], ?Request $request = null): void
    {
        $user = auth()->user();
        SsiLog::create([
            'business_id' => $businessId,
            'audit_id' => $context['audit_id'] ?? null,
            'audit_item_id' => $context['audit_item_id'] ?? null,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'log_type' => $context['log_type'] ?? 'activity',
            'action' => $action,
            'user_id' => $user->id ?? null,
            'user_name' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) ?: (string) ($user->username ?? ''),
            'device_id' => $context['device_id'] ?? $request?->header('X-Device-Id'),
            'ip_address' => $request?->ip(),
            'old_values' => $context['old_values'] ?? null,
            'new_values' => $context['new_values'] ?? null,
            'note' => $context['note'] ?? null,
        ]);
    }
}

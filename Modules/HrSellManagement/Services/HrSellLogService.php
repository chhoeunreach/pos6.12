<?php

namespace Modules\HrSellManagement\Services;

use Illuminate\Http\Request;
use Modules\HrSellManagement\Entities\HrSellLog;

class HrSellLogService
{
    public function log(int $businessId, string $action, array $context = [], ?Request $request = null): void
    {
        $user = auth()->user();
        HrSellLog::create([
            'business_id' => $businessId,
            'hr_sell_record_id' => $context['hr_sell_record_id'] ?? null,
            'action' => $action,
            'user_id' => $user->id ?? null,
            'user_name' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) ?: (string) ($user->username ?? ''),
            'old_data' => isset($context['old_data']) ? json_encode($context['old_data']) : null,
            'new_data' => isset($context['new_data']) ? json_encode($context['new_data']) : null,
            'ip_address' => $request?->ip(),
            'note' => $context['note'] ?? null,
        ]);
    }
}

<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Entities\LoanPaymentDetail;

class LoanPaymentService
{
    public function savePaymentDetails(int $paymentId, array $details): void
    {
        foreach ($details as $detail) {
            $paymentMethodId = Arr::get($detail, 'payment_method_id');
            $paymentMethod = null;

            if (! empty($paymentMethodId) && Schema::hasTable('payment_methods')) {
                $paymentMethod = DB::table('payment_methods')->find($paymentMethodId);
            }

            LoanPaymentDetail::create([
                'payment_id' => $paymentId,
                'payment_method_id' => $paymentMethod->id ?? null,
                'payment_method_snapshot' => $paymentMethod->name ?? Arr::get($detail, 'payment_method_snapshot', 'Unknown'),
                'currency' => Arr::get($detail, 'currency', 'USD'),
                'amount' => (float) Arr::get($detail, 'amount', 0),
                'exchange_rate' => (float) Arr::get($detail, 'exchange_rate', 1),
                'amount_base' => (float) Arr::get($detail, 'amount_base', Arr::get($detail, 'amount', 0)),
                'reference_number' => Arr::get($detail, 'reference_number'),
                'note' => Arr::get($detail, 'note'),
            ]);
        }
    }

    public function syncPaymentMethodsFromPos(): array
    {
        if (! Schema::hasTable('payment_methods')) {
            return [];
        }

        $rows = DB::table('payment_methods')
            ->select('id', 'name', 'is_active')
            ->orderBy('name')
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'is_active' => (bool) $row->is_active,
        ])->all();
    }
}

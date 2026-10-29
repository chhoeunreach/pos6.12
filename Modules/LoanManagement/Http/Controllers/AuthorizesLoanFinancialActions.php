<?php

namespace Modules\LoanManagement\Http\Controllers;

trait AuthorizesLoanFinancialActions
{
    protected function authorizeFinancialAction(array $permissions): void
    {
        $user = auth()->user();
        abort_unless($user && collect($permissions)->contains(fn ($permission) => $user->can($permission)), 403, 'Unauthorized financial action.');
    }

    protected function paymentHasPostedAmount(object $payment): bool
    {
        return max(abs((float) ($payment->amount ?? 0)), abs((float) ($payment->total_paid ?? 0)), abs((float) ($payment->total_paid_base ?? 0))) > 0;
    }
}

<?php

namespace Modules\LoanManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\LoanManagement\Services\LoanUserSyncService;

class EnsureLoanUserExists
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $loanUserId = app(LoanUserSyncService::class)->syncFromAuthenticatedUser($request->user());

            if ($loanUserId) {
                session(['loan_management.user_id' => $loanUserId]);
            }
        }

        return $next($request);
    }
}

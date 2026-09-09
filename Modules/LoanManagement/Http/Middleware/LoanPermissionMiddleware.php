<?php

namespace Modules\LoanManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoanPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user() ?: auth()->user();
        $permissions = preg_split('/[|,]/', $permission) ?: [];
        $allowed = $user && collect($permissions)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->contains(fn ($item) => $user->can($item));

        abort_unless($allowed, 403, 'Unauthorized action.');

        return $next($request);
    }
}

<?php

namespace Modules\LoanManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoanPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $permissions = preg_split('/[|,]/', $permission) ?: [];
        $allowed = auth()->check() && collect($permissions)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->contains(fn ($item) => auth()->user()->can($item));

        abort_unless($allowed, 403, 'Unauthorized action.');

        return $next($request);
    }
}

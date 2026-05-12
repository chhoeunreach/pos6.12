<?php

namespace Modules\LoanManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoanPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless(auth()->check() && auth()->user()->can($permission), 403, 'Unauthorized action.');

        return $next($request);
    }
}

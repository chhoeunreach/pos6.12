<?php

namespace Modules\KneayerngLinks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UseKneayerngLinksDatabase
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
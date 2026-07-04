<?php

namespace Modules\KneayerngLinks\Providers;

use Illuminate\Support\ServiceProvider;

class UseKneayerngLinksDatabase
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
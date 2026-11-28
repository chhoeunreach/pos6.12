<?php

namespace Modules\Social\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        if (is_file(__DIR__ . '/../Routes/web.php')) {
            Route::middleware('web')->group(__DIR__ . '/../Routes/web.php');
        }
        if (is_file(__DIR__ . '/../Routes/api.php')) {
            Route::prefix('api')->middleware('api')->group(__DIR__ . '/../Routes/api.php');
        }
    }
}

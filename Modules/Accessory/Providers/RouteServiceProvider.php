<?php

namespace Modules\Accessory\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Accessory\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware(config('accessory.middleware', ['web']))
            ->prefix(config('accessory.route_prefix', 'accessory-pos'))
            ->as(config('accessory.route_name_prefix', 'accessory.'))
            ->namespace($this->moduleNamespace)
            ->group(module_path('Accessory', 'Routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api/' . config('accessory.route_prefix', 'accessory-pos'))
            ->middleware(['api', 'auth:api', 'accessory.database'])
            ->as('accessory.api.')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Accessory', 'Routes/api.php'));
    }
}

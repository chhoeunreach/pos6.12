<?php

namespace Modules\Service\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\Service\Http\Controllers';

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
        Route::middleware(config('service.middleware', ['web']))
            ->prefix(config('service.route_prefix', 'service'))
            ->as(config('service.route_name_prefix', 'service.'))
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', 'Routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api/' . config('service.route_prefix', 'service'))
            ->middleware(['api', 'auth:api', 'service.database'])
            ->as('service.api.')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Service', 'Routes/api.php'));
    }
}

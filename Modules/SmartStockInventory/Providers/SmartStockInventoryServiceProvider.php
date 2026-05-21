<?php

namespace Modules\SmartStockInventory\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\SmartStockInventory\Http\Middleware\SmartStockAccessMiddleware;

class SmartStockInventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRouteMiddlewareAlias();
        $this->registerConfig();
        $this->registerViews();
        $this->registerMigrations();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('smartstockinventory.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'smartstockinventory');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'smartstockinventory');
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    private function registerRouteMiddlewareAlias(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ssi.access', SmartStockAccessMiddleware::class);
    }
}

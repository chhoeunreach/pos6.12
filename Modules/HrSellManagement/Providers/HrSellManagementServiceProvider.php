<?php

namespace Modules\HrSellManagement\Providers;

use Illuminate\Support\ServiceProvider;

class HrSellManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'hrsellmanagement');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('hrsellmanagement.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'hrsellmanagement');
    }
}

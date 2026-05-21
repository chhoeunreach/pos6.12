<?php

namespace Modules\WarrantyCardPrint\Providers;

use Illuminate\Support\ServiceProvider;

class WarrantyCardPrintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'warrantycardprint');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('warrantycardprint.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'warrantycardprint');
    }
}

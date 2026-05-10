<?php

namespace Modules\SmartStockInventory\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SmartStockInventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerMigrations();
        $this->ensureModuleMarkedInstalled();
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

    private function ensureModuleMarkedInstalled(): void
    {
        try {
            if (! class_exists(\App\System::class) || ! Schema::hasTable('system')) {
                return;
            }

            $key = 'smartstockinventory_version';
            if (empty(\App\System::getProperty($key))) {
                \App\System::addProperty($key, config('smartstockinventory.module_version', '1.0.0'));
            }
        } catch (\Throwable $e) {
        }
    }
}

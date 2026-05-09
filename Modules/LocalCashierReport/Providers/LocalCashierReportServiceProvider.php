<?php

namespace Modules\LocalCashierReport\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class LocalCashierReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->ensureModuleMarkedInstalled();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('localcashierreport.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'localcashierreport');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'localcashierreport');
    }

    private function ensureModuleMarkedInstalled(): void
    {
        try {
            if (! class_exists(\App\System::class) || ! Schema::hasTable('system')) {
                return;
            }

            $key = 'localcashierreport_version';
            if (empty(\App\System::getProperty($key))) {
                \App\System::addProperty($key, config('localcashierreport.module_version', '1.0.0'));
            }
        } catch (\Throwable $e) {
        }
    }
}

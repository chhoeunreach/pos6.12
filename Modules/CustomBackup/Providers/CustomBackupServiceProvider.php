<?php

namespace Modules\CustomBackup\Providers;

use Illuminate\Support\ServiceProvider;

class CustomBackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('custombackup.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'custombackup');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'custombackup');
    }
}

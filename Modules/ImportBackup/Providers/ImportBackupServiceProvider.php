<?php

namespace Modules\ImportBackup\Providers;

use Illuminate\Support\ServiceProvider;

class ImportBackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('importbackup.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'importbackup');
    }
}


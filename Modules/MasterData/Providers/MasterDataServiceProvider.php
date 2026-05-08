<?php

namespace Modules\MasterData\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MasterDataServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->ensureModuleMarkedInstalled();
        $this->registerViews();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'masterdata');
    }

    private function ensureModuleMarkedInstalled(): void
    {
        try {
            if (! class_exists(\App\System::class)) {
                return;
            }
            if (! Schema::hasTable('system')) {
                return;
            }

            $key = 'masterdata_version';
            if (empty(\App\System::getProperty($key))) {
                \App\System::addProperty($key, '1.0.0');
            }
        } catch (\Throwable $e) {
        }
    }
}


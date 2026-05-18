<?php

namespace Modules\WarrantyCardPrint\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class WarrantyCardPrintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->ensureModuleMarkedInstalled();
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'warrantycardprint');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function ensureModuleMarkedInstalled(): void
    {
        try {
            if (! class_exists(\App\System::class) || ! Schema::hasTable('system')) {
                return;
            }

            if (empty(\App\System::getProperty('warrantycardprint_version'))) {
                \App\System::addProperty('warrantycardprint_version', '1.0.0');
            }
        } catch (\Throwable $e) {
        }
    }
}

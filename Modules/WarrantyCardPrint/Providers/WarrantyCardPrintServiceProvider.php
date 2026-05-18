<?php

namespace Modules\WarrantyCardPrint\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class WarrantyCardPrintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->ensureModuleMarkedInstalled();
        $this->ensurePermissionsExist();
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

    private function ensurePermissionsExist(): void
    {
        try {
            if (! Schema::hasTable('permissions')) {
                return;
            }

            Permission::firstOrCreate([
                'name' => 'warranty_card_print.view',
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }
}

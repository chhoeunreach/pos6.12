<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        foreach (['hr_sell.report.edit', 'hr_sell.report.delete'] as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['hr_sell.report.edit', 'hr_sell.report.delete'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

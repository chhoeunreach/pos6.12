<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class LoanManagementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(Permission::class) || ! Schema::hasTable('permissions')) {
            return;
        }

        foreach ((array) config('loanmanagement.permissions', []) as $permission) {
            try {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            } catch (\Throwable $e) {
                // Ignore individual duplicate / permission error
            }
        }
    }
}


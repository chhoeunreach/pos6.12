<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class LoanManagementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('loanmanagement.permissions', []) as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}


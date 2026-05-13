<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;

class LoanManagementDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LoanManagementPermissionSeeder::class);
        $this->call(LoanManagementReferenceSeeder::class);
    }
}

<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(LoanManagementDatabaseSeeder::class);
    }
}


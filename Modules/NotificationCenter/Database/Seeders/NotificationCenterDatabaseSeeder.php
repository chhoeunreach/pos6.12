<?php

namespace Modules\NotificationCenter\Database\Seeders;

use Illuminate\Database\Seeder;

class NotificationCenterDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TelegramConfigMigrateSeeder::class);
    }
}

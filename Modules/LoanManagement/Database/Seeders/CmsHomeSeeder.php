<?php

namespace Modules\LoanManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\LoanManagement\Services\BusinessSettingsService;

class CmsHomeSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSettingsService::seedCmsDefaults();
        $this->command?->info('CMS defaults and sample brands initialized. Existing content preserved.');
    }
}

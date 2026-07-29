<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HrSellManagementModuleTest extends TestCase
{
    public function test_hr_sell_routes_are_registered()
    {
        $this->assertTrue(Route::has('hr-sell.dashboard'));
        $this->assertTrue(Route::has('hr-sell.sales.index'));
        $this->assertTrue(Route::has('hr-sell.sales.link'));
        $this->assertTrue(Route::has('hr-sell.reports.index'));
        $this->assertTrue(Route::has('hr-sell.settings.index'));
    }

    public function test_hr_sell_module_files_exist()
    {
        $this->assertFileExists(base_path('Modules/HrSellManagement/module.json'));
        $this->assertFileExists(base_path('Modules/HrSellManagement/Database/Migrations/2026_07_29_000001_create_hr_sell_management_tables.php'));
        $this->assertFileExists(base_path('Modules/HrSellManagement/Http/Controllers/DataController.php'));
    }
}

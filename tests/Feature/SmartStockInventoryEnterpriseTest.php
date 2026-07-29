<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmartStockInventoryEnterpriseTest extends TestCase
{
    public function test_enterprise_routes_are_registered()
    {
        $this->assertTrue(Route::has('ssi.enterprise.audit.index'));
        $this->assertTrue(Route::has('ssi.enterprise.audit.store'));
        $this->assertTrue(Route::has('ssi.enterprise.scanner.scan'));
        $this->assertTrue(Route::has('ssi.enterprise.report.index'));
    }

    public function test_enterprise_migration_file_uses_only_ssi_tables()
    {
        $migration = file_get_contents(base_path('Modules/SmartStockInventory/Database/Migrations/2026_07_29_000001_create_ssi_enterprise_tables.php'));

        foreach ([
            'ssi_audits',
            'ssi_audit_items',
            'ssi_audit_scans',
            'ssi_investigations',
            'ssi_approvals',
            'ssi_logs',
            'ssi_dashboard_cache',
            'ssi_settings',
        ] as $table) {
            $this->assertStringContainsString($table, $migration);
        }

        $this->assertStringNotContainsString("Schema::table('transactions'", $migration);
        $this->assertStringNotContainsString("Schema::table('products'", $migration);
        $this->assertStringNotContainsString('Permission::', $migration);
    }
}

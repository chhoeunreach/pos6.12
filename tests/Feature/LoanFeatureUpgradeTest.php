<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanFeatureUpgradeTest extends LoanModuleFeatureTestCase
{
    public function test_upgrade_is_additive_and_can_run_twice(): void
    {
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('mysql_loan');
        $schema = Schema::connection('mysql_loan');
        $schema->create('loans', fn (Blueprint $t) => $t->increments('id'));
        $schema->create('customer_groups', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        $schema->create('loan_payment_methods', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->boolean('is_active')->default(true);
        });
        DB::connection('mysql_loan')->table('customer_groups')->insert(['name' => 'Existing customers']);
        $this->assertSame(0, Artisan::call('loan-management:upgrade-features'));
        $this->assertTrue($schema->hasTable('loan_quotations'));
        $this->assertTrue($schema->hasTable('loan_quotation_items'));
        $this->assertTrue($schema->hasTable('loan_quotation_schedules'));
        $this->assertTrue($schema->hasColumn('loans', 'daily_penalty_rate'));
        $this->assertTrue($schema->hasColumn('loans', 'ptp_date'));
        $this->assertTrue($schema->hasColumn('loans', 'ptp_amount'));
        $this->assertTrue($schema->hasColumn('loan_payment_methods', 'code'));
        $this->assertTrue($schema->hasColumn('loan_payment_methods', 'sort_order'));
        $this->assertSame('Existing customers', DB::connection('mysql_loan')->table('customer_groups')->value('name'));
        $this->assertSame(0, Artisan::call('loan-management:upgrade-features'));
        $this->assertEquals(4, DB::connection('mysql_loan')->table('migrations')->count());
        DB::purge('mysql_loan');
    }
}

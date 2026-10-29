<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CbcExportTest extends TestCase
{
    private static int $userId = 60000;
    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        DB::purge('mysql_loan');
        Carbon::setTestNow('2026-10-03 10:00:00');
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->authenticate(['*']);
        $this->controller = new class extends DashboardController {
            protected function loanReportLocationOptions(): array { return []; }
            protected function loanReportIsKhmer(): bool { return false; }
        };
        Schema::connection('mysql_loan')->create('loans', function (Blueprint $table) {
            $table->id(); $table->string('loan_number'); $table->integer('customer_id')->nullable();
            $table->integer('business_location_id')->nullable(); $table->string('customer_name_snapshot');
            $table->string('customer_phone_snapshot'); $table->string('currency');
            $table->decimal('principal_amount'); $table->decimal('balance_amount');
            $table->date('loan_date'); $table->string('status'); $table->softDeletes();
        });
        Schema::connection('mysql_loan')->create('loan_customers', function (Blueprint $table) {
            $table->id(); $table->string('name')->nullable(); $table->string('khmer_name')->nullable();
            $table->string('id_card_number')->nullable();
        });
        Schema::connection('mysql_loan')->create('loan_business_locations', function (Blueprint $table) {
            $table->id(); $table->string('name');
        });
        Schema::connection('mysql_loan')->create('loan_payment_schedules', function (Blueprint $table) {
            $table->id(); $table->integer('loan_id'); $table->date('due_date');
            $table->decimal('amount_balance'); $table->string('status'); $table->softDeletes();
        });
        Schema::connection('mysql_loan')->create('loan_payments', function (Blueprint $table) {
            $table->id(); $table->integer('loan_id'); $table->timestamp('paid_at');
            $table->decimal('amount'); $table->string('status'); $table->softDeletes();
        });
        DB::connection('mysql_loan')->table('loan_customers')->insert(['id' => 1, 'name' => '=SUM(1)', 'id_card_number' => 'ID-001']);
        DB::connection('mysql_loan')->table('loan_business_locations')->insert(['id' => 1, 'name' => 'Branch A']);
        foreach ([['USD', 100], ['KHR', 400000]] as $index => [$currency, $balance]) {
            DB::connection('mysql_loan')->table('loans')->insert([
                'id' => $index + 1, 'loan_number' => 'LOAN-'.($index + 1), 'customer_id' => 1, 'business_location_id' => $index + 1,
                'customer_name_snapshot' => 'Test Customer', 'customer_phone_snapshot' => '012345678',
                'currency' => $currency, 'principal_amount' => $balance + 100, 'balance_amount' => $balance,
                'loan_date' => '2026-09-01', 'status' => 'active',
            ]);
        }
        DB::connection('mysql_loan')->table('loan_payment_schedules')->insert([
            ['loan_id' => 1, 'due_date' => '2026-09-01', 'amount_balance' => 70, 'status' => 'partial'],
            ['loan_id' => 1, 'due_date' => '2026-08-01', 'amount_balance' => 999, 'status' => 'cancelled'],
            ['loan_id' => 1, 'due_date' => '2026-10-03', 'amount_balance' => 30, 'status' => 'pending'],
        ]);
        DB::connection('mysql_loan')->table('loan_payments')->insert([
            ['loan_id' => 1, 'paid_at' => '2026-10-02 12:00:00', 'amount' => 40, 'status' => 'confirmed'],
            ['loan_id' => 1, 'paid_at' => '2026-10-02 12:00:00', 'amount' => 999, 'status' => 'cancelled'],
            ['loan_id' => 1, 'paid_at' => '2026-10-05 12:00:00', 'amount' => 10, 'status' => 'confirmed'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); DB::disconnect('mysql_loan'); parent::tearDown();
    }

    private function authenticate(array $permissions): void
    {
        auth()->setUser(new class(['id' => ++self::$userId], $permissions) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __construct(array $attributes, private array $permissions) { parent::__construct($attributes); }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool { return in_array('*', $this->permissions, true) || in_array($ability, $this->permissions, true); }
        });
    }

    private function request(array $query = []): Request
    {
        $request = Request::create('/loan-management/reports/cbc-export', 'GET', $query);
        $request->setLaravelSession(session()->driver());
        $route = app('router')->getRoutes()->getByName('loan-management.reports.cbc-export');
        $route->bind($request); $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        return $request;
    }

    public function testCoreSchemaAmountsDatesAndCurrencies(): void
    {
        $data = $this->controller->cbcExport($this->request())->getData();
        $this->assertSame(2, $data['summary']['records']);
        $this->assertEquals(100, $data['summary']['currencies']['USD']['balance']);
        $this->assertEquals(400000, $data['summary']['currencies']['KHR']['balance']);
        $row = $data['loans']->first();
        $this->assertSame(32, $row->max_dpd);
        $this->assertEquals(70, $row->overdue_amount);
        $this->assertEquals(40, $row->total_repaid);
        $this->assertSame('ID-001', $row->customer_national_id);
        $this->assertContains('Date of birth', $row->missing_details);
        $this->assertStringContainsString('cbc_export_table', $this->controller->cbcExport($this->request())->render());
    }

    public function testBranchAndSearchFiltersApplyToPreviewAndCsv(): void
    {
        $filters = ['location_id' => 1, 'search' => 'LOAN-1'];
        $this->assertSame(1, $this->controller->cbcExport($this->request($filters))->getData()['loans']->total());
        $response = $this->controller->cbcExport($this->request($filters + ['format' => 'csv']));
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $file = fopen('php://memory', 'r+'); fwrite($file, substr($csv, 3)); rewind($file);
        $header = fgetcsv($file, escape: ''); $row = fgetcsv($file, escape: '');
        $this->assertCount(count($header), $row);
        $this->assertSame('LOAN-1', $row[3]);
        $this->assertSame("'=SUM(1)", $row[5]);
        $this->assertFalse(fgetcsv($file, escape: '')); fclose($file);
    }

    public function testReportViewDoesNotGrantDownloadPermission(): void
    {
        $this->authenticate(['loan_management.reports.view']);
        $this->assertFalse($this->controller->cbcExport($this->request())->getData()['canExport']);
        $this->expectException(HttpException::class);
        $this->controller->cbcExport($this->request(['format' => 'csv']));
    }

    public function testInvalidMonthIsRejectedBeforeQueries(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->cbcExport($this->request(['month' => '2026-13']));
    }

    public function testHistoricalSelectionDisclosesCurrentSnapshot(): void
    {
        $data = $this->controller->cbcExport($this->request(['month' => '2026-09']))->getData();
        $this->assertSame('2026-09-30', $data['filters']['cutoff_date']);
        $this->assertSame('2026-10-03', $data['filters']['snapshot_date']);
        $this->assertEquals(0, $data['loans']->first()->total_repaid);
    }

    public function testMissingOptionalTablesStillProducesReport(): void
    {
        foreach (['loan_payment_schedules', 'loan_payments', 'loan_customers', 'loan_business_locations'] as $table) { Schema::connection('mysql_loan')->drop($table); }
        $data = $this->controller->cbcExport($this->request())->getData();
        $this->assertSame(2, $data['summary']['records']);
        $this->assertSame(0, $data['loans']->first()->max_dpd);
        $this->assertEquals(0, $data['loans']->first()->total_repaid);
    }

    public function testFutureMonthAndUnauthorizedAccessAreRejected(): void
    {
        try {
            $this->controller->cbcExport($this->request(['month' => '2026-11']));
            $this->fail('Future reporting periods must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('month', $exception->errors());
        }
        $this->authenticate([]);
        try {
            $this->controller->cbcExport($this->request());
            $this->fail('Unauthorized report access must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function testDraftDeletedAndFutureLoansAreExcluded(): void
    {
        $loan = (array) DB::connection('mysql_loan')->table('loans')->find(1);
        foreach ([['status' => 'draft'], ['deleted_at' => '2026-10-01'], ['loan_date' => '2026-11-01']] as $changes) {
            unset($loan['id']);
            DB::connection('mysql_loan')->table('loans')->insert(array_merge($loan, $changes));
        }
        $this->assertSame(2, $this->controller->cbcExport($this->request())->getData()['summary']['records']);
    }
}

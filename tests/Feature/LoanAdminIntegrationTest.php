<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoanAdminIntegrationTest extends TestCase
{
    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'excel.temporary_files.local_path' => sys_get_temp_dir()]);
        DB::purge('mysql_loan');
        $this->authenticate(true);
        Schema::connection('mysql_loan')->create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number');
            $table->unsignedInteger('customer_id');
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->decimal('principal_amount', 12, 2);
            $table->string('currency');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_loan')->create('loan_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
        DB::connection('mysql_loan')->table('loan_customers')->insert([
            'id' => 1, 'name' => 'Test Customer', 'phone' => '012345678', 'address' => 'Test Address',
        ]);
        DB::connection('mysql_loan')->table('loans')->insert([
            'id' => 1, 'loan_number' => 'TEST-001', 'customer_id' => 1,
            'customer_name_snapshot' => 'Test Customer', 'customer_phone_snapshot' => '012345678',
            'principal_amount' => 2000, 'currency' => 'USD',
        ]);
        $this->controller = new DashboardController;
    }

    protected function tearDown(): void
    {
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    private function authenticate(bool $canEdit): void
    {
        auth()->setUser(new class(['id' => 1], $canEdit) extends GenericUser {
            public function __construct(array $attributes, private bool $canEdit)
            {
                parent::__construct($attributes);
            }

            public function hasRole($roles, $guard = null): bool { return false; }

            public function __get($key) { return $this->attributes[$key] ?? null; }

            public function can($ability, $arguments = []): bool
            {
                return $ability !== 'loan_management.edit' || $this->canEdit;
            }
        });
    }

    public function testUpdatePersistsAndPreservesUnsubmittedCustomerFields(): void
    {
        $response = $this->controller->adminLoanInlineUpdate(Request::create('/', 'POST', [
            'expected_loan_id' => 1, 'expected_loan_number' => 'TEST-001', 'expected_customer_id' => 1,
            'principal_amount' => '2500.125', 'currency' => 'khr', 'note' => 'Verified',
        ]), '1');
        $this->assertTrue($response->getData(true)['success']);
        $loan = DB::connection('mysql_loan')->table('loans')->find(1);
        $this->assertEquals(2500.13, $loan->principal_amount);
        $this->assertSame('KHR', $loan->currency);
        $this->assertSame('Verified', $loan->note);
        $customer = DB::connection('mysql_loan')->table('loan_customers')->find(1);
        $this->assertSame('Test Customer', $customer->name);
        $this->assertSame('012345678', $customer->phone);
        $this->assertSame('Test Address', $customer->address);
    }

    public function testStaleFormIsRejectedWithoutChangingLoan(): void
    {
        try {
            $this->controller->adminLoanInlineUpdate(Request::create('/', 'POST', [
                'expected_loan_number' => 'WRONG', 'principal_amount' => 999,
            ]), '1');
            $this->fail('Expected conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertEquals(2000, DB::connection('mysql_loan')->table('loans')->find(1)->principal_amount);
        }
    }

    public function testCustomerEditsSyncOnlySubmittedFields(): void
    {
        $this->controller->adminLoanInlineUpdate(Request::create('/', 'POST', [
            'customer_name_snapshot' => 'Updated Customer',
        ]), '1');
        $customer = DB::connection('mysql_loan')->table('loan_customers')->find(1);
        $this->assertSame('Updated Customer', $customer->name);
        $this->assertSame('012345678', $customer->phone);
        $this->assertSame('Test Address', $customer->address);
    }

    public function testNegativeAmountIsRejected(): void
    {
        try {
            $this->controller->adminLoanInlineUpdate(Request::create('/', 'POST', [
                'principal_amount' => -1,
            ]), '1');
            $this->fail('Expected validation failure.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('principal_amount', $exception->errors());
            $this->assertEquals(2000, DB::connection('mysql_loan')->table('loans')->find(1)->principal_amount);
        }
    }

    public function testViewOnlyUserCannotUpdateLoan(): void
    {
        $this->authenticate(false);
        $this->expectException(HttpException::class);
        $this->controller->adminLoanInlineUpdate(Request::create('/', 'POST', ['note' => 'Denied']), '1');
    }

    public function testStandaloneExportProducesReadableXlsx(): void
    {
        $controller = new class extends DashboardController {
            protected function buildYearlyLoanSummary(array $filters): array
            {
                $row = $this->emptyYearlySummaryRow(2026);
                $row['principal_total'] = 24000;
                return ['rows' => [$row]];
            }
        };
        $response = $controller->adminLoanExport(Request::create('/', 'GET', [
            'start_year' => 2026, 'end_year' => 2026,
        ]));
        $path = $response->getFile()->getPathname();
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('Year', $sheet->getCell('A1')->getValue());
            $this->assertEquals(2026, $sheet->getCell('A2')->getValue());
            $this->assertEquals(24000, $sheet->getCell('C2')->getValue());
            $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testYearlyPaymentAdjustmentsReachAdminRows(): void
    {
        $controller = new class extends DashboardController {
            protected function yearlyLoanAggregates(array $filters) { return collect(); }
            protected function yearlyScheduleAggregates(array $filters) { return collect(); }
            protected function yearlyPaymentAggregates(array $filters)
            {
                return collect([(object) [
                    'report_year' => 2026, 'payment_total' => 125,
                    'collection_payment_total' => 100, 'deposit_payment_total' => 25,
                    'penalty_total' => 8, 'discount_total' => 3,
                ]]);
            }
            public function rows(): array
            {
                return $this->adminLoanRows($this->buildYearlyLoanSummary([
                    'start_year' => 2026, 'end_year' => 2026,
                ])['rows']);
            }
        };
        $paid = $controller->rows()[0]['general_paid'];
        $this->assertSame(100.0, $paid['principal_paid']);
        $this->assertSame(25.0, $paid['interest_paid']);
        $this->assertSame(8.0, $paid['penalties_received']);
        $this->assertSame(3.0, $paid['interest_deducted']);
    }

    public function testAdminInstallmentKeepsOriginalLayoutAndReportControls(): void
    {
        auth()->setUser(new class(['id' => 1, 'name' => 'Test Staff']) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function hasRole($roles, $guard = null): bool { return false; }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool { return true; }
        });
        config(['session.driver' => 'array', 'view.compiled' => sys_get_temp_dir(), 'cache.default' => 'array']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        \Illuminate\Support\Facades\Cache::put('loan_management.sidebar_badges', [], 600);
        session()->put('user.language', 'en');
        $html = view('loanmanagement::admin_loan.index', [
            'filters' => ['start_year' => 2026, 'end_year' => 2026, 'location_id' => '', 'search' => ''],
            'payload' => ['adminRows' => [], 'adminMonthlyRows' => []],
            'locations' => [], 'isKhmer' => false,
        ])->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach (['loanManagementApp', 'loanManagementMain', 'loanManagementSidebar', 'adminLoanFilter', 'admin-loan-react-root', 'root', 'adminLoanDetailModal'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
        }
        $this->assertSame(1, $xpath->query('//body')->length);
        $this->assertSame(1, $xpath->query('//*[@id="loanManagementMain" and contains(@class,"admin-loan-main")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="loanManagementHeader"]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="adminLoanFilter"]//input[@name="date_from"]')->length);
        $this->assertStringContainsString('window.__LM_SYSTEM_BUSINESS_NAME__', $html);
        $this->assertSame(1, $xpath->query('//*[@id="loanBusinessTheme"]')->length);
    }

    public function testDetailsRenderWhenTelegramRoutesAreUnavailable(): void
    {
        config(['session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('loan-management.customers.telegram.link'));
        $html = view('loanmanagement::admin_loan.details', [
            'year' => 2026, 'group' => 'all', 'isKhmer' => false,
            'loans' => collect([(object) [
                'id' => 1, 'customer_id' => 1, 'loan_number' => 'TEST-001',
                'loan_date' => '2026-01-01', 'customer_name' => 'Test Customer',
                'customer_phone' => '012345678', 'location_name' => 'Test Branch',
                'principal_amount' => 2000, 'paid_amount' => 100, 'balance_amount' => 1900,
                'status' => 'active',
            ]]),
        ])->render();
        $this->assertStringContainsString('TEST-001', $html);
        $this->assertStringContainsString('data-edit-modal-url=', $html);
        $this->assertStringNotContainsString('data-telegram-link-url=', $html);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Modules\LoanManagement\Services\LoanCustomerLedgerService;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanCustomerLedgerTest extends LoanModuleFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('mysql_loan');
        $schema = Schema::connection('mysql_loan');
        $schema->create('loan_customers', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('customer_code'); $t->string('phone'); $t->softDeletes(); });
        $schema->create('loans', function (Blueprint $t) {
            $t->increments('id'); $t->integer('customer_id'); $t->string('loan_number'); $t->string('status')->default('active'); $t->string('currency')->default('USD');
            $t->decimal('total_amount', 18, 2); $t->decimal('down_payment', 18, 2)->default(0); $t->decimal('balance_amount', 18, 2); $t->decimal('exchange_rate', 18, 6)->default(1); $t->date('loan_date'); $t->timestamps(); $t->softDeletes();
        });
        $schema->create('loan_payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('loan_id')->nullable(); $t->integer('customer_id')->nullable(); $t->string('receipt_number'); $t->string('payment_method_snapshot')->nullable(); $t->string('status')->default('confirmed');
            $t->string('currency')->default('USD'); $t->decimal('exchange_rate', 18, 6)->default(1); $t->decimal('total_paid', 18, 2)->nullable(); $t->decimal('total_paid_base', 18, 2)->nullable(); $t->date('paid_date')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        $schema->create('loan_payment_details', function (Blueprint $t) { $t->increments('id'); $t->integer('payment_id'); $t->string('method'); $t->decimal('amount_base', 18, 2); });
        DB::connection('mysql_loan')->table('loan_customers')->insert(['id' => 1, 'name' => 'Sok San', 'customer_code' => 'CUST-001', 'phone' => '012345678']);
        $this->staff(['loan_management.view', 'loan_management.export.view']);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }
    protected function tearDown(): void { DB::purge('mysql_loan'); parent::tearDown(); }
    private function staff(array $permissions): void {
        auth()->setUser(new class(['id' => 1, 'permissions' => $permissions]) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return in_array($ability, $this->permissions, true); }
        });
    }
    private function loan(array $changes = []): int {
        return DB::connection('mysql_loan')->table('loans')->insertGetId(array_replace(['customer_id' => 1, 'loan_number' => 'LN-1', 'total_amount' => 120, 'down_payment' => 20, 'balance_amount' => 70, 'loan_date' => '2026-01-01'], $changes));
    }
    private function payment(int $loan, array $changes = []): int {
        return DB::connection('mysql_loan')->table('loan_payments')->insertGetId(array_replace(['loan_id' => $loan, 'customer_id' => 1, 'receipt_number' => 'RC-1', 'total_paid' => 20, 'total_paid_base' => 20, 'paid_date' => '2026-01-01'], $changes));
    }
    private function seedHistory(): int {
        $id = $this->loan();
        $this->payment($id);
        $this->payment($id, ['receipt_number' => 'RC-2', 'total_paid' => 50, 'total_paid_base' => 50, 'paid_date' => '2026-02-01', 'payment_method_snapshot' => 'Cash + ABA']);
        DB::connection('mysql_loan')->table('loan_payment_details')->insert([['payment_id' => 2, 'method' => 'Cash', 'amount_base' => 20], ['payment_id' => 2, 'method' => 'ABA', 'amount_base' => 30]]);
        return $id;
    }
    private function request(array $filters = []): Request {
        $request = Request::create('/loan-management/customers/1/ledger', 'GET', $filters);
        $request->setLaravelSession(session()->driver()); $this->app->instance('request', $request); return $request;
    }
    public function test_deposit_and_split_payment_are_each_counted_once_with_correct_opening_and_closing(): void {
        $this->seedHistory();
        $all = app(LoanCustomerLedgerService::class)->statement(1);
        $this->assertCount(3, $all['rows']); $this->assertFalse($all['has_adjustments']);
        $this->assertSame(14000, $all['summaries'][0]['debit']); $this->assertSame(7000, $all['summaries'][0]['credit']); $this->assertSame(7000, $all['summaries'][0]['closing']);
        $month = app(LoanCustomerLedgerService::class)->statement(1, ['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);
        $this->assertCount(1, $month['rows']); $this->assertSame(12000, $month['summaries'][0]['opening']); $this->assertSame(5000, $month['summaries'][0]['credit']); $this->assertSame(7000, $month['rows'][0]['balance']);
    }
    public function test_current_balance_adjustments_are_explicit_and_are_not_receipts(): void {
        $this->seedHistory(); DB::connection('mysql_loan')->table('loans')->where('id', 1)->update(['balance_amount' => 60, 'updated_at' => '2026-03-01']);
        $result = app(LoanCustomerLedgerService::class)->statement(1);
        $this->assertTrue($result['has_adjustments']); $this->assertSame('adjustment', $result['rows']->last()['type']); $this->assertSame(1000, $result['rows']->last()['credit']);
        $this->assertSame(6000, $result['summaries'][0]['closing']); $this->assertCount(2, $result['rows']->where('type', 'payment'));
        $before = app(LoanCustomerLedgerService::class)->statement(1, ['end_date' => '2026-02-28']); $this->assertSame(7000, $before['summaries'][0]['closing']);
    }
    public function test_void_pending_deleted_and_other_customers_records_are_excluded(): void {
        $id = $this->seedHistory();
        foreach (['void', 'failed', 'pending', 'cancelled'] as $status) $this->payment($id, ['status' => $status]);
        $this->payment($id, ['deleted_at' => '2026-01-01']);
        $other = $this->loan(['customer_id' => 2, 'loan_number' => 'OTHER']); $this->payment($other, ['customer_id' => 1, 'receipt_number' => 'MISMATCHED']);
        $draft = $this->loan(['status' => 'draft']); $this->payment($draft, ['status' => 'draft']);
        $deleted = $this->loan(['deleted_at' => '2026-01-01']); $this->payment($deleted);
        $result = app(LoanCustomerLedgerService::class)->statement(1);
        $this->assertCount(3, $result['rows']); $this->assertCount(2, $result['loans']); $this->assertSame(7000, $result['summaries'][0]['closing']);
    }
    public function test_foreign_payments_convert_to_loan_currency_and_currencies_stay_separate(): void {
        $this->seedHistory();
        $khr = $this->loan(['loan_number' => 'KHR-1', 'currency' => 'KHR', 'total_amount' => 400000, 'down_payment' => 0, 'balance_amount' => 200000, 'exchange_rate' => .00025]);
        $this->payment($khr, ['total_paid' => 50, 'total_paid_base' => 50, 'currency' => 'USD']);
        $result = app(LoanCustomerLedgerService::class)->statement(1);
        $summaries = $result['summaries']->keyBy('currency'); $this->assertCount(2, $summaries); $this->assertSame(20000000, $summaries['KHR']['closing']); $this->assertSame(7000, $summaries['USD']['closing']);
        $filtered = app(LoanCustomerLedgerService::class)->statement(1, ['currency' => 'KHR']); $this->assertCount(1, $filtered['summaries']); $this->assertSame(20000000, $filtered['rows']->where('type', 'payment')->first()['credit']);
    }
    public function test_filter_only_loan_is_scoped_to_the_customer(): void {
        $this->seedHistory(); $other = $this->loan(['customer_id' => 2]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(LoanCustomerLedgerService::class)->statement(1, ['loan_id' => $other]);
    }
    public function test_modal_print_and_csv_render_and_exports_are_protected(): void {
        $this->seedHistory();
        DB::connection('mysql_loan')->table('loan_payments')->where('id', 2)->update(['receipt_number' => '=DANGEROUS()', 'payment_method_snapshot' => '<script>bad</script>']);
        $controller = app(LoanCustomerController::class);
        $view = $controller->ledger($this->request(['_customer_modal' => 1]), 1);
        $this->assertSame('loanmanagement::customers.modal_ledger', $view->getName()); $html = $view->render();
        foreach (['data-customer-modal-view="ledger"', 'js-customer-ledger-filter', 'Opening balance', 'Closing balance', '&lt;script&gt;bad&lt;/script&gt;'] as $expected) $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $print = $controller->ledger($this->request(['print' => 1]), 1); $this->assertSame('loanmanagement::customers.ledger_print', $print->getName()); $this->assertStringContainsString('window.print()', $print->render());
        $csv = $controller->ledger($this->request(['export' => 'csv']), 1); ob_start(); $csv->sendContent(); $contents = ob_get_clean();
        $this->assertStringContainsString("'=DANGEROUS()", $contents); $this->assertStringContainsString('USD', $contents);
        $this->staff(['loan_management.view']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); $controller->ledger($this->request(['export' => 'csv']), 1);
    }
    public function test_end_only_filter_works_and_reverse_date_range_is_rejected(): void {
        $this->seedHistory(); $controller = app(LoanCustomerController::class);
        $view = $controller->ledger($this->request(['end_date' => '2026-01-31']), 1); $this->assertCount(2, $view->getData()['ledger']['rows']);
        $this->expectException(ValidationException::class); $controller->ledger($this->request(['start_date' => '2026-02-01', 'end_date' => '2026-01-01']), 1);
    }
    public function test_empty_and_undated_records_have_explicit_behavior(): void {
        $empty = app(LoanCustomerLedgerService::class)->statement(1); $this->assertCount(0, $empty['rows']);
        $this->payment(0, ['loan_id' => null, 'paid_date' => null]);
        $all = app(LoanCustomerLedgerService::class)->statement(1); $this->assertSame(-2000, $all['summaries'][0]['closing']);
        $range = app(LoanCustomerLedgerService::class)->statement(1, ['start_date' => '2026-01-01']); $this->assertCount(0, $range['rows']); $this->assertSame(1, $range['undated']);
    }
    public function test_pos_ledger_shortcut_requires_a_link_same_business_and_pos_permission(): void {
        Schema::connection('mysql_loan')->table('loan_customers', fn (Blueprint $t) => $t->integer('main_contact_id')->nullable());
        Schema::create('contacts', function (Blueprint $t) { $t->increments('id'); $t->integer('business_id'); });
        DB::table('contacts')->insert(['id' => 1708, 'business_id' => 7]);
        DB::connection('mysql_loan')->table('loan_customers')->where('id', 1)->update(['main_contact_id' => 1708]);
        session()->put('user.business_id', 7);
        $controller = app(LoanCustomerController::class);
        $this->staff(['loan_management.view', 'customer.view']);
        $data = $controller->ledger($this->request(), 1)->getData();
        $this->assertStringEndsWith('/contacts/1708?view=ledger', $data['posLedgerUrl']);
        session()->put('user.business_id', 8);
        $this->assertNull($controller->ledger($this->request(), 1)->getData()['posLedgerUrl']);
        session()->put('user.business_id', 7); $this->staff(['loan_management.view']);
        $this->assertNull($controller->ledger($this->request(), 1)->getData()['posLedgerUrl']);
    }
    public function test_ledger_route_inherits_module_auth_customer_and_financial_permissions(): void {
        $route = app('router')->getRoutes()->getByName('loan-management.customers.ledger');
        $this->assertSame('loan-management/customers/{customer}/ledger', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('loan.permission:loan_management.customers.view|loan_management.view', $route->gatherMiddleware());
        $this->assertContains('loan.permission:loan_management.loans.view|loan_management.payments.view|loan_management.payment|loan_management.view', $route->gatherMiddleware());
    }

    public function test_posted_deposits_on_pending_contracts_remain_customer_credits(): void {
        $id = $this->loan(['status' => 'pending']); $this->payment($id);
        $result = app(LoanCustomerLedgerService::class)->statement(1);
        $this->assertCount(1, $result['rows']); $this->assertSame('payment', $result['rows'][0]['type']);
        $this->assertSame(0, $result['summaries'][0]['debit']); $this->assertSame(-2000, $result['summaries'][0]['closing']);
        $this->assertFalse($result['has_adjustments']);
    }

}

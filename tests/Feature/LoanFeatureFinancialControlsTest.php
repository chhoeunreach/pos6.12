<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\LoanAbaPaywayController;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Http\Controllers\LoanPaymentController;
use Modules\LoanManagement\Http\Controllers\StaffMobileActionController;
use Modules\LoanManagement\Services\LoanPenaltyAccrualService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LoanFeatureFinancialControlsTest extends TestCase
{
    private FeatureFinancialControlsLoanController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('mysql_loan');
        $migration = require module_path('LoanManagement', 'Database/migrations').'/2026_05_13_000001_create_core_loan_management_tables.php';
        $migration->up();
        $migration = require module_path('LoanManagement', 'Database/migrations').'/2026_10_07_000001_add_daily_penalty_rate_to_loans.php';
        $migration->up();
        Schema::connection('mysql_loan')->table('loans', function (Blueprint $table) {
            $table->string('interest_type')->default('flat');
            $table->decimal('interest_rate', 18, 6)->default(0);
            $table->string('interest_rate_type')->nullable();
            $table->integer('duration_months')->default(1);
        });
        Schema::connection('mysql_loan')->table('loan_payment_schedules', function (Blueprint $table) {
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('schedule_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
        });
        Schema::connection('mysql_loan')->table('loan_payments', function (Blueprint $table) {
            $table->decimal('total_paid_base', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->string('payment_type')->nullable();
        });
        $this->authenticate(['*']);
        $this->controller = new FeatureFinancialControlsLoanController;
        $this->controller->resetSchemaCaches();
        DB::connection('mysql_loan')->table('loans')->insert([
            'id' => 1, 'loan_number' => 'FIN-001', 'customer_id' => 1, 'status' => 'active',
            'total_amount' => 110, 'principal_amount' => 100, 'balance_amount' => 110,
        ]);
        $this->schedule(1, 100, 10);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    private function authenticate(array $permissions): void
    {
        auth()->setUser(new class(['id' => 1, 'name' => 'Officer', 'username' => 'officer'], $permissions) extends GenericUser {
            public function __construct(array $attributes, private array $permissions) { parent::__construct($attributes); }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool
            {
                return in_array('*', $this->permissions, true) || in_array($ability, $this->permissions, true);
            }
        });
    }

    private function schedule(int $id, float $principal, float $interest = 0, float $penalty = 0, string $status = 'pending'): void
    {
        $total = $principal + $interest + $penalty;
        DB::connection('mysql_loan')->table('loan_payment_schedules')->insert([
            'id' => $id, 'loan_id' => 1, 'installment_no' => $id, 'due_date' => '2026-09-01',
            'principal_due' => $principal, 'interest_due' => $interest, 'penalty_due' => $penalty,
            'amount_due' => $total, 'schedule_amount' => $total,
            'amount_balance' => $total, 'balance_amount' => $total, 'status' => $status,
        ]);
    }

    private function request(array $data): Request
    {
        return Request::create('/', 'POST', $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
    }

    private function settle(array $overrides = [])
    {
        return $this->controller->processSettlement($this->request(array_merge([
            'settlement_amount' => 100, 'interest_discount' => 10,
            'payment_method' => 'cash', 'paid_date' => '2026-10-07',
        ], $overrides)), 1);
    }

    private function reschedule(array $overrides = [])
    {
        return $this->controller->processReschedule($this->request(array_merge([
            'reschedule_amount' => 100, 'new_duration_months' => 2,
            'new_interest_rate' => 12, 'interest_type' => 'reducing_balance',
            'first_due_date' => '2026-10-31', 'reason' => 'Approved restructuring',
        ], $overrides)), 1);
    }

    private function rejects(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Expected financial action to be rejected.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    

    public function testSimulationCannotPostEvenForAnAdministrator(): void
    {
        $this->rejects(fn () => (new LoanAbaPaywayController)->simulateSuccess('any-reference'), 403);
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_payments')->count());
        $this->assertEquals(110, DB::connection('mysql_loan')->table('loans')->value('balance_amount'));
    }

    public function testUnconfiguredGatewayCannotCreateFakeTransactionsOrCheckout(): void
    {
        $this->rejects(fn () => (new LoanAbaPaywayController)->create($this->request(['amount' => 100])), 503);
        $this->rejects(fn () => (new LoanAbaPaywayController)->checkout('any-reference'), 503);
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_aba_payway_transactions')->count());
    }

    public function testSettlementRejectsUnderpaymentAndLeavesBalancesUnchanged(): void
    {
        $this->rejects(fn () => $this->settle(['settlement_amount' => 1]), 422);
        $this->assertEquals(110, DB::connection('mysql_loan')->table('loans')->value('balance_amount'));
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_payments')->count());
    }

    public function testSettlementRejectsDiscountOfPrincipal(): void
    {
        $this->rejects(fn () => $this->settle(['interest_discount' => 100, 'settlement_amount' => 10]), 422);
    }

    public function testSettlementIncludesFeeAndKeepsActualCashAndRebateSeparate(): void
    {
        $this->settle(['settlement_amount' => 102, 'prepayment_fee' => 2]);
        $loan = DB::connection('mysql_loan')->table('loans')->first();
        $payment = DB::connection('mysql_loan')->table('loan_payments')->first();
        $schedule = DB::connection('mysql_loan')->table('loan_payment_schedules')->first();
        $this->assertSame('completed', $loan->status);
        $this->assertEquals(102, $loan->paid_amount);
        $this->assertEquals(102, $payment->total_paid_base);
        $this->assertEquals(100, $schedule->amount_paid);
        $this->assertEquals(100, $schedule->paid_amount);
        $this->assertEquals(10, $schedule->discount_amount);
        $this->assertEquals(0, $schedule->balance_amount);
        $this->assertEquals(0, $schedule->amount_balance);
        $this->rejects(fn () => $this->settle(['settlement_amount' => 102, 'prepayment_fee' => 2]), 422);
        $this->assertSame(1, DB::connection('mysql_loan')->table('loan_payments')->count());
    }

    public function testSettlementRejectsMissingFeePayment(): void
    {
        $this->rejects(fn () => $this->settle(['prepayment_fee' => 2]), 422);
    }

    public function testPartiallyPaidLoanSettlementUsesRemainingBalance(): void
    {
        DB::connection('mysql_loan')->table('loan_payment_schedules')->update([
            'amount_paid' => 40, 'paid_amount' => 40, 'amount_balance' => 70, 'balance_amount' => 70, 'status' => 'partial',
        ]);
        DB::connection('mysql_loan')->table('loans')->update(['paid_amount' => 40, 'balance_amount' => 70]);
        $this->settle(['settlement_amount' => 60]);
        $this->assertEquals(100, DB::connection('mysql_loan')->table('loans')->value('paid_amount'));
        $this->assertEquals(100, DB::connection('mysql_loan')->table('loan_payment_schedules')->value('amount_paid'));
    }

    

    

    

    

    public function testRescheduleCalculatesDecliningInterestAndPreservesPenalties(): void
    {
        DB::connection('mysql_loan')->table('loan_payment_schedules')->update(['penalty_due' => 5, 'amount_balance' => 115, 'balance_amount' => 115]);
        $this->reschedule();
        $rows = DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNull('deleted_at')->orderBy('installment_no')->get();
        $this->assertEquals([1, 0.5], $rows->pluck('interest_due')->all());
        $this->assertEquals([5, 0], $rows->pluck('penalty_due')->all());
        $this->assertEquals(['2026-10-31', '2026-11-30'], $rows->pluck('due_date')->all());
        $this->assertEquals(106.5, DB::connection('mysql_loan')->table('loans')->value('balance_amount'));
        $this->assertEquals(1, DB::connection('mysql_loan')->table('loans')->value('interest_rate'));
        $this->assertSame(1, DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNotNull('deleted_at')->count());
    }

    public function testRescheduleFlatInterestAndExplicitPenaltyWaiver(): void
    {
        DB::connection('mysql_loan')->table('loan_payment_schedules')->update(['penalty_due' => 5]);
        $this->reschedule(['interest_type' => 'flat', 'waive_penalties' => true]);
        $rows = DB::connection('mysql_loan')->table('loan_payment_schedules')->whereNull('deleted_at')->get();
        $this->assertEquals([1, 1], $rows->pluck('interest_due')->all());
        $this->assertEquals(0, $rows->sum('penalty_due'));
        $this->assertEquals(102, DB::connection('mysql_loan')->table('loans')->value('balance_amount'));
    }

    public function testPenaltiesUseFixedRateAndAreAppliedOncePerDay(): void
    {
        DB::connection('mysql_loan')->table('loans')->update(['daily_penalty_rate' => 2, 'penalty_amount' => 99]);
        $service = new LoanPenaltyAccrualService;
        Carbon::setTestNow('2026-10-07');
        $service->accrueDaily();
        $service->accrueDaily();
        Carbon::setTestNow('2026-10-08');
        $service->accrueDaily();
        $this->assertEquals(4, DB::connection('mysql_loan')->table('loans')->value('penalty_amount'));
        $this->assertEquals(2, DB::connection('mysql_loan')->table('loans')->value('daily_penalty_rate'));
        $this->assertEquals(114, DB::connection('mysql_loan')->table('loan_payment_schedules')->value('balance_amount'));
        $this->assertSame(2, DB::connection('mysql_loan')->table('loan_penalties')->count());
    }

    public function testZeroPenaltyRateAndDryRunDoNotPostCharges(): void
    {
        DB::connection('mysql_loan')->table('loans')->update(['daily_penalty_rate' => 0]);
        $service = new LoanPenaltyAccrualService;
        $service->accrueDaily(true);
        $this->assertSame('pending', DB::connection('mysql_loan')->table('loan_payment_schedules')->value('status'));
        $service->accrueDaily();
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_penalties')->count());
        $this->assertEquals(110, DB::connection('mysql_loan')->table('loans')->value('balance_amount'));
    }

    public function testPenaltyHistoryIsRequiredForIdempotentAccrual(): void
    {
        Schema::connection('mysql_loan')->drop('loan_penalties');
        $this->rejects(fn () => (new LoanPenaltyAccrualService)->accrueDaily(), 409);
        $this->assertEquals(110, DB::connection('mysql_loan')->table('loan_payment_schedules')->value('amount_balance'));
    }

    public function testPendingLoansDoNotAccruePenalties(): void
    {
        DB::connection('mysql_loan')->table('loans')->update(['status' => 'pending']);
        (new LoanPenaltyAccrualService)->accrueDaily();
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_penalties')->count());
    }

    

    

    

    private function payment(): void
    {
        DB::connection('mysql_loan')->table('loan_payments')->insert([
            'id' => 1, 'loan_id' => 1, 'customer_id' => 1, 'amount' => 10, 'total_paid' => 10,
            'total_paid_base' => 10, 'status' => 'confirmed',
        ]);
    }

    

    

    

    private function mobilePayment(array $overrides = [])
    {
        return (new StaffMobileActionController)->receivePayment($this->request(array_merge([
            'loan_id' => 1, 'customer_id' => 1, 'currency' => 'USD', 'amount' => 10,
            'details' => [['method' => 'cash', 'amount' => 10]],
        ], $overrides)));
    }

    

    

    

    

    

    

    

    

    public function testRestructureKeepsHistoricalPartialCashInTotalAmount(): void
    {
        DB::connection('mysql_loan')->table('loans')->update(['paid_amount' => 40]);
        DB::connection('mysql_loan')->table('loan_payment_schedules')->update([
            'amount_paid' => 40, 'paid_amount' => 40, 'balance_amount' => 70, 'amount_balance' => 70, 'status' => 'partial',
        ]);
        $this->reschedule();
        $loan = DB::connection('mysql_loan')->table('loans')->first();
        $this->assertEquals(40, $loan->paid_amount);
        $this->assertEquals(141.5, $loan->total_amount);
        $this->assertEquals(101.5, $loan->balance_amount);
    }

    

    

    

    public function testRescheduleFormConvertsStoredMonthlyInterestToAnnualRate(): void
    {
        DB::connection('mysql_loan')->table('loans')->update(['interest_rate' => 1.5, 'interest_rate_type' => 'monthly']);
        $view = $this->controller->rescheduleModal(1);
        $this->assertEquals(18, $view->getData()['annualInterestRate']);
    }
}

class FeatureFinancialControlsLoanController extends LoanInstallmentListController
{
    public function resetSchemaCaches(): void
    {
        self::$loanTableExistsCache = [];
        self::$loanColumnCache = [];
    }

    protected function ultimatePosPaymentTypes(object $loanRow): array
    {
        return ['cash' => 'Cash'];
    }

    public function allocate(float $amount): void
    {
        DB::connection('mysql_loan')->transaction(fn () => $this->applyLoanPaymentToSchedules(1, $amount, '2026-10-07 10:00:00'));
    }

    public function payoff(float $amount, float $discount): void
    {
        DB::connection('mysql_loan')->transaction(fn () => $this->applyLoanPayOffToSchedules(1, $amount, $discount, '2026-10-07 10:00:00'));
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\LoanQuotationController;
use Modules\LoanManagement\Entities\LoanQuotation;
use Modules\LoanManagement\Services\LoanQuotationService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoanQuotationTest extends TestCase
{
    private static int $userId = 50000;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('mysql_loan');
        config(['cache.default' => 'array', 'session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $migration = require module_path('LoanManagement', 'Database/migrations').'/2026_10_02_000001_create_loan_quotations_tables.php';
        $migration->up();
        $this->authenticate(['*']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    public function testQuotationCreationLoadsProductsWithoutStatusColumn(): void
    {
        Schema::connection('mysql_loan')->create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->integer('qty_available')->default(0);
        });
        Schema::connection('mysql_loan')->create('loan_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->softDeletes();
        });
        Schema::connection('mysql_loan')->create('loan_business_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::connection('mysql_loan')->table('loan_products')->insert([
            ['name' => 'Z Phone', 'selling_price' => 500, 'qty_available' => 0],
            ['name' => 'A Phone', 'selling_price' => 200, 'qty_available' => 2],
        ]);
        $service = new class extends LoanQuotationService {
            public function generateQuotationNumber(?int $locationId = null): string
            {
                return 'QTN-TEST-0001';
            }
        };
        $view = (new LoanQuotationController($service))->create();
        $this->assertSame('loanmanagement::quotations.create', $view->name());
        $this->assertSame(['A Phone', 'Z Phone'], $view->getData()['products']->pluck('name')->all());
        $this->assertSame(200.0, $view->getData()['products']->first()->selling_price);
    }

    private function authenticate(array $permissions): void
    {
        auth()->setUser(new class(['id' => ++self::$userId], $permissions) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __construct(array $attributes, private array $permissions) { parent::__construct($attributes); }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool
            {
                return in_array('*', $this->permissions, true) || in_array($ability, $this->permissions, true);
            }
        });
    }

    private function proposalData(): array
    {
        return [
            'customer_name_snapshot' => 'Test Customer', 'customer_phone_snapshot' => '012345678',
            'quotation_date' => '2026-10-03', 'first_due_date' => '2026-11-03',
            'total_amount' => 1000, 'down_payment' => 100, 'loan_amount' => 900,
            'interest_rate' => 1, 'interest_type' => 'flat_rate', 'duration_months' => 3,
            'payment_frequency' => 'monthly', 'note' => 'Original note',
            'items' => [['product_name' => 'Test Phone', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]],
        ];
    }

    public function testUpdateReplacesItemsAndScheduleButPreservesIdentity(): void
    {
        $service = new LoanQuotationService;
        $quotation = $service->create($this->proposalData(), auth()->id());
        $originalNumber = $quotation->quotation_no;
        $data = $this->proposalData();
        $data['customer_name_snapshot'] = 'Updated Customer';
        $data['duration_months'] = 2;
        $data['loan_amount'] = 700;
        $data['items'][0]['unit_price'] = 800;
        $data['items'][0]['line_total'] = 99999;
        $updated = $service->update($quotation, $data, auth()->id());
        $this->assertSame($quotation->id, $updated->id);
        $this->assertSame($originalNumber, $updated->quotation_no);
        $this->assertSame('draft', $updated->status);
        $this->assertSame('Updated Customer', $updated->customer_name_snapshot);
        $this->assertEquals(800, $updated->total_amount);
        $this->assertEquals(14, $updated->total_interest);
        $this->assertEquals(714, $updated->total_payable);
        $this->assertCount(1, $updated->items);
        $this->assertEquals(800, $updated->items->first()->line_total);
        $this->assertCount(2, $updated->schedules);
        $this->assertEquals(700, $updated->schedules->sum('principal_amount'));
        $this->assertSame(auth()->id(), $updated->updated_by);
    }

    public function testViewOnlyAccountCannotMutateOrCreateQuotations(): void
    {
        $this->authenticate(['loan_management.view']);
        $controller = new LoanQuotationController(new LoanQuotationService);
        foreach ([
            ['create', []], ['store', [Request::create('/', 'POST', $this->proposalData())]],
            ['edit', [1]], ['update', [Request::create('/', 'PUT', $this->proposalData()), 1]],
            ['destroy', [1]], ['duplicate', [1]], ['convertToLoan', [1]],
            ['changeStatus', [Request::create('/', 'POST', ['status' => 'sent']), 1]],
            ['previewSchedule', [Request::create('/')]],
        ] as [$method, $arguments]) {
            try {
                $controller->$method(...$arguments);
                $this->fail($method.' should be denied.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode(), $method);
            }
        }
        $this->assertSame(0, LoanQuotation::count());
    }

    public function testDeletePermissionSoftDeletesOnlyTheQuotation(): void
    {
        $service = new LoanQuotationService;
        $quotation = $service->create($this->proposalData(), auth()->id());
        $this->authenticate(['loan_management.delete']);
        (new LoanQuotationController($service))->destroy($quotation->id);
        $this->assertNull(LoanQuotation::find($quotation->id));
        $this->assertNotNull(LoanQuotation::withTrashed()->find($quotation->id)->deleted_at);
        $this->assertSame(1, DB::connection('mysql_loan')->table('loan_quotation_items')->count());
    }

    public function testConvertedProposalCannotBeUpdatedDeletedOrReopened(): void
    {
        $service = new LoanQuotationService;
        $quotation = $service->create($this->proposalData(), auth()->id());
        $quotation->update(['status' => 'converted', 'converted_loan_id' => 123]);
        $controller = new LoanQuotationController($service);
        foreach ([
            ['edit', [$quotation->id]],
            ['update', [Request::create('/', 'PUT', $this->proposalData()), $quotation->id]],
            ['destroy', [$quotation->id]],
            ['changeStatus', [Request::create('/', 'POST', ['status' => 'draft']), $quotation->id]],
        ] as [$method, $arguments]) {
            try {
                $controller->$method(...$arguments);
                $this->fail($method.' must preserve converted proposals.');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function testUpdateControllerValidatesNestedItems(): void
    {
        $service = new LoanQuotationService;
        $quotation = $service->create($this->proposalData(), auth()->id());
        $data = $this->proposalData();
        $data['items'][0]['quantity'] = -1;
        try {
            (new LoanQuotationController($service))->update(Request::create('/', 'PUT', $data), $quotation->id);
            $this->fail('Invalid quantities must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('items.0.quantity', $exception->errors());
        }
        $this->assertEquals(1000, $quotation->fresh()->total_amount);
    }

    public function testEditFormPreservesFieldsAndUsesUpdateRoute(): void
    {
        $quotation = (new LoanQuotationService)->create($this->proposalData(), auth()->id());
        $quotation->load('items');
        $html = view('loanmanagement::quotations.create', [
            'quotation' => $quotation, 'quotationNo' => $quotation->quotation_no,
            'locations' => collect(), 'customers' => collect(),
        ])->render();
        $this->assertStringContainsString(route('loan-management.quotations.update', $quotation->id), $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('value="Test Customer"', $html);
        $this->assertStringContainsString('value="Test Phone"', $html);
        $this->assertStringContainsString('Original note', $html);
        $this->assertStringContainsString('function recalculateSchedule()', $html);
    }

    public function testActionButtonsFollowPermissions(): void
    {
        $quotation = (new LoanQuotationService)->create($this->proposalData(), auth()->id());
        $data = [
            'quotations' => new \Illuminate\Pagination\LengthAwarePaginator(collect([$quotation]), 1, 20),
            'summary' => ['total' => 1, 'draft' => 1, 'sent' => 0, 'accepted' => 0, 'converted' => 0, 'total_amount' => 1000],
        ];
        $this->authenticate(['loan_management.view']);
        $html = view('loanmanagement::quotations.index', $data)->render();
        foreach (['quotations.edit', 'quotations.destroy', 'quotations.create', 'quotations.convert'] as $action) {
            $parameters = $action === 'quotations.create' ? [] : [$quotation->id];
            $attribute = in_array($action, ['quotations.destroy', 'quotations.convert'], true) ? 'action' : 'href';
            $this->assertSame(0, substr_count($html, $attribute.'="'.route('loan-management.'.$action, $parameters).'"'), $action);
        }
        $this->authenticate(['loan_management.edit', 'loan_management.delete']);
        $html = view('loanmanagement::quotations.index', $data)->render();
        $this->assertStringContainsString(route('loan-management.quotations.edit', $quotation->id), $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringNotContainsString(route('loan-management.quotations.convert', $quotation->id), $html);
    }

    public function testEditorCanUpdateThroughController(): void
    {
        $service = new LoanQuotationService;
        $quotation = $service->create($this->proposalData(), auth()->id());
        $this->authenticate(['loan_management.edit']);
        $data = $this->proposalData();
        $data['note'] = 'Changed by editor';
        $response = (new LoanQuotationController($service))->update(Request::create('/', 'PUT', $data), $quotation->id);
        $this->assertSame(route('loan-management.quotations.show', $quotation->id), $response->getTargetUrl());
        $this->assertSame('Changed by editor', $quotation->fresh()->note);
    }

    public function testFailedScheduleReplacementRollsBackEntireUpdate(): void
    {
        $quotation = (new LoanQuotationService)->create($this->proposalData(), auth()->id());
        $service = new class extends LoanQuotationService {
            public function calculateSchedule(float $principal, float $interestRate, string $interestType, int $duration, string $frequency, string $firstDueDate): array
            {
                $rows = parent::calculateSchedule($principal, $interestRate, $interestType, $duration, $frequency, $firstDueDate);
                $rows[0]['due_date'] = null;
                return $rows;
            }
        };
        $data = $this->proposalData();
        $data['note'] = 'Must roll back';
        $data['items'][0]['product_name'] = 'Replacement';
        try {
            $service->update($quotation, $data, auth()->id());
            $this->fail('Invalid schedule must fail.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('Original note', $quotation->fresh()->note);
            $this->assertSame('Test Phone', $quotation->items()->first()->product_name_snapshot);
            $this->assertSame(3, $quotation->schedules()->count());
        }
    }

    public function testPrintTemplateUsesA4AndKeepsQuotationDetails(): void
    {
        $quotation = (new LoanQuotationService)->create($this->proposalData(), auth()->id());
        $quotation->load(['items', 'schedules']);
        $quotation->setRelation('location', null);
        $html = view('loanmanagement::quotations.print', compact('quotation'))->render();
        $this->assertStringContainsString('size: A4 portrait;', $html);
        $this->assertStringContainsString('margin: 12mm;', $html);
        $this->assertStringContainsString('display: table-header-group;', $html);
        $this->assertStringContainsString('Test Customer', $html);
        $this->assertStringContainsString('Test Phone', $html);
        $this->assertStringContainsString('Customer Acceptance', $html);
        $this->assertStringNotContainsString('+855 (0) 23 999 888', $html);
        $this->assertStringContainsString("window.addEventListener('afterprint'", $html);
        $this->assertStringContainsString('window.location.replace(', $html);
        $this->assertStringContainsString('href="'.route('loan-management.quotations.show', $quotation->id).'"', $html);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\LoanCustomerService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;

class PublicInstallmentRequestTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        DB::purge('mysql_loan');
        $this->storage = sys_get_temp_dir().'/installment-request-'.bin2hex(random_bytes(5));
        $app->useStoragePath($this->storage);
        BusinessSettingsService::save(['customer_login_enabled' => true]);
        auth()->guard('web')->forgetUser();
        auth()->guard('customer_loan')->forgetUser();
        Schema::connection('mysql_loan')->create('loan_customers', function (Blueprint $table) {
            $table->id(); $table->string('customer_code')->unique(); $table->string('name');
            $table->string('phone'); $table->text('address'); $table->text('note')->nullable();
            $table->string('username')->nullable()->unique(); $table->string('login_phone')->nullable()->unique();
            $table->string('password')->nullable(); $table->string('customer_type');
            $table->string('status'); $table->boolean('can_login'); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->storage);
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    private function controller(): PublicAppController
    {
        return new class extends PublicAppController {
            protected function catalogProducts(): array
            {
                return [['id' => 7, 'variation_id' => 0, 'name' => 'ASUS Vivobook 15', 'sku' => 'ASUS-15', 'price' => 520]];
            }
        };
    }

    private function request(array $changes = [], bool $json = true): Request
    {
        $request = Request::create('/register', 'POST', array_merge([
            'name' => 'Sample Applicant', 'phone' => '012 345 678', 'address' => 'Sample Address',
            'installment_items' => json_encode([['id' => 7, 'qty' => 2, 'price' => 1, 'name' => 'Forged Name']]),
        ], $changes), [], [], $json ? ['HTTP_ACCEPT' => 'application/json'] : []);
        $request->setLaravelSession(session()->driver());
        app()->instance('request', $request);
        return $request;
    }

    public function testSubmissionCreatesPendingContactWithoutLoginOrLoanAndUsesCatalogPrices(): void
    {
        $response = $this->controller()->storeRegistration($this->request(['status' => 'active', 'can_login' => 1, 'password' => 'unexpected-password']), new LoanCustomerService);
        $row = DB::connection('mysql_loan')->table('loan_customers')->first();
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('pending', $response->getData(true)['status']);
        $this->assertSame('pending', $row->status);
        $this->assertSame(0, $row->can_login);
        $this->assertNull($row->password);
        $this->assertNull($row->username);
        $this->assertNull($row->login_phone);
        $this->assertSame('public_installment_request', $row->customer_type);
        $this->assertStringContainsString('ASUS Vivobook 15 (SKU: ASUS-15) x2 = 1,040.00', $row->note);
        $this->assertStringNotContainsString('Forged Name', $row->note);
        $this->assertFalse(auth()->guard('customer_loan')->check());
        $this->assertFalse(Schema::connection('mysql_loan')->hasTable('loans'));
    }

    public function testExistingCustomerSessionAndCredentialsArePreserved(): void
    {
        $user = new GenericUser(['id' => 91, 'name' => 'Existing Customer']);
        auth()->guard('customer_loan')->setUser($user);
        $this->controller()->storeRegistration($this->request(['phone' => '012345678']), new LoanCustomerService);
        $this->assertSame($user, auth()->guard('customer_loan')->user());
        $this->assertSame('pending', DB::connection('mysql_loan')->table('loan_customers')->value('status'));
    }

    public function testInvalidCartIsRejectedBeforeSaving(): void
    {
        foreach ([[['id' => 999, 'qty' => 1]], [['id' => 7, 'qty' => -1]], ['bad item'], null] as $items) {
            try {
                $this->controller()->storeRegistration($this->request(['installment_items' => json_encode($items)]), new LoanCustomerService);
                $this->fail('Expected invalid cart to be rejected');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('installment_items', $exception->errors());
            }
        }
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_customers')->count());
    }

    public function testEmptyCartCanSubmitGeneralInquiryAndNormalPostReturnsHome(): void
    {
        $response = $this->controller()->storeRegistration($this->request(['installment_items' => '[]'], false), new LoanCustomerService);
        $this->assertSame(route('loan-management.public.home'), $response->getTargetUrl());
        $this->assertStringContainsString('Staff will help', DB::connection('mysql_loan')->table('loan_customers')->value('note'));
    }

    public function testDisabledPortalRejectsSubmission(): void
    {
        BusinessSettingsService::save(['customer_login_enabled' => false]);
        $response = $this->controller()->storeRegistration($this->request(), new LoanCustomerService);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_customers')->count());
    }

    public function testStaffFollowUpFormPreservesPendingStatusAndRequestNotes(): void
    {
        $this->controller()->storeRegistration($this->request(), new LoanCustomerService);
        $row = DB::connection('mysql_loan')->table('loan_customers')->first();
        $html = view('loanmanagement::customers.partials.basic_info', ['customerRow' => $row])->render();
        $this->assertStringContainsString('value="pending" selected', $html);
        $this->assertStringContainsString('ASUS Vivobook 15', $html);
        $this->assertStringContainsString('name="note"', $html);
        $validator = \Illuminate\Support\Facades\Validator::make([
            'name' => $row->name, 'phone' => $row->phone, 'address' => $row->address,
            'customer_type' => $row->customer_type, 'status' => 'pending',
            'note' => $row->note."\nStaff called; awaiting documents.",
        ], (new \Modules\LoanManagement\Http\Requests\UpdateLoanCustomerRequest)->rules());
        $this->assertTrue($validator->passes());
        (new LoanCustomerService)->update($row->id, $validator->validated());
        $saved = DB::connection('mysql_loan')->table('loan_customers')->first();
        $this->assertSame('pending', $saved->status);
        $this->assertStringContainsString('ASUS Vivobook 15', $saved->note);
        $this->assertStringContainsString('awaiting documents', $saved->note);
    }

    public function testAssessmentPreferencesReachStaffRequestNotes(): void
    {
        $this->controller()->storeRegistration($this->request(['preferred_months' => 12, 'preferred_down_payment' => 100]), new LoanCustomerService);
        $note = DB::connection('mysql_loan')->table('loan_customers')->value('note');
        $this->assertStringContainsString('12 months', $note);
        $this->assertStringContainsString('Proposed down payment: 100.00', $note);
    }

    public function testRegistrationUrlOpensHomepageModalAndSubmissionIsRateLimited(): void
    {
        app()->instance('request', Request::create('/register?product_id=7'));
        $response = $this->controller()->register();
        $this->assertStringContainsString('apply=1', $response->getTargetUrl());
        $this->assertStringContainsString('product_id=7', $response->getTargetUrl());
        $route = app('router')->getRoutes()->match(Request::create('/register', 'POST'));
        $this->assertContains('throttle:6,1', $route->gatherMiddleware());
    }
}

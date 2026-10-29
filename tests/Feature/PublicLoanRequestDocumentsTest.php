<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Entities\Loan;
use Modules\LoanManagement\Entities\LoanCustomer;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Modules\LoanManagement\Http\Requests\StoreStandaloneLoanRequest;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\CreateStandaloneLoanService;
use Modules\LoanManagement\Services\LoanCustomerService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;

class PublicLoanRequestDocumentsTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        DB::purge('mysql_loan');
        $this->storage = sys_get_temp_dir().'/loan-request-docs-'.bin2hex(random_bytes(5));
        $app->useStoragePath($this->storage);

        BusinessSettingsService::save(['customer_login_enabled' => true]);
        auth()->guard('customer_loan')->forgetUser();

        $this->createCustomersTable();
        $this->createLoansTable();
        $this->createLoanFilesTable();

        DB::connection('mysql_loan')->table('loan_customers')->insert([
            'id' => 55,
            'customer_code' => 'C-0055',
            'name' => 'Sokha Applicant',
            'phone' => '012 999 888',
            'address' => 'Phnom Penh',
            'username' => 'sokha',
        ]);

        auth()->guard('customer_loan')->setUser(new GenericUser([
            'id' => 55,
            'name' => 'Sokha Applicant',
            'phone' => '012 999 888',
            'address' => 'Phnom Penh',
            'province' => 'Phnom Penh',
            'district' => 'Phnom Penh',
            'commune' => 'Phnom Penh',
            'village' => 'Village 1',
        ]));
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->storage);
        DB::disconnect('mysql_loan');
        parent::tearDown();
    }

    private function createCustomersTable(): void
    {
        Schema::connection('mysql_loan')->create('loan_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('username')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('commune')->nullable();
            $table->string('village')->nullable();
            $table->string('khmer_name')->nullable();
            $table->string('id_card_number')->nullable();
            $table->string('workplace')->nullable();
            $table->decimal('monthly_income', 14, 2)->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('family_contact_name')->nullable();
            $table->string('family_contact_phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function createLoansTable(): void
    {
        Schema::connection('mysql_loan')->create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status')->nullable();
            $table->decimal('principal_amount', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    private function createLoanFilesTable(): void
    {
        Schema::connection('mysql_loan')->create('loan_files', function (Blueprint $table) {
            $table->id();
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id');
            $table->string('category')->nullable();
            $table->string('disk')->nullable();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    private function controller(): PublicAppController
    {
        return new class extends PublicAppController {
            protected function catalogProducts(): array
            {
                return [];
            }
        };
    }

    private function request(array $files = [], array $changes = []): Request
    {
        $request = Request::create(
            '/customer/loan-request',
            'POST',
            array_merge([
                'principal_amount' => 500,
                'duration_months' => 6,
            ], $changes),
            [],
            $files
        );
        $request->setLaravelSession(session()->driver());
        app()->instance('request', $request);
        return $request;
    }

    public function testEveryDocumentSlotAcceptsMultipleFilesWithCropControls(): void
    {
        $html = view('loanmanagement::public.customer_loan_request', [
            'settings' => BusinessSettingsService::get(),
            'customer' => (object) [
                'id' => 55,
                'name' => 'Sokha Applicant',
                'khmer_name' => 'ស៊ុក អត្ថ',
                'phone' => '012 999 888',
                'alternate_phone' => null,
                'address' => 'Phnom Penh',
                'id_card_number' => '123456789',
                'workplace' => 'ABC Store',
                'monthly_income' => 800,
                'customer_photo_url' => null,
                'family_contact_name' => null,
                'family_contact_phone' => null,
                'spouse_name' => null,
                'spouse_phone' => null,
                'username' => 'sokha',
            ],
            'defaultInterestRate' => 1.5,
            'locations' => collect(),
            'catalogProducts' => [],
            'errors' => new ViewErrorBag,
        ])->render();

        foreach (['id_card_front', 'id_card_back', 'income_proof', 'collateral_photo'] as $field) {
            $this->assertStringContainsString('name="'.$field.'[]" multiple', $html, $field.' must accept multiple files');
            $this->assertStringNotContainsString('name="'.$field.'" ', $html, $field.' must no longer be single-file');
        }

        $this->assertSame(4, substr_count($html, 'data-md-slot '));
        $this->assertSame(4, substr_count($html, 'data-md-queue>'));
        $this->assertSame(4, substr_count($html, 'data-md-count>'));
        $this->assertSame(4, substr_count($html, 'data-md-add>'));
        $this->assertSame(4, substr_count($html, 'data-md-max="8"'));
        $this->assertStringContainsString('window.MDUpload', $html);
        $this->assertStringContainsString('Keep original', $html);
        $this->assertStringContainsString('openCropper', $html);
    }

    public function testRegisterInstallmentRequestModalShowsTheSameMultiFileSlots(): void
    {
        $html = view('loanmanagement::public.partials.installment_request_modal')->render();

        foreach (['id_card_front', 'id_card_back', 'income_proof', 'collateral_photo'] as $field) {
            $this->assertStringContainsString('name="'.$field.'[]" multiple', $html, $field.' must accept multiple files');
        }

        $this->assertSame(4, substr_count($html, 'data-md-slot '));
        $this->assertSame(4, substr_count($html, 'data-md-max="8"'));
        $this->assertStringContainsString('window.MDUpload', $html);
        $this->assertStringContainsString('MDUpload.syncAll()', $html);
        $this->assertStringContainsString('clearDocs()', $html);
    }

    public function testInstallmentCartSendsOriginalFileNamesAndOffersCropControls(): void
    {
        $source = file_get_contents(module_path('LoanManagement', 'Resources').'/views/loans/standalone/modal.blade.php');

        $this->assertStringContainsString("fd.append('documents[]', d.dataUri)", $source);
        $this->assertStringContainsString("fd.append('document_names[]', d.name)", $source);
        $this->assertStringContainsString('function mobCropDoc(', $source);
        $this->assertStringContainsString('function mobKeepOriginalDoc(', $source);
        $this->assertStringContainsString('window.MDUpload.openCropper(item.file', $source);
        $this->assertStringContainsString('item.originalDataUri', $source);

        $rules = (new StoreStandaloneLoanRequest)->rules();
        $this->assertArrayHasKey('document_names', $rules);
        $this->assertSame('nullable|array|max:12', $rules['document_names']);
        $this->assertSame('nullable|string|max:14000000', $rules['documents.*']);
    }

    public function testCartDocumentsAreStoredWithTheUploadedFileName(): void
    {
        $shot = UploadedFile::fake()->image('shot.png', 40, 30);
        $png = 'data:image/png;base64,'.base64_encode((string) file_get_contents($shot->getPathname()));
        $pdf = 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 test');

        $loanId = (new CreateStandaloneLoanService)->createStandaloneLoan($this->cartLoanPayload([
            'documents' => [$png, $pdf],
            'document_names' => ['payslip-march.png', 'id-card-scan.pdf'],
        ]));

        $names = DB::connection('mysql_loan')->table('loan_files')
            ->where('fileable_type', LoanCustomer::class)
            ->where('fileable_id', 55)
            ->where('category', 'document')
            ->orderBy('id')
            ->pluck('original_name')
            ->all();

        $this->assertSame(['payslip-march.png', 'id-card-scan.pdf'], $names);
        $this->assertGreaterThan(0, $loanId);
    }

    public function testCartDocumentNamesAreSanitisedAndFallBackToAGeneratedName(): void
    {
        $service = new CreateStandaloneLoanService;
        $method = new \ReflectionMethod($service, 'safeDocumentName');
        $method->setAccessible(true);

        $this->assertSame('payslip.png', $method->invoke($service, 'payslip.png', 'jpg'));
        $this->assertSame('id scan.png', $method->invoke($service, ' ../../id scan.png', 'jpg'));
        $this->assertSame('notes.txt', $method->invoke($service, 'notes.exe', 'txt'));
        $this->assertSame('scan.jpg', $method->invoke($service, 'scan', 'jpg'));
        $this->assertNull($method->invoke($service, '   ', 'jpg'));
        $this->assertNull($method->invoke($service, '../../', 'jpg'));

        $shot = UploadedFile::fake()->image('shot.png', 40, 30);
        $png = 'data:image/png;base64,'.base64_encode((string) file_get_contents($shot->getPathname()));

        (new CreateStandaloneLoanService)->createStandaloneLoan($this->cartLoanPayload([
            'documents' => [$png],
            'document_names' => ['../'],
        ]));

        $name = DB::connection('mysql_loan')->table('loan_files')->where('category', 'document')->value('original_name');
        $this->assertMatchesRegularExpression('/^customer-document-\d+-1\.png$/', (string) $name);
    }

    public function testRegistrationStoresMultipleDocumentsOnThePendingCustomer(): void
    {
        $request = Request::create('/register', 'POST', [
            'name' => 'Pending Applicant',
            'phone' => '012 555 444',
            'address' => 'Phnom Penh',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'installment_items' => '[]',
        ], [], [
            'id_card_front' => [
                UploadedFile::fake()->image('pass-front-a.jpg', 400, 260),
                UploadedFile::fake()->image('pass-front-b.jpg', 400, 260),
            ],
            'id_card_back' => [UploadedFile::fake()->image('pass-back.jpg', 400, 260)],
            'income_proof' => [UploadedFile::fake()->create('payslip.pdf', 60, 'application/pdf')],
        ], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession(session()->driver());
        app()->instance('request', $request);

        $response = $this->controller()->storeRegistration($request, new LoanCustomerService);
        $this->assertSame(201, $response->getStatusCode());

        $customer = DB::connection('mysql_loan')->table('loan_customers')->where('name', 'Pending Applicant')->first();
        $this->assertNotNull($customer);

        $rows = DB::connection('mysql_loan')->table('loan_files')->orderBy('id')->get();
        $this->assertCount(4, $rows);
        $this->assertSame(['pass-front-a.jpg', 'pass-front-b.jpg'], $this->sortedNames($rows->where('category', 'id_front')));
        $this->assertSame(['pass-back.jpg'], $this->sortedNames($rows->where('category', 'id_back')));
        $this->assertSame(['payslip.pdf'], $this->sortedNames($rows->where('category', 'income_proof')));

        foreach ($rows as $row) {
            $this->assertSame(LoanCustomer::class, $row->fileable_type);
            $this->assertSame((int) $customer->id, (int) $row->fileable_id);
            $this->assertStringStartsWith('loan-customers/'.$customer->id.'/', $row->path);
            $this->assertFileExists($this->storagePath($row->path));
        }
    }

    public function testMultipleFilesPerSlotAreAllStoredWithTheirCategories(): void
    {
        $files = [
            'id_card_front' => [
                UploadedFile::fake()->image('front-a.jpg', 400, 260),
                UploadedFile::fake()->image('front-b.png', 400, 260),
            ],
            'id_card_back' => [
                UploadedFile::fake()->image('back-a.jpg', 400, 260),
            ],
            'income_proof' => [
                UploadedFile::fake()->image('slip-1.jpg', 400, 260),
                UploadedFile::fake()->create('slip-2.pdf', 120, 'application/pdf'),
            ],
            'collateral_photo' => [
                UploadedFile::fake()->image('phone.jpg', 400, 260),
                UploadedFile::fake()->image('receipt.jpg', 400, 260),
            ],
        ];

        $response = $this->controller()->storeCustomerLoanRequest(
            $this->request($files),
            new CreateStandaloneLoanService
        );

        $this->assertSame(302, $response->getStatusCode());

        $rows = DB::connection('mysql_loan')->table('loan_files')->orderBy('id')->get();
        $this->assertCount(7, $rows, 'Every uploaded file must be stored: '.implode(', ', $rows->map(fn ($r) => $r->category.':'.$r->original_name)->all()));

        $loanId = DB::connection('mysql_loan')->table('loans')->value('id');
        $byCategory = $rows->groupBy('category')->map->pluck('original_name', 'path');

        $this->assertSame(
            ['front-a.jpg', 'front-b.png'],
            $this->sortedNames($rows->where('category', 'id_front'))
        );
        $this->assertSame(['back-a.jpg'], $this->sortedNames($rows->where('category', 'id_back')));
        $this->assertSame(['slip-1.jpg', 'slip-2.pdf'], $this->sortedNames($rows->where('category', 'income_proof')));
        $this->assertSame(['phone.jpg', 'receipt.jpg'], $this->sortedNames($rows->where('category', 'collateral')));

        foreach ($rows as $row) {
            $this->assertSame(Loan::class, $row->fileable_type);
            $this->assertSame((int) $loanId, (int) $row->fileable_id);
            $this->assertSame('public', $row->disk);
            $this->assertStringStartsWith('loan-files/'.$loanId.'/', $row->path);
            $this->assertNotNull($row->mime_type);
            $this->assertGreaterThan(0, (int) $row->size_bytes);
            $this->assertFileExists($this->storagePath($row->path));
        }
    }

    public function testSingleFileSlotsAndAbsentSlotsRemainSupported(): void
    {
        $response = $this->controller()->storeCustomerLoanRequest(
            $this->request([
                'id_card_front' => [UploadedFile::fake()->image('only-one.jpg', 400, 260)],
            ]),
            new CreateStandaloneLoanService
        );

        $this->assertSame(302, $response->getStatusCode());
        $rows = DB::connection('mysql_loan')->table('loan_files')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('id_front', $rows->first()->category);
        $this->assertSame('only-one.jpg', $rows->first()->original_name);
    }

    public function testOriginalFileBytesAreStoredUntouched(): void
    {
        $upload = UploadedFile::fake()->image('receipt.png', 320, 200);
        $originalBytes = file_get_contents($upload->getPathname());

        $this->controller()->storeCustomerLoanRequest(
            $this->request(['collateral_photo' => [$upload]]),
            new CreateStandaloneLoanService
        );

        $row = DB::connection('mysql_loan')->table('loan_files')->first();
        $this->assertSame($originalBytes, file_get_contents($this->storagePath($row->path)));
    }

    public function testUnsupportedTypeAndMoreThanEightFilesAreRejected(): void
    {
        try {
            $this->controller()->storeCustomerLoanRequest(
                $this->request(['income_proof' => [UploadedFile::fake()->create('malware.exe', 10)]]),
                new CreateStandaloneLoanService
            );
            $this->fail('Expected unsupported document type to be rejected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('income_proof.0', $exception->errors());
        }

        $tooMany = [];
        for ($i = 0; $i < 9; $i++) {
            $tooMany[] = UploadedFile::fake()->image('page-'.$i.'.jpg', 200, 200);
        }

        try {
            $this->controller()->storeCustomerLoanRequest(
                $this->request(['id_card_front' => $tooMany]),
                new CreateStandaloneLoanService
            );
            $this->fail('Expected more than eight files to be rejected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('id_card_front', $exception->errors());
        }

        $this->assertSame(0, DB::connection('mysql_loan')->table('loan_files')->count());
    }

    private function cartLoanPayload(array $extra = []): array
    {
        return array_merge([
            'action_type' => 'create_pending',
            'customer_id' => 55,
            'customer_name' => 'Sokha Applicant',
            'customer_phone' => '012 999 888',
            'customer_address' => 'Phnom Penh',
            'loan_date' => now()->toDateString(),
            'principal_amount' => 500,
            'down_payment' => 0,
            'duration_months' => 6,
            'interest_rate' => 1.5,
            'interest_type' => 'flat',
            'payment_frequency' => 'monthly',
            'first_due_date' => now()->addMonth()->toDateString(),
            'items' => [],
        ], $extra);
    }

    private function sortedNames($rows): array
    {
        $names = $rows->pluck('original_name')->all();
        sort($names);
        return $names;
    }

    private function storagePath(string $path): string
    {
        return Storage::disk('public')->path($path);
    }
}
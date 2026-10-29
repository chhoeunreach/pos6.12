<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanCustomerDetailTest extends LoanModuleFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('mysql_loan');
        $schema = Schema::connection('mysql_loan');
        $schema->create('loan_customers', function (Blueprint $table) {
            $table->increments('id'); $table->string('customer_code'); $table->string('name'); $table->string('khmer_name')->nullable(); $table->string('phone');
            $table->string('status')->default('active'); $table->text('note')->nullable(); $table->string('address')->nullable(); $table->timestamps();
        });
        $schema->create('loans', function (Blueprint $table) {
            $table->increments('id'); $table->integer('customer_id'); $table->string('loan_number'); $table->string('status')->default('active');
            $table->string('currency')->default('USD'); $table->decimal('principal_amount', 18, 2); $table->decimal('balance_amount', 18, 2); $table->date('loan_date')->nullable(); $table->softDeletes();
        });
        $schema->create('loan_payments', function (Blueprint $table) {
            $table->increments('id'); $table->integer('loan_id')->nullable(); $table->integer('customer_id')->nullable(); $table->string('receipt_number')->nullable();
            $table->decimal('total_paid_base', 18, 2)->nullable(); $table->decimal('total_paid', 18, 2)->nullable(); $table->date('paid_date')->nullable(); $table->softDeletes();
        });
        DB::connection('mysql_loan')->table('loan_customers')->insert(['id' => 1, 'customer_code' => 'CUST-001', 'name' => 'Sok San', 'khmer_name' => 'សុខ សាន', 'phone' => '012 345 678', 'note' => '<script>alert(1)</script>', 'address' => 'Phnom Penh']);
        $this->staff(['loan_management.view', 'loan_management.edit']);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    protected function tearDown(): void { DB::purge('mysql_loan'); parent::tearDown(); }

    private function staff(array $permissions): void
    {
        auth()->setUser(new class(['id' => 1, 'name' => 'Customer Staff', 'permissions' => $permissions]) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return in_array($ability, $this->permissions, true); }
        });
    }

    private function history(): void
    {
        $db = DB::connection('mysql_loan');
        for ($id = 1; $id <= 25; $id++) {
            $db->table('loans')->insert(['id' => $id, 'customer_id' => 1, 'loan_number' => 'LN-'.$id, 'principal_amount' => 100, 'balance_amount' => $id === 25 ? 1000 : 10, 'currency' => $id === 25 ? 'KHR' : 'USD']);
            $db->table('loan_payments')->insert(['id' => $id, 'loan_id' => $id, 'customer_id' => $id % 2 ? 1 : null, 'receipt_number' => 'RC-'.$id, 'total_paid_base' => 4, 'total_paid' => 1000, 'paid_date' => '2026-10-'.str_pad($id, 2, '0', STR_PAD_LEFT)]);
        }
        $db->table('loan_payments')->insert(['id' => 26, 'customer_id' => 1, 'receipt_number' => 'LEGACY', 'total_paid' => 5, 'paid_date' => '2026-09-01']);
        $db->table('loans')->insert(['id' => 30, 'customer_id' => 99, 'loan_number' => 'OTHER-CUSTOMER', 'principal_amount' => 5000, 'balance_amount' => 5000]);
        $db->table('loans')->insert(['id' => 31, 'customer_id' => 1, 'loan_number' => 'DELETED', 'principal_amount' => 9000, 'balance_amount' => 9000, 'deleted_at' => now()]);
        $db->table('loan_payments')->insert(['id' => 30, 'loan_id' => 30, 'customer_id' => 99, 'receipt_number' => 'OTHER-PAYMENT', 'total_paid_base' => 9000]);
        $db->table('loan_payments')->insert(['id' => 31, 'customer_id' => 1, 'receipt_number' => 'DELETED-PAYMENT', 'total_paid_base' => 9000, 'deleted_at' => now()]);
    }

    public function test_full_history_summaries_are_not_limited_and_balances_keep_their_currencies(): void
    {
        $this->history();
        $data = app(LoanCustomerController::class)->show(1)->getData();
        $this->assertCount(20, $data['loans']);
        $this->assertCount(20, $data['payments']);
        $this->assertSame(25, $data['loanSummary']['count']);
        $this->assertSame(26, $data['paymentSummary']['count']);
        $this->assertSame(105.0, $data['paymentSummary']['amount']);
        $groups = $data['loanSummary']['currencies']->keyBy('currency');
        $this->assertSame(240.0, (float) $groups['USD']->balance);
        $this->assertSame(1000.0, (float) $groups['KHR']->balance);
        $this->assertSame('RC-25', $data['payments']->first()->receipt_number, 'Payment date takes precedence over record ID');
        $this->assertFalse($data['loans']->contains('loan_number', 'OTHER-CUSTOMER'));
    }

    public function test_profile_renders_grouped_fields_safe_notes_and_authorized_actions(): void
    {
        $this->history();
        $html = app(LoanCustomerController::class)->show(1)->render();
        $this->assertStringContainsString('loan-customer-detail.css', $html);
        $this->assertStringContainsString('សុខ សាន', $html);
        $this->assertStringContainsString('Phnom Penh', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('href="tel:012345678"', $html);
        $this->assertStringContainsString('href="'.route('loan-management.customers.edit', 1).'"', $html);
        $this->assertStringContainsString('href="'.route('loan-management.loans.view', 25).'"', $html);
        $this->assertStringContainsString('href="'.route('loan-management.payments.show', 25).'"', $html);
        $document = new \DOMDocument; @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        foreach (['customer-identity-heading', 'customer-contact-heading', 'customer-family-heading', 'customer-access-heading', 'customer-installments', 'customer-payments'] as $id) {
            $this->assertSame(1, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " lcd-page ")]//*[@id="'.$id.'"]')->length, $id.' must stay inside the profile layout');
        }
    }

    public function test_minimal_profile_handles_missing_optional_data_and_hides_restricted_links(): void
    {
        $this->staff(['loan_management.customers.view']);
        $schema = Schema::connection('mysql_loan'); $schema->drop('loan_payments'); $schema->drop('loans');
        $html = app(LoanCustomerController::class)->show(1)->render();
        $this->assertStringContainsString('No installments yet', $html);
        $this->assertStringContainsString('No payments yet', $html);
        $this->assertStringContainsString('Disabled', $html);
        $this->assertStringContainsString('Not linked', $html);
        $this->assertStringNotContainsString('href="'.route('loan-management.customers.edit', 1).'"', $html);
        $this->assertStringContainsString('—', $html);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\LoanManagement\Http\Controllers\SettingsController;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Tests\Support\LoanModuleFeatureTestCase;

class LoanBusinessSettingsTest extends LoanModuleFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.mysql_loan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('mysql_loan');
        $schema = Schema::connection('mysql_loan');
        $schema->create('loan_payments', function (Blueprint $table) { $table->increments('id'); $table->string('payment_method_snapshot'); $table->decimal('total_paid_base', 18, 2); });
        $schema->create('loan_payment_details', function (Blueprint $table) { $table->increments('id'); $table->integer('payment_id'); $table->string('payment_method_snapshot'); $table->decimal('amount_base', 18, 2); });
        $schema->create('loan_payment_methods', function (Blueprint $table) { $table->increments('id'); $table->string('name')->unique(); $table->string('code')->nullable(); $table->integer('sort_order')->default(0); $table->boolean('is_active')->default(true); $table->timestamps(); });
        DB::connection('mysql_loan')->table('loan_payment_methods')->insert([
            ['id' => 1, 'name' => 'Cash', 'code' => 'cash'], ['id' => 2, 'name' => 'ABA', 'code' => 'aba'],
        ]);
        $this->staff(['loan_management.setting']);
        Cache::put('loan_management.sidebar_badges', [], 600);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void { DB::purge('mysql_loan'); parent::tearDown(); }

    private function staff(array $permissions): void
    {
        auth()->setUser(new class(['id' => 90001, 'name' => 'Settings Staff', 'permissions' => $permissions]) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function hasRole($roles, $guard = null): bool { return false; }
            public function can($ability, $arguments = []): bool { return in_array($ability, $this->permissions, true); }
        });
    }

    private function request(array $changes = []): Request
    {
        $defaults = BusinessSettingsService::get();
        $data = array_intersect_key($defaults, array_flip(['business_name', 'system_name', 'currency_code', 'currency_symbol_placement', 'time_zone', 'fy_start_month', 'transaction_edit_days', 'date_format', 'time_format', 'currency_precision', 'theme_color', 'invoice_message_template']));
        $request = Request::create('/loan-management/settings/business', 'POST', array_replace($data, $changes));
        $request->setLaravelSession(session()->driver());
        $this->app->instance('request', $request);
        return $request;
    }

    public function test_settings_permission_alone_can_render_all_sections_and_save(): void
    {
        $view = (new SettingsController)->business();
        $html = $view->render();
        foreach (['profile', 'policies', 'branding', 'currency', 'payment', 'templates', 'notifications', 'social', 'cms', 'portal'] as $tab) $this->assertStringContainsString('id="tab-'.$tab.'"', $html);
        $this->assertStringContainsString('loan-settings.js', $html);
        $this->assertStringContainsString('id="testTelegramBot"', $html);
        $response = (new SettingsController)->updateBusiness($this->request(['business_name' => 'Updated Company', 'active_tab' => 'tab-payment']));
        $this->assertSame('Updated Company', BusinessSettingsService::get()['business_name']);
        $this->assertStringEndsWith('#tab-payment', $response->getTargetUrl());
    }

    public function test_view_only_staff_see_disabled_controls_without_bot_secret_and_cannot_save(): void
    {
        BusinessSettingsService::save(['telegram_bot_token' => '12345:private_token']);
        $this->staff(['loan_management.settings.view']);
        $html = (new SettingsController)->business()->render();
        $this->assertStringContainsString('class="lm-settings-fields" disabled', $html);
        $this->assertStringNotContainsString('12345:private_token', $html);
        $this->assertStringNotContainsString('id="saveSettingsBtn"', $html);
        try { (new SettingsController)->updateBusiness($this->request()); $this->fail('Expected authorization failure'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_nullable_fields_clear_and_unsubmitted_switches_are_preserved(): void
    {
        BusinessSettingsService::save(['legal_name' => 'Old Name', 'company_email' => 'old@example.test', 'contract_terms' => 'Old terms', 'telegram_bot_token' => '12345:old_token', 'start_date' => '2026-01-01', 'cms_enabled' => true, 'notify_new_loan' => true]);
        (new SettingsController)->updateBusiness($this->request(['legal_name' => null, 'company_email' => null, 'contract_terms' => null, 'telegram_bot_token' => null, 'start_date' => null]));
        $saved = BusinessSettingsService::get();
        foreach (['legal_name', 'company_email', 'contract_terms', 'telegram_bot_token'] as $key) $this->assertSame('', $saved[$key]);
        $this->assertNull($saved['start_date']);
        $this->assertTrue($saved['cms_enabled']);
        $this->assertTrue($saved['notify_new_loan']);
        (new SettingsController)->updateBusiness($this->request(['cms_enabled' => '0', 'notify_new_loan' => '0']));
        $this->assertFalse(BusinessSettingsService::get()['cms_enabled']);
        $this->assertFalse(BusinessSettingsService::get()['notify_new_loan']);
    }

    public function test_image_removal_clears_file_and_saved_path(): void
    {
        Storage::fake('public');
        foreach (['logo', 'stamp', 'login_background', 'home_hero'] as $key) Storage::disk('public')->put('loan-management/business/'.$key.'.png', 'sample');
        BusinessSettingsService::save(['logo_path' => 'loan-management/business/logo.png', 'stamp_path' => 'loan-management/business/stamp.png', 'login_background_path' => 'loan-management/business/login_background.png', 'home_hero_path' => 'loan-management/cms/old-hero.png']);
        Storage::disk('public')->put('loan-management/cms/old-hero.png', 'sample');
        (new SettingsController)->updateBusiness($this->request(['remove_logo' => '1', 'remove_stamp' => '1', 'remove_login_background' => '1', 'remove_home_hero' => '1']));
        $this->assertNull(BusinessSettingsService::get()['home_hero_path']);
        Storage::disk('public')->assertMissing('loan-management/cms/old-hero.png');
        foreach (['logo', 'stamp', 'login_background'] as $key) {
            $this->assertNull(BusinessSettingsService::get()[$key.'_path']);
            Storage::disk('public')->assertMissing('loan-management/business/'.$key.'.png');
        }
    }

    public function test_invalid_policy_timezone_social_and_duplicate_method_fail_before_saving(): void
    {
        foreach ([['min_loan_amount' => 1000, 'max_loan_amount' => 10], ['time_zone' => 'Fake/Zone'], ['home_cms' => ['footer_facebook' => 'javascript:alert(1)']], ['new_method' => ['name' => 'cash']]] as $changes) {
            try { (new SettingsController)->updateBusiness($this->request($changes + ['business_name' => 'Must not save'])); $this->fail('Expected validation error'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
            $this->assertNotSame('Must not save', BusinessSettingsService::get()['business_name']);
        }
    }

    public function test_payment_channels_add_edit_and_deactivate_with_split_aware_usage(): void
    {
        $db = DB::connection('mysql_loan');
        $db->table('loan_payments')->insert([['id' => 1, 'payment_method_snapshot' => 'Cash + ABA', 'total_paid_base' => 100], ['id' => 2, 'payment_method_snapshot' => 'Cash', 'total_paid_base' => 25]]);
        $db->table('loan_payment_details')->insert([['payment_id' => 1, 'payment_method_snapshot' => 'Cash', 'amount_base' => 40], ['payment_id' => 1, 'payment_method_snapshot' => 'ABA', 'amount_base' => 60]]);
        $usage = (new SettingsController)->business()->getData()['usage'];
        $this->assertSame(['payments_count' => 2, 'total_amount' => 65.0], $usage['Cash']);
        $this->assertSame(['payments_count' => 1, 'total_amount' => 60.0], $usage['ABA']);
        $this->assertArrayNotHasKey('Cash + ABA', $usage);
        (new SettingsController)->updateBusiness($this->request(['methods' => [1 => ['name' => 'Cash', 'code' => 'cash', 'is_active' => '0', 'sort_order' => 4]], 'new_method' => ['name' => 'Wing', 'code' => 'wing', 'sort_order' => 3]]));
        $this->assertSame(0, (int) $db->table('loan_payment_methods')->where('id', 1)->value('is_active'));
        $this->assertSame('wing', $db->table('loan_payment_methods')->where('name', 'Wing')->value('code'));
        try { (new SettingsController)->updateBusiness($this->request(['methods' => [1 => ['name' => 'Renamed Cash']]])); $this->fail('Historical name must remain'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('methods.1.name', $e->errors()); }
    }

    public function test_bot_check_uses_get_me_only_and_sanitizes_failures(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'sample_bot']])]);
        $response = (new SettingsController)->testTelegram($this->request(['telegram_bot_token' => '12345:sample_token']));
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Connected to @sample_bot', $response->getData(true)['message']);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_ends_with($request->url(), '/getMe'));
        Http::assertSentCount(1);
        Http::fake(['api.telegram.org/*' => function () { throw new \RuntimeException('failed URL bot12345:sample_token'); }]);
        $response = (new SettingsController)->testTelegram($this->request(['telegram_bot_token' => '12345:sample_token']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringNotContainsString('sample_token', $response->getContent());
    }
}

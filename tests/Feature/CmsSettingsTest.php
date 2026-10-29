<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Modules\LoanManagement\Http\Controllers\SettingsController;
use Modules\LoanManagement\Services\BusinessSettingsService;
use Modules\LoanManagement\Services\CmsHomeService;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CmsSettingsTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['cache.default' => 'array', 'session.driver' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        $this->storage = sys_get_temp_dir().'/cms-settings-'.bin2hex(random_bytes(5));
        $app->useStoragePath($this->storage);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        auth()->setUser(new class(['id' => 92001, 'name' => 'Sample Staff']) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function hasRole($roles, $guard = null): bool { return false; }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool { return true; }
        });
    }

    protected function tearDown(): void
    {
        app('files')->deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function testCmsSavePersistsSectionsAndPreservesOtherSettings(): void
    {
        BusinessSettingsService::save(['business_name' => 'Sample Business']);
        $cms = CmsHomeService::normalize(['catalog' => false, 'menu_about' => false, 'label_products' => 'Our Shop', 'contact_email' => 'support@example.com']);
        $request = Request::create('/loan-management/settings/cms', 'POST', ['home_headline' => 'Sample Headline', 'home_subtitle' => 'Sample Subtitle', 'home_body' => 'Sample About', 'home_cms' => $cms]);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        $response = (new SettingsController)->updateCms($request);
        $saved = BusinessSettingsService::get();
        $this->assertFalse($saved['home_cms']['catalog']);
        $this->assertFalse($saved['home_cms']['menu_about']);
        $this->assertSame('Our Shop', $saved['home_cms']['label_products']);
        $this->assertSame('support@example.com', $saved['home_cms']['contact_email']);
        $this->assertSame('Sample Business', $saved['business_name']);
        $this->assertStringContainsString('/settings/cms', $response->getTargetUrl());
        BusinessSettingsService::save(['business_name' => 'Updated Business']);
        $this->assertFalse(BusinessSettingsService::get()['home_cms']['catalog']);
        BusinessSettingsService::save(['home_hero_path' => 'loan-management/cms/sample.jpg']);
        BusinessSettingsService::save(['home_hero_path' => null]);
        $this->assertNull(BusinessSettingsService::get()['home_hero_path']);
    }

    public function testViewOnlyStaffCannotModifyCms(): void
    {
        auth()->setUser(new class(['id' => 92002]) extends GenericUser implements \Illuminate\Contracts\Auth\Access\Authorizable {
            public function hasRole($roles, $guard = null): bool { return false; }
            public function __get($key) { return $this->attributes[$key] ?? null; }
            public function can($ability, $arguments = []): bool { return $ability === 'loan_management.view'; }
        });
        try {
            (new SettingsController)->updateCms(Request::create('/test', 'POST'));
            $this->fail('Expected permission denial');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function testManagedBrandsSaveOrderVisibilityAndRemoval(): void
    {
        $cms = CmsHomeService::normalize(['brands_source' => 'managed', 'brands_items' => [
            ['name' => 'Lenovo', 'logo_url' => 'https://example.com/logo.png', 'website_url' => 'https://example.com', 'enabled' => true],
            ['name' => 'ASUS', 'enabled' => false],
        ]]);
        $data = ['home_headline' => 'Sample Headline', 'home_subtitle' => 'Sample Subtitle', 'home_cms' => $cms];
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $saved = BusinessSettingsService::get()['home_cms'];
        $this->assertSame($cms['brands_items'], $saved['brands_items']);
        $this->assertSame(['Lenovo'], array_column(CmsHomeService::visibleBrands($saved, ['Catalog Brand']), 'name'));

        $data['home_cms']['brands_items'] = [
            ['name' => 'ASUS Updated', 'website_url' => 'https://www.asus.com', 'enabled' => true],
            ['name' => 'Lenovo', 'enabled' => false],
        ];
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $saved = BusinessSettingsService::get()['home_cms'];
        $this->assertSame(['ASUS Updated', 'Lenovo'], array_column($saved['brands_items'], 'name'));
        $this->assertSame('https://www.asus.com', $saved['brands_items'][0]['website_url']);
        $this->assertSame(['ASUS Updated'], array_column(CmsHomeService::visibleBrands($saved, []), 'name'));

        $data['home_cms']['brands_items'] = [$data['home_cms']['brands_items'][0]];
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $this->assertSame(['ASUS Updated'], array_column(BusinessSettingsService::get()['home_cms']['brands_items'], 'name'));

        $data['home_cms']['brands_items'] = '';
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $this->assertSame([], BusinessSettingsService::get()['home_cms']['brands_items']);
    }

    public function testPartnerLinksRejectUnsupportedProtocols(): void
    {
        $data = ['home_headline' => 'Sample Headline', 'home_subtitle' => 'Sample Subtitle', 'home_cms' => CmsHomeService::normalize([])];
        $data['home_cms']['brands_items'] = [['name' => 'Unsafe Partner', 'website_url' => 'javascript:alert(1)', 'enabled' => '1']];
        $request = Request::create('/test', 'POST', $data);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        try {
            (new SettingsController)->updateCms($request);
            $this->fail('Expected invalid website URL to be rejected');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('home_cms.brands_items.0.website_url', $exception->errors());
        }
    }

    public function testHeroUploadCanBeReplacedAndRestored(): void
    {
        config(['filesystems.disks.public.root' => $this->storage.'/public']);
        $data = ['home_headline' => 'Sample Headline', 'home_subtitle' => 'Sample Subtitle', 'home_body' => '', 'home_cms' => CmsHomeService::normalize([])];
        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('hero.jpg', file_get_contents(module_path('LoanManagement', 'Resources/assets/cms-home/hero.jpg')));
        $request = Request::create('/loan-management/settings/cms', 'POST', $data, [], ['home_hero' => $upload]);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $path = BusinessSettingsService::get()['home_hero_path'];
        $this->assertStringStartsWith('loan-management/cms/', $path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($path));
        $request = Request::create('/loan-management/settings/cms', 'POST', $data + ['remove_home_hero' => '1']);
        $request->setLaravelSession(session()->driver()); app()->instance('request', $request);
        (new SettingsController)->updateCms($request);
        $this->assertNull(BusinessSettingsService::get()['home_hero_path']);
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists($path));
    }

    public function testSettingsViewContainsControlsAndImageUpload(): void
    {
        session()->put('user.language', 'en');
        \Illuminate\Support\Facades\Cache::put('loan_management.sidebar_badges', [], 600);
        $html = (new SettingsController)->cms()->render();
        foreach (CmsHomeService::fields() as $key => $field) {
            $this->assertStringContainsString('name="home_cms['.$key.']"', $html);
        }
        $this->assertStringContainsString('name="home_hero"', $html);
        $this->assertStringContainsString('multipart/form-data', $html);
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $brandSection = (new \DOMXPath($document))->query('//details[summary="Authorized Brands & Partners"]')->item(0);
        $this->assertNotNull($brandSection);
        $this->assertTrue($brandSection->hasAttribute('open'));
        $this->assertArrayHasKey('brands', CmsHomeService::groups()['Authorized Brands & Partners']);
        $this->assertArrayHasKey('brands_items', CmsHomeService::groups()['Authorized Brands & Partners']);
    }

    public function testCmsSeederInitializesSamplesAndPreservesEditsWhenRepeated(): void
    {
        $seeder = new \Modules\LoanManagement\Database\Seeders\CmsHomeSeeder;
        $seeder->run();
        $first = BusinessSettingsService::get();
        $this->assertSame('managed', $first['home_cms']['brands_source']);
        $this->assertCount(8, $first['home_cms']['brands_items']);
        $this->assertSame('ASUS', $first['home_cms']['brands_items'][4]['name']);
        BusinessSettingsService::save(['home_headline' => 'Custom Headline', 'home_cms' => array_merge($first['home_cms'], [
            'brands' => false, 'contact_address' => 'Custom Address', 'brands_items' => [['name' => 'Custom Partner']],
        ])]);
        $seeder->run();
        $saved = BusinessSettingsService::get();
        $this->assertSame('Custom Headline', $saved['home_headline']);
        $this->assertSame('Custom Address', $saved['home_cms']['contact_address']);
        $this->assertFalse($saved['home_cms']['brands']);
        $this->assertSame(['Custom Partner'], array_column($saved['home_cms']['brands_items'], 'name'));
        $seeder->run();
        $this->assertSame($saved, BusinessSettingsService::get());
        BusinessSettingsService::save(['home_cms' => array_merge($saved['home_cms'], ['step_3_title' => 'Track your account'])]);
        $seeder->run();
        $this->assertSame('Staff follow-up', BusinessSettingsService::get()['home_cms']['step_3_title']);
        $this->assertSame('Custom Address', BusinessSettingsService::get()['home_cms']['contact_address']);
    }

    public function testBusinessSettingsEnterpriseFieldsAndDefaults(): void
    {
        BusinessSettingsService::save([
            'business_name' => 'NTL Microfinance',
            'legal_name' => 'NTL (CAMBODIA) CO., LTD',
            'tax_number' => 'K001-901234567',
            'default_interest_rate' => '1.75',
            'interest_rate_period' => 'monthly',
            'default_interest_method' => 'declining',
            'grace_period_days' => 5,
            'penalty_type' => 'percentage',
            'penalty_value' => '0.20',
            'loan_prefix' => 'MFI-',
            'customer_prefix' => 'BORROWER-',
            'receipt_printer_type' => 'thermal_80mm',
            'telegram_bot_token' => '999888777:TEST_TOKEN',
            'telegram_chat_id' => '-100987654321',
            'notify_new_loan' => true,
        ]);

        $settings = BusinessSettingsService::get();
        $this->assertSame('NTL Microfinance', $settings['business_name']);
        $this->assertSame('NTL (CAMBODIA) CO., LTD', $settings['legal_name']);
        $this->assertSame('K001-901234567', $settings['tax_number']);
        $this->assertSame('1.75', $settings['default_interest_rate']);
        $this->assertSame('1.75', $settings['default_profit_percent']); // backward compatibility synced
        $this->assertSame('monthly', $settings['interest_rate_period']);
        $this->assertSame('declining', $settings['default_interest_method']);
        $this->assertSame(5, $settings['grace_period_days']);
        $this->assertSame('percentage', $settings['penalty_type']);
        $this->assertSame('0.20', $settings['penalty_value']);
        $this->assertSame('MFI-', $settings['loan_prefix']);
        $this->assertSame('BORROWER-', $settings['customer_prefix']);
        $this->assertSame('thermal_80mm', $settings['receipt_printer_type']);
        $this->assertSame('999888777:TEST_TOKEN', $settings['telegram_bot_token']);
        $this->assertSame('-100987654321', $settings['telegram_chat_id']);
        $this->assertTrue($settings['notify_new_loan']);
    }

    public function testSocialMediaSettingsSaveThroughBusinessSettingsAndUpdateHomeCms(): void
    {
        $payload = [
            'business_name' => 'NTL Finance',
            'system_name' => 'Loan App',
            'currency_code' => 'USD',
            'currency_symbol_placement' => 'before',
            'time_zone' => 'Asia/Phnom_Penh',
            'fy_start_month' => 1,
            'transaction_edit_days' => 30,
            'date_format' => 'd-m-Y',
            'time_format' => 24,
            'currency_precision' => 2,
            'theme_color' => '#6366f1',
            'invoice_message_template' => 'Thank you {Customer Name}',
            'home_cms' => [
                'footer_show_social' => '1',
                'footer_social_title' => 'Follow Us Online',
                'footer_facebook' => 'https://facebook.com/ntlfinance',
                'footer_telegram' => 'https://t.me/ntltelegram',
                'footer_tiktok' => 'https://tiktok.com/@ntlfinance',
                'footer_whatsapp' => 'https://wa.me/85512999888',
                'footer_website' => 'https://ntl-finance.com',
            ],
        ];

        $request = Request::create('/loan-management/settings/business', 'POST', $payload);
        $request->setLaravelSession(session()->driver());
        app()->instance('request', $request);

        $response = (new SettingsController)->updateBusiness($request);
        $this->assertTrue($response->isRedirect());

        $saved = BusinessSettingsService::get();
        $this->assertTrue($saved['home_cms']['footer_show_social']);
        $this->assertSame('Follow Us Online', $saved['home_cms']['footer_social_title']);
        $this->assertSame('https://facebook.com/ntlfinance', $saved['home_cms']['footer_facebook']);
        $this->assertSame('https://t.me/ntltelegram', $saved['home_cms']['footer_telegram']);
        $this->assertSame('https://tiktok.com/@ntlfinance', $saved['home_cms']['footer_tiktok']);
        $this->assertSame('https://wa.me/85512999888', $saved['home_cms']['footer_whatsapp']);
        $this->assertSame('https://ntl-finance.com', $saved['home_cms']['footer_website']);
    }

    public function testSocialSettingsRouteRedirectsToTabSocial(): void
    {
        $url = route('loan-management.settings.social');
        $this->assertStringContainsString('/settings/social', $url);
        $businessUrl = route('loan-management.settings.business');
        $this->assertSame($businessUrl . '#tab-social', redirect()->to($businessUrl . '#tab-social')->getTargetUrl());
    }
}

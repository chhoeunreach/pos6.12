<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Console\Kernel;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Tests\Support\LoanModuleFeatureTestCase as TestCase;

class PublicCmsHomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app;
        config(['session.driver' => 'array', 'cache.default' => 'array', 'view.compiled' => sys_get_temp_dir()]);
        auth()->guard('web')->forgetUser();
        auth()->guard('customer_loan')->forgetUser();
    }

    public function renderHome(array $cms = []): string
    {
        return view('loanmanagement::public.home', [
            'settings' => ['home_cms' => $cms, 'home_headline' => 'Shop <safely>', 'home_subtitle' => 'Sample subtitle', 'home_body' => 'Sample about text'],
            'products' => [['id' => 1, 'name' => 'Sample <Laptop>', 'price' => 1200, 'category' => 'Computers', 'brand' => 'Sample Brand']],
            'categories' => ['Computers'], 'brands' => ['Sample Brand'],
        ])->render();
    }

    public function testTemplatePreservesLiveCatalogAndRealGuestLinks(): void
    {
        $html = $this->renderHome();
        foreach (['Products Catalog', 'Installment Guide', 'publicMenuToggle', 'catalogSearchInput', 'cartApply', 'Sample &lt;Laptop&gt;', 'Shop &lt;safely&gt;', 'Sample about text', 'Register Installment Request', 'id="installmentRequestModal"', 'name="installment_items"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString(route('loan-management.public.register'), $html);
        $this->assertStringContainsString(route('loan-management.public.customer-login'), $html);
        $this->assertStringNotContainsString('hero-card', $html);
        $this->assertStringNotContainsString('Apply Installment Installment', $html);
    }

    public function testCustomerDashboardAndRequestLinksRemainAvailable(): void
    {
        auth()->guard('customer_loan')->setUser(new GenericUser(['id' => 123, 'name' => 'Sample Customer', 'phone' => '123', 'username' => 'sample', 'customer_photo_url' => null]));
        $html = $this->renderHome();
        $this->assertStringContainsString('Go to My Dashboard', $html);
        $this->assertStringContainsString(route('loan-management.public.customer-loan-request', ['product_id' => 1]), $html);
        $this->assertStringContainsString('customerProfileToggle', $html);
    }

    public function testHeroAssetIsServedAsAnImage(): void
    {
        $response = (new PublicAppController)->homeImage();
        $this->assertStringStartsWith('image/', $response->getFile()->getMimeType());
        $this->assertGreaterThan(1000, $response->getFile()->getSize());
    }

    public function testHiddenSectionsRemoveMenuLinksAndCartActions(): void
    {
        $html = $this->renderHome(['hero' => false, 'guide' => false, 'about' => false, 'contact' => false, 'cart' => false]);
        foreach (['id="home"', 'href="#home"', 'href="#how"', 'href="#about"', 'href="#contact"', 'id="cart"', 'data-product='] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertStringContainsString('without-cart', $html);
        $this->assertStringContainsString('id="products"', $html);
    }

    public function testCmsContentAndMenuLabelsAreRendered(): void
    {
        $html = $this->renderHome(['label_products' => 'Our Shop', 'contact_phone' => '012 123 456', 'contact_email' => 'support@example.com', 'contact_address' => 'Sample Address', 'guide_title' => 'Sample Guide']);
        foreach (['Our Shop', 'Sample Guide', 'Sample Address', 'mailto:support@example.com', 'tel:012123456'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function testManagedPartnersRenderInOrderWithLogosAndSafeLinks(): void
    {
        $html = $this->renderHome(['brands_source' => 'managed', 'brands_items' => [
            ['name' => 'Partner <b>One</b>', 'logo_url' => 'https://example.com/logo.png', 'website_url' => 'https://example.com', 'enabled' => true],
            ['name' => 'Hidden Partner', 'enabled' => false],
            ['name' => 'Partner Two', 'website_url' => 'javascript:alert(1)', 'enabled' => true],
        ]]);
        $this->assertStringContainsString('Authorized Brands &amp; Partners', $html);
        $this->assertStringContainsString('src="https://example.com/logo.png"', $html);
        $this->assertStringContainsString('href="https://example.com" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('Hidden Partner', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('Partner One', $html);
        $this->assertLessThan(strpos($html, 'Partner Two'), strpos($html, 'Partner One'));
    }

    public function testEmptyOrDisabledManagedBrandsHideTheStrip(): void
    {
        $this->assertStringNotContainsString('id="partnerBrandsTitle"', $this->renderHome(['brands_source' => 'managed', 'brands_items' => []]));
        $this->assertStringNotContainsString('id="partnerBrandsTitle"', $this->renderHome(['brands' => false]));
        $this->assertStringContainsString('Sample Brand', $this->renderHome());
    }

    public function testLanguageSwitcherRendersNearCustomerLogin(): void
    {
        $html = $this->renderHome();
        $this->assertStringContainsString('cms-lang-switch', $html);
        $this->assertStringContainsString('cms-lang-btn', $html);
        $this->assertStringContainsString('ខ្មែរ', $html);
        $this->assertStringContainsString('EN', $html);
        $this->assertStringContainsString('lang=km', $html);
        $this->assertStringContainsString('lang=en', $html);
        $this->assertStringContainsString('Customer Login', $html);

        // Language toggle is inside nav-actions right alongside customer login button
        $navActionsPos = strpos($html, 'class="nav-actions"');
        $langSwitchPos = strpos($html, 'class="cms-lang-switch', $navActionsPos);
        $loginPos = strpos($html, route('loan-management.public.customer-login'), $navActionsPos);
        $this->assertNotFalse($navActionsPos);
        $this->assertNotFalse($langSwitchPos);
        $this->assertNotFalse($loginPos);
    }

    public function testKhmerLanguageRendersCompleteTranslations(): void
    {
        request()->merge(['lang' => 'km']);
        session(['user.language' => 'km']);
        app()->setLocale('km');

        $html = $this->renderHome();

        // Check HTML lang attribute
        $this->assertStringContainsString('lang="km"', $html);

        // Check header, menus, and announcements
        $this->assertStringContainsString('កាតាឡុកទំនិញ', $html);
        $this->assertStringContainsString('របៀបស្នើសុំ', $html);
        $this->assertStringContainsString('អំពីយើង', $html);
        $this->assertStringContainsString('ទំនាក់ទំនង', $html);

        // Check login modal and installment modal translations
        $this->assertStringContainsString('ចូលគណនីអតិថិជន', $html);
        $this->assertStringContainsString('ចុះឈ្មោះស្នើសុំបង់រំលស់', $html);
        $this->assertStringContainsString('ឈ្មោះពេញ', $html);
        $this->assertStringContainsString('លេខទូរស័ព្ទ', $html);
        $this->assertStringContainsString('អាសយដ្ឋានដឹកជញ្ជូន', $html);
        $this->assertStringContainsString('ឯកសារភ្ជាប់', $html);

        // Check catalog, cart, and actions
        $this->assertStringContainsString('ទំនិញទាំងអស់', $html);
        $this->assertStringContainsString('ដាក់ក្នុងកន្ត្រក', $html);
        $this->assertStringContainsString('កន្ត្រករំលស់', $html);
        $this->assertStringContainsString('ស្នើសុំរំលស់', $html);

        // Reset request locale
        request()->query->remove('lang');
        session()->forget('user.language');
        app()->setLocale('en');
    }

    public function testSampleFeaturesUseCmsContentAndVisibility(): void
    {
        $html = $this->renderHome(['experience_title' => 'Custom Experience', 'privacy_body' => 'Custom privacy content', 'assessment_title' => 'Custom Assessment']);
        foreach (['Custom Experience', 'Custom Assessment', 'Custom privacy content', 'id="customerLoginModal"', 'id="assessmentMonthly"', 'id="cartSubtotal"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $hidden = $this->renderHome(['experience' => false, 'assessment' => false, 'privacy' => false]);
        foreach (['id="experience"', 'id="assessmentProduct"', 'id="privacyModal"'] as $text) {
            $this->assertStringNotContainsString($text, $hidden);
        }
    }
}


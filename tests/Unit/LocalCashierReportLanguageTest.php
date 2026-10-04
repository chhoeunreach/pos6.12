<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Filesystem\Filesystem;
use Modules\LocalCashierReport\Support\ReportLanguage;
use PHPUnit\Framework\TestCase;

class LocalCashierReportLanguageTest extends TestCase
{
    private $previousApp;
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApp = Application::getInstance();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('config', new Repository(['app' => ['locale' => 'en']]));
        $loader = new FileLoader(new Filesystem(), $this->app->langPath());
        $loader->addNamespace('localcashierreport', $this->app->basePath('Modules/LocalCashierReport/Resources/lang'));
        $this->app->instance('translator', new Translator($loader, 'en'));
        $this->app->instance('request', Request::create('/local-cashier-report'));
    }

    protected function tearDown(): void
    {
        Application::setInstance($this->previousApp);
        parent::tearDown();
    }

    public function test_report_language_overrides_the_application_language(): void
    {
        $this->app['config']->set('app.locale', 'km');
        $this->app->instance('request', Request::create('/local-cashier-report?report_lang=en'));
        $this->assertSame('en', ReportLanguage::current());
        $this->assertSame('Cashier Report', ReportLanguage::text('local_cashier_report'));
    }

    public function test_khmer_user_language_is_used_without_a_report_override(): void
    {
        foreach (['km', 'kh'] as $locale) {
            $this->app['config']->set('app.locale', $locale);
            $this->assertSame('km', ReportLanguage::current());
            $this->assertSame('របាយការណ៍បេឡាករ', ReportLanguage::text('local_cashier_report'));
        }
    }

    public function test_invalid_report_language_falls_back_to_user_language(): void
    {
        $this->app['config']->set('app.locale', 'km');
        $this->app->instance('request', Request::create('/local-cashier-report?report_lang=invalid'));
        $this->assertSame('km', ReportLanguage::current());
    }

    public function test_builtin_payment_labels_translate_and_custom_names_are_preserved(): void
    {
        $this->assertSame('សាច់ប្រាក់', ReportLanguage::payment('cash', 'Cash', 'km'));
        $this->assertSame('អេប៊ីអេ', ReportLanguage::payment('custom_pay_2', 'ABA', 'km'));
        $this->assertSame('Monthly', ReportLanguage::payment('custom_pay_7', 'បង់ប្រចាំខែ', 'en'));
        $this->assertSame('My Payment Provider', ReportLanguage::payment('custom_pay_2', 'My Payment Provider', 'km'));
        $this->assertSame('Wing', ReportLanguage::payment('custom_pay_1', 'វីង (Wing)', 'en'));
        $this->assertSame('វីង', ReportLanguage::payment('custom_pay_1', 'វីង (Wing)', 'km'));
    }

    public function test_system_group_names_and_reminders_translate_without_changing_filter_values(): void
    {
        $this->assertSame('Sale', ReportLanguage::label('លក់', 'en'));
        $this->assertSame('Installment', ReportLanguage::label('រំលស់', 'en'));
        $this->assertSame('ការទូទាត់របស់អតិថិជន', ReportLanguage::label('Customer Payment', 'km'));
        $this->assertSame('ហួសកំណត់', ReportLanguage::label('Overdue', 'km'));
        $this->assertSame('Wholesale Customers', ReportLanguage::label('Wholesale Customers', 'km'));
    }
}

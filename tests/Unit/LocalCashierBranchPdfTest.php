<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;
use Modules\LocalCashierReport\Support\ReportLanguage;
use PHPUnit\Framework\TestCase;

class LocalCashierBranchPdfTest extends TestCase
{
    private $previousApp;
    private Application $app;
    private Factory $viewFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $basePath = dirname(__DIR__, 2);
        $this->previousApp = Application::getInstance();
        $this->app = new Application($basePath);
        $this->app->instance('path.storage', $basePath . DIRECTORY_SEPARATOR . 'storage');
        $this->app->instance('path.public', $basePath . DIRECTORY_SEPARATOR . 'public');
        $this->app->instance('config', new Repository([
            'app' => ['locale' => 'en'],
            'view' => ['compiled' => sys_get_temp_dir()],
        ]));

        $files = new Filesystem();
        $loader = new FileLoader($files, $basePath . '/resources/lang');
        $loader->addNamespace('localcashierreport', $basePath . '/Modules/LocalCashierReport/Resources/lang');
        $this->app->instance('translator', new Translator($loader, 'en'));
        $this->app->instance('request', Request::create('/local-cashier-report/pdf?report_lang=km'));

        $resolver = new EngineResolver();
        $bladeCompiler = new BladeCompiler($files, sys_get_temp_dir());
        $resolver->register('blade', fn () => new CompilerEngine($bladeCompiler, $files));
        $resolver->register('php', fn () => new PhpEngine($files));

        $finder = new FileViewFinder($files, [$basePath . '/resources/views']);
        $finder->addNamespace('localcashierreport', $basePath . '/Modules/LocalCashierReport/Resources/views');

        $this->viewFactory = new Factory($resolver, $finder, new Dispatcher($this->app));
        $this->app->instance('view', $this->viewFactory);

        $routes = new RouteCollection();
        $routes->add((new Route('GET', 'local-cashier-report', fn () => null))->name('local-cashier-report.index'));
        $routes->add((new Route('GET', 'local-cashier-report/print', fn () => null))->name('local-cashier-report.print'));
        $routes->add((new Route('GET', 'local-cashier-report/pdf', fn () => null))->name('local-cashier-report.pdf'));
        $routes->refreshNameLookups();
        $this->app->instance('url', new UrlGenerator($routes, $this->app['request']));
    }

    protected function tearDown(): void
    {
        Application::setInstance($this->previousApp);
        parent::tearDown();
    }

    public function test_branch_pdf_and_print_views_render_independent_branches_and_overall_summary_at_end(): void
    {
        $report = [
            'payment_columns' => ['cash', 'aba'],
            'payment_labels' => ['cash' => 'Cash', 'aba' => 'ABA'],
            'branch_dashboards' => [
                [
                    'branch_key' => 'kampong',
                    'branch_name' => 'កម្ពុជាក្រោម',
                    'location_ids' => [1, 5000001],
                    'location_labels' => ['កម្ពុជាក្រោម', 'កម្ពុជាក្រោម-Accessory (Accessory)'],
                    'rows' => [
                        [
                            'row_kind' => 'main',
                            'name' => 'លក់',
                            'sort' => 1,
                            'label' => 'កម្ពុជាក្រោម',
                            'qty_total' => 2,
                            'total' => 500.0,
                            'paid' => 450.0,
                            'due' => 50.0,
                            'payments' => ['cash' => 300.0, 'aba' => 150.0],
                        ],
                    ],
                    'total' => 500.0,
                    'paid' => 450.0,
                    'due' => 50.0,
                    'expenses' => 70.0,
                    'sell_return' => 0.0,
                    'actual_income' => 380.0,
                    'payments' => ['cash' => 300.0, 'aba' => 150.0],
                    'expense_payments' => ['cash' => 50.0, 'aba' => 20.0],
                    'actual_income_payments' => ['cash' => 250.0, 'aba' => 130.0],
                ],
                [
                    'branch_key' => 'smc',
                    'branch_name' => 'ស្ទឹងមានជ័យ',
                    'location_ids' => [2],
                    'location_labels' => ['ស្ទឹងមានជ័យ'],
                    'rows' => [
                        [
                            'row_kind' => 'main',
                            'name' => 'លក់',
                            'sort' => 1,
                            'label' => 'ស្ទឹងមានជ័យ',
                            'qty_total' => 1,
                            'total' => 200.0,
                            'paid' => 200.0,
                            'due' => 0.0,
                            'payments' => ['cash' => 200.0, 'aba' => 0.0],
                        ],
                    ],
                    'total' => 200.0,
                    'paid' => 200.0,
                    'due' => 0.0,
                    'expenses' => 10.0,
                    'sell_return' => 0.0,
                    'actual_income' => 190.0,
                    'payments' => ['cash' => 200.0, 'aba' => 0.0],
                    'expense_payments' => ['cash' => 10.0, 'aba' => 0.0],
                    'actual_income_payments' => ['cash' => 190.0, 'aba' => 0.0],
                ],
            ],
            'rows_by_location' => [],
            'rows' => [],
            'grand_total' => 700.0,
            'grand_paid' => 650.0,
            'grand_expenses' => 80.0,
            'grand_actual_income' => 570.0,
            'grand_due' => 50.0,
        ];

        $pdfHtml = $this->viewFactory->make('localcashierreport::pdf_branch', [
            'businessName' => 'Test Shop',
            'filters' => [
                'start_date' => '2026-11-26',
                'end_date' => '2026-11-26',
                'style_mode' => 'branch_dashboard',
                'location_ids' => [1, 2, 5000001],
            ],
            'selectedLocations' => ['កម្ពុជាក្រោម', 'ស្ទឹងមានជ័យ'],
            'currencySymbol' => '$',
            'staticPaymentColumns' => [],
            'report' => $report,
        ])->render();

        $branchOnePos = strpos($pdfHtml, '<div class="branch-title">កម្ពុជាក្រោម</div>');
        $branchTwoPos = strpos($pdfHtml, '<div class="branch-title">ស្ទឹងមានជ័យ</div>');
        $overallPos = strpos($pdfHtml, '<div class="overall-block">');

        $this->assertNotFalse($branchOnePos);
        $this->assertNotFalse($branchTwoPos);
        $this->assertNotFalse($overallPos);
        // Overall combined summary must appear at the very end after all branch blocks
        $this->assertGreaterThan($branchOnePos, $overallPos);
        $this->assertGreaterThan($branchTwoPos, $overallPos);

        $this->assertStringContainsString(ReportLanguage::text('overall_combined_summary', 'km'), $pdfHtml);
        $this->assertStringContainsString('$ 380.00', $pdfHtml);
        $this->assertStringContainsString('$ 190.00', $pdfHtml);
        $this->assertStringContainsString('$ 700.00', $pdfHtml);
        $this->assertStringContainsString('$ 650.00', $pdfHtml);
        $this->assertStringContainsString('$ 80.00', $pdfHtml);
        $this->assertStringContainsString('$ 570.00', $pdfHtml);

        $printHtml = $this->viewFactory->make('localcashierreport::print', [
            'businessName' => 'Test Shop',
            'filters' => [
                'start_date' => '2026-11-26',
                'end_date' => '2026-11-26',
                'style_mode' => 'branch_dashboard',
                'location_ids' => [1, 2, 5000001],
            ],
            'selectedLocations' => ['កម្ពុជាក្រោម', 'ស្ទឹងមានជ័យ'],
            'currencySymbol' => '$',
            'khmerFontFamily' => 'KhmerFont, sans-serif',
            'staticPaymentColumns' => [],
            'report' => $report,
        ])->render();

        $this->assertStringContainsString('កម្ពុជាក្រោម', $printHtml);
        $this->assertStringContainsString('ស្ទឹងមានជ័យ', $printHtml);
        $this->assertStringContainsString(ReportLanguage::text('overall_combined_summary', 'km'), $printHtml);
        $this->assertStringContainsString('$ 570.00', $printHtml);

        $this->assertTrue(method_exists(LocalCashierReportController::class, 'pdf'));
        $this->assertTrue(is_file($this->app->basePath('vendor/mpdf/mpdf/ttfonts/KhmerOS.ttf')));
    }
}

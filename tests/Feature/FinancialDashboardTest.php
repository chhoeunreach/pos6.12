<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\LocalCashierReport\Http\Controllers\FinancialDashboardController;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;
use Modules\LocalCashierReport\Services\FinancialDashboardReport;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialDashboardTest extends TestCase
{
    private $originalConnection;
    private $allowed = true;
    private $locations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config(['database.default' => 'finance_main', 'accessory.database_connection' => 'finance_accessory', 'service.database_connection' => 'finance_service']);
        foreach (['finance_main', 'finance_accessory', 'finance_service'] as $connection) {
            config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            $schema = Schema::connection($connection);
            $schema->create('transactions', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('business_id')->default(1);
                $table->integer('location_id')->default(1);
                $table->string('type')->default('sell');
                $table->string('status')->default('final');
                $table->string('transaction_date')->default('2026-11-01 12:00:00');
                $table->decimal('final_total')->default(0);
                $table->integer('expense_category_id')->nullable();
            });
            $schema->create('transaction_sell_lines', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('transaction_id');
                $table->integer('product_id')->default(1);
                $table->integer('variation_id')->default(1);
                $table->integer('lot_no_line_id')->nullable();
                $table->integer('parent_sell_line_id')->nullable();
                $table->string('children_type')->nullable();
                $table->decimal('quantity')->default(1);
                $table->decimal('quantity_returned')->default(0);
            });
            $schema->create('purchase_lines', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('transaction_id');
                $table->integer('variation_id')->default(1);
                $table->decimal('purchase_price_inc_tax')->nullable();
            });
            $schema->create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
                $table->integer('sell_line_id');
                $table->integer('purchase_line_id');
                $table->decimal('quantity');
            });
            $schema->create('products', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('business_id')->default(1);
                $table->boolean('enable_stock')->default(true);
            });
            $schema->create('variations', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('product_id')->default(1);
                $table->decimal('dpp_inc_tax')->nullable();
                $table->decimal('default_purchase_price')->nullable();
            });
            $schema->create('expense_categories', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('business_id')->default(1);
                $table->string('name');
                $table->integer('parent_id')->nullable();
            });
            $db = DB::connection($connection);
            $db->table('products')->insert(['id' => 1]);
            $db->table('variations')->insert(['id' => 1]);
            $db->table('expense_categories')->insert([
                ['id' => 1, 'name' => 'Office', 'parent_id' => null],
                ['id' => 2, 'name' => 'Supplies', 'parent_id' => 1],
            ]);
        }
        Schema::create('system', function (Blueprint $table) {
            $table->string('key');
            $table->string('value');
        });
        DB::table('system')->insert(['key' => 'localcashierreport_version', 'value' => '1.0.0']);
        $this->locations = collect([
            (object) ['id' => 1, 'name' => 'Branch A'],
            (object) ['id' => 2, 'name' => 'Branch B'],
            (object) ['id' => -2, 'name' => 'Branch A (Accessory)'],
            (object) ['id' => -3, 'name' => 'Branch A (Service)'],
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => $this->allowed && $permission === 'profit_loss_report.view');
        $this->actingAs($user);
        session()->put('user.business_id', 1);
        session()->put('business.name', 'Test business');
        session()->put('currency', ['symbol' => '$']);
    }

    protected function tearDown(): void
    {
        foreach (['finance_main', 'finance_accessory', 'finance_service'] as $connection) DB::purge($connection);
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function seedSales()
    {
        DB::table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 220, 'status' => 'final', 'transaction_date' => '2026-11-01 12:00:00', 'expense_category_id' => null],
            ['id' => 2, 'type' => 'sell_return', 'final_total' => 110, 'status' => 'final', 'transaction_date' => '2026-11-04 12:00:00', 'expense_category_id' => null],
            ['id' => 3, 'type' => 'expense', 'final_total' => 20, 'status' => 'final', 'transaction_date' => '2026-11-03 12:00:00', 'expense_category_id' => 2],
            ['id' => 4, 'type' => 'expense_refund', 'final_total' => 5, 'status' => 'final', 'transaction_date' => '2026-11-04 12:00:00', 'expense_category_id' => 2],
            ['id' => 10, 'type' => 'purchase', 'final_total' => 10000, 'status' => 'received', 'transaction_date' => '2026-10-01 12:00:00', 'expense_category_id' => null],
        ]);
        DB::table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'purchase_price_inc_tax' => 60]);
        DB::table('transaction_sell_lines')->insert([
            ['id' => 1, 'transaction_id' => 1, 'quantity' => 2, 'lot_no_line_id' => 1, 'parent_sell_line_id' => null],
            ['id' => 2, 'transaction_id' => 2, 'quantity' => 1, 'lot_no_line_id' => null, 'parent_sell_line_id' => 1],
        ]);
    }

    private function build(array $ids = [1], array $dates = [])
    {
        return (new FinancialDashboardReport)->build(1, $this->locations, $dates + [
            'location_ids' => $ids, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ]);
    }

    private function controllerRequest(array $data = [])
    {
        $request = Request::create('/reports/financial-dashboard', 'GET', $data);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $cashier->shouldReceive('getAccessibleLocations')->with(1)->andReturn($this->locations);

        return (new FinancialDashboardController($cashier, new FinancialDashboardReport))->index($request);
    }

    public function test_sales_returns_costs_expense_refunds_and_margins_use_their_own_dates()
    {
        $this->seedSales();
        $report = $this->build();
        $this->assertSame([], $report['unavailable']);
        $this->assertEquals(1, $report['total']['invoices']);
        $this->assertEquals(1, $report['total']['units']);
        foreach (['sales' => 220, 'returns' => 110, 'net_sales' => 110, 'cost' => 60, 'gross_profit' => 50, 'expenses' => 15, 'net_profit' => 35] as $key => $value) {
            $this->assertEquals($value, $report['total'][$key], $key);
        }
        $this->assertEqualsWithDelta(31.81818, $report['total']['margin'], 0.0001);
        $this->assertEquals(15, $report['expenses']['Office']['total']);
        $this->assertEquals(100, $report['expenses']['Office']['share']);
        $returnsOnly = $this->build([1], ['start_date' => '2026-11-04', 'end_date' => '2026-11-04']);
        $this->assertEquals(-110, $returnsOnly['total']['net_sales']);
        $this->assertEquals(-60, $returnsOnly['total']['cost']);
        $this->assertEquals(-45, $returnsOnly['total']['net_profit']);
    }

    public function test_all_three_sources_combine_by_branch_name_without_duplicate_invoice_totals()
    {
        $this->seedSales();
        $accessory = DB::connection('finance_accessory');
        $accessory->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 50, 'status' => 'final'],
            ['id' => 10, 'type' => 'purchase', 'final_total' => 999, 'status' => 'received'],
        ]);
        $accessory->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1]);
        $accessory->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'purchase_price_inc_tax' => 20]);
        $accessory->table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1]);
        $service = DB::connection('finance_service');
        $service->table('products')->update(['enable_stock' => false]);
        $service->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 40, 'expense_category_id' => null],
            ['id' => 2, 'type' => 'expense', 'final_total' => 3, 'expense_category_id' => 1],
        ]);
        $service->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1]);
        $report = $this->build([1, -2, -3]);
        $this->assertCount(1, $report['branches']);
        $this->assertEquals(3, $report['total']['invoices']);
        $this->assertEquals(3, $report['total']['units']);
        $this->assertSame('Branch A', $report['top_location']['name']);
        $this->assertEquals(200, $report['total']['net_sales']);
        $this->assertEquals(80, $report['total']['cost']);
        $this->assertEquals(18, $report['total']['expenses']);
        $this->assertEquals(102, $report['total']['net_profit']);
        $this->assertEquals(51, $report['total']['margin']);
        $this->assertEquals(18, $report['expenses']['Office']['total']);
    }

    public function test_location_suffixes_merge_into_one_branch_with_all_values_and_filter_ids()
    {
        $this->seedSales();
        $this->locations = collect([
            (object) ['id' => 1, 'name' => 'កម្ពុជាក្រោម'],
            (object) ['id' => -4, 'name' => 'កម្ពុជាក្រោម-Accessory (Accessory)'],
            (object) ['id' => -6, 'name' => 'កម្ពុជាក្រោម-Accessory-ស្តុកធំ (Accessory)'],
            (object) ['id' => 2, 'name' => 'កម្ពុជាក្រោម-Display'],
            (object) ['id' => 3, 'name' => 'កម្ពុជាក្រោម-ទុកសិន'],
            (object) ['id' => 4, 'name' => 'សាខាផ្សេង'],
        ]);
        DB::table('transactions')->insert(['id' => 20, 'location_id' => 2, 'final_total' => 500]);
        DB::table('transaction_sell_lines')->insert(['id' => 20, 'transaction_id' => 20, 'quantity' => 5, 'lot_no_line_id' => 1]);
        $accessory = DB::connection('finance_accessory');
        $accessory->table('transactions')->insert([
            ['id' => 1, 'location_id' => 2, 'type' => 'sell', 'status' => 'final', 'final_total' => 25],
            ['id' => 10, 'location_id' => 2, 'type' => 'purchase', 'status' => 'received', 'final_total' => 20],
        ]);
        $accessory->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'purchase_price_inc_tax' => 20]);
        $accessory->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'quantity' => 1, 'lot_no_line_id' => 1]);

        $report = $this->build([1, -4, -6, 2, 3]);
        $this->assertCount(1, $report['branches']);
        $branch = array_values($report['branches'])[0];
        $this->assertSame('កម្ពុជាក្រោម', $branch['name']);
        $this->assertSame([1, -4, -6, 2, 3], $branch['location_ids']);
        $this->assertEquals(7, $branch['units']);
        $this->assertEquals(635, $branch['net_sales']);
        $this->assertEquals(380, $branch['cost']);
        $this->assertEquals(240, $branch['net_profit']);
        $this->assertEquals(15, $report['expenses']['Office']['values']['កម្ពុជាក្រោម']);
        $this->assertSame('កម្ពុជាក្រោម', $report['top_location']['name']);

        $this->assertCount(2, $this->build([1, -4, -6, 2, 3, 4])['branches']);
        $singleLocation = array_values($this->build([1])['branches'])[0];
        $this->assertSame([1], $singleLocation['location_ids']);
        $this->assertEquals(110, $singleLocation['net_sales']);
    }

    public function test_inaccessible_locations_other_businesses_and_drafts_do_not_leak_into_totals()
    {
        $this->seedSales();
        DB::table('transactions')->insert([
            ['id' => 20, 'business_id' => 2, 'location_id' => 1, 'status' => 'final', 'final_total' => 999],
            ['id' => 21, 'business_id' => 1, 'location_id' => 2, 'status' => 'final', 'final_total' => 999],
            ['id' => 22, 'business_id' => 1, 'location_id' => 1, 'status' => 'draft', 'final_total' => 999],
            ['id' => 23, 'business_id' => 1, 'location_id' => 999, 'status' => 'final', 'final_total' => 999],
        ]);
        $this->assertEquals(220, $this->build([1, 999])['total']['sales']);
        $this->assertCount(0, $this->build([999])['branches']);
    }

    public function test_unknown_cost_is_not_reported_as_zero_profit_and_default_costs_are_identified_as_estimates()
    {
        DB::table('transactions')->insert(['id' => 1, 'final_total' => 100]);
        DB::table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1]);
        $report = $this->build();
        $this->assertEquals(100, $report['total']['net_sales']);
        $this->assertNull($report['total']['cost']);
        $this->assertNull($report['total']['net_profit']);
        $this->assertSame([], $report['expenses']);
        $this->assertEquals(1, $report['total']['missing_costs']);
        DB::table('variations')->update(['dpp_inc_tax' => 30]);
        $estimated = $this->build();
        $this->assertEquals(70, $estimated['total']['net_profit']);
        $this->assertEquals(1, $estimated['total']['estimated_costs']);
    }

    public function test_purchase_cost_fallback_does_not_use_future_purchases_or_other_businesses()
    {
        $this->seedSales();
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transactions')->insert([
            ['id' => 11, 'business_id' => 1, 'location_id' => 1, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-11-02 12:00:00'],
            ['id' => 12, 'business_id' => 2, 'location_id' => 1, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-10-02 12:00:00'],
        ]);
        DB::table('purchase_lines')->insert([
            ['id' => 2, 'transaction_id' => 11, 'purchase_price_inc_tax' => 999],
            ['id' => 3, 'transaction_id' => 12, 'purchase_price_inc_tax' => 999],
        ]);
        $report = $this->build();
        $this->assertEquals(60, $report['total']['cost']);
        $this->assertEquals(2, $report['total']['estimated_costs']);
    }

    public function test_sales_without_lines_and_returns_without_cost_lines_are_incomplete()
    {
        DB::table('transactions')->insert(['id' => 1, 'final_total' => 100]);
        $this->assertNull($this->build()['total']['net_profit']);
    }

    public function test_source_failure_marks_combined_results_unavailable()
    {
        $this->seedSales();
        Schema::connection('finance_accessory')->drop('transactions');
        $report = $this->build([1, -2]);
        $this->assertSame(['accessory'], $report['unavailable']);
        $this->assertNull($report['total']['sales']);
        $this->assertNull($report['total']['net_profit']);
        $this->assertSame([], $report['expenses']);
    }

    public function test_controller_does_not_fall_back_to_all_locations_for_an_empty_or_unauthorized_selection()
    {
        foreach ([['location_filter_applied' => 1], ['location_ids' => [999]]] as $selection) {
            $view = $this->controllerRequest($selection);
            $this->assertSame([], $view->getData()['filters']['location_ids']);
            $this->assertSame([], $view->getData()['report']['branches']);
        }
    }

    public function test_controller_requires_profit_report_permission()
    {
        $this->allowed = false;
        $this->expectException(HttpException::class);
        $this->controllerRequest();
    }

    public function test_category_inclusion_and_exclusion_recalculate_expenses_profit_and_shares_without_deleting_records()
    {
        $this->seedSales();
        DB::table('expense_categories')->insert(['id' => 3, 'name' => 'Travel']);
        DB::table('transactions')->insert(['id' => 30, 'type' => 'expense', 'final_total' => 30, 'expense_category_id' => 3]);
        $all = $this->build();
        $this->assertEquals(45, $all['total']['expenses']);
        $this->assertEquals(5, $all['total']['net_profit']);
        $included = $this->build([1], ['expense_filter_applied' => 1, 'expense_categories' => ['Office']]);
        $this->assertEquals(15, $included['total']['expenses']);
        $this->assertEquals(35, $included['total']['net_profit']);
        $this->assertEquals(100, $included['expenses']['Office']['share']);
        $this->assertSame(['Office', 'Travel'], $included['expense_options']);
        $this->assertTrue($included['expense_filter_active']);
        $excluded = $this->build([1], ['excluded_expense_categories' => ['Office']]);
        $this->assertEquals(30, $excluded['total']['expenses']);
        $this->assertEquals(20, $excluded['total']['net_profit']);
        $this->assertArrayNotHasKey('Office', $excluded['expenses']);
        $this->assertEquals(100, $excluded['expenses']['Travel']['share']);
        $this->assertEquals(45, $this->build()['total']['expenses']);
        $this->assertSame(6, DB::table('transactions')->count());
    }

    public function test_explicit_no_categories_means_no_expenses_while_default_all_tracks_available_categories()
    {
        $this->seedSales();
        $none = $this->build([1], ['expense_filter_applied' => 1, 'expense_categories' => []]);
        $this->assertEquals(0, $none['total']['expenses']);
        $this->assertEquals(50, $none['total']['net_profit']);
        $this->assertSame([], $none['expenses']);
        $this->assertTrue($none['expense_filter_active']);
        $view = $this->controllerRequest(['location_ids' => [1], 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'expense_filter_applied' => 0, 'expense_categories' => ['An old category']]);
        $this->assertEquals(15, $view->getData()['report']['total']['expenses']);
        $this->assertFalse($view->getData()['report']['expense_filter_active']);
    }

    public function test_source_filter_limits_sales_and_expenses_to_the_selected_source()
    {
        $this->seedSales();
        DB::connection('finance_accessory')->table('transactions')->insert(['id' => 1, 'type' => 'expense', 'final_total' => 5, 'expense_category_id' => 1]);
        $report = $this->build([1, -2], ['source' => 'accessory']);
        $this->assertEquals(0, $report['total']['sales']);
        $this->assertEquals(5, $report['total']['expenses']);
        $this->assertEquals(-5, $report['total']['net_profit']);
        $this->assertSame([-2], array_values($report['branches'])[0]['location_ids']);
    }

    public function test_filtered_csv_uses_the_same_expense_totals_and_declares_the_filter()
    {
        $this->seedSales();
        $response = $this->controllerRequest(['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'report_lang' => 'en', 'download' => 'csv', 'excluded_expense_categories' => ['Office']]);
        ob_start();
        $response->getCallback()();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Expense totals, net profit and expense shares', $csv);
        $this->assertStringContainsString('"Net profit",50', $csv);
        $this->assertStringNotContainsString('Office', $csv);
    }

    public function test_controller_validates_date_order()
    {
        $this->expectException(ValidationException::class);
        $this->controllerRequest(['start_date' => '2026-11-04', 'end_date' => '2026-11-01']);
    }

    public function test_csv_export_preserves_negative_numbers_and_protects_text_cells()
    {
        $this->seedSales();
        $this->locations = collect([(object) ['id' => 1, 'name' => '=2+2']]);
        $response = $this->controllerRequest(['start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'report_lang' => 'en', 'download' => 'csv']);
        ob_start();
        $response->getCallback()();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringContainsString('-110', $csv);
        $this->assertStringContainsString('Net profit', $csv);
        $this->assertStringContainsString('-45', $csv);
        $this->assertStringNotContainsString('Sales before returns', $csv);
        $this->assertStringNotContainsString('Sales returns', $csv);
    }

    public function test_top_location_uses_actual_units_instead_of_invoice_count()
    {
        $this->seedSales();
        DB::table('transactions')->insert(['id' => 20, 'location_id' => 2, 'final_total' => 500]);
        DB::table('transaction_sell_lines')->insert(['id' => 20, 'transaction_id' => 20, 'quantity' => 5]);
        $report = $this->build([1, 2]);
        $this->assertEquals(2, $report['total']['invoices']);
        $this->assertEquals(6, $report['total']['units']);
        $this->assertSame('Branch B', $report['top_location']['name']);
        $this->assertEquals(5, $report['top_location']['units']);
    }

    public function test_a_wrong_sku_allocation_does_not_replace_the_correct_historical_cost()
    {
        $this->seedSales();
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('purchase_lines')->insert(['id' => 20, 'transaction_id' => 10, 'variation_id' => 2, 'purchase_price_inc_tax' => 999]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 20, 'quantity' => 2]);
        $this->assertEquals(60, $this->build()['total']['cost']);
    }

    public function test_combo_component_lines_do_not_inflate_units_sold()
    {
        $this->seedSales();
        DB::table('transaction_sell_lines')->insert(['id' => 20, 'transaction_id' => 1, 'quantity' => 5, 'children_type' => 'combo']);
        $this->assertEquals(1, $this->build()['total']['units']);
    }

    public function test_dashboard_renders_four_kpis_the_combined_table_and_side_chart()
    {
        $this->seedSales();
        $view = $this->controllerRequest(['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'report_lang' => 'en', 'location_ids' => [1]]);
        $directory = sys_get_temp_dir().'/finance-dashboard-test-views';
        if (! is_dir($directory)) mkdir($directory);
        config(['view.compiled' => $directory]);
        \Illuminate\Support\Facades\Blade::swap(new \Illuminate\View\Compilers\BladeCompiler($this->app['files'], $directory));
        $this->app['view.engine.resolver']->forget('blade');
        $source = file_get_contents(base_path('Modules/LocalCashierReport/Resources/views/financial_dashboard.blade.php'));
        $source = preg_replace('/^@extends\([^\n]*\)\r?$/m', '', $source)."\n@yield('css')\n@yield('content')\n@yield('javascript')";
        $html = \Illuminate\Support\Facades\Blade::render($source, $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertSame(4, substr_count($html, 'class="finance-kpi '));
        $this->assertStringContainsString('Total units sold', $html);
        $this->assertStringContainsString('Top location', $html);
        $this->assertStringContainsString('Gross profit margin', $html);
        $this->assertStringContainsString('finance-combined-table', $html);
        $this->assertStringContainsString('finance-chart-panel', $html);
        $this->assertStringContainsString('Office', $html);
        $this->assertStringContainsString('$35.00', $html);
        $this->assertStringNotContainsString('Sales before returns', $html);
        $this->assertStringNotContainsString('Sales returns', $html);
        $this->assertStringContainsString('finance-filter-panel', $html);
        $this->assertStringContainsString('finance-date-range', $html);
        $this->assertStringContainsString('finance-chart-total', $html);
        $this->assertStringContainsString('finance-open-category-filter', $html);
        $this->assertStringContainsString('finance-remove-category', $html);
        $this->assertStringContainsString('excluded_expense_categories', $html);
        $this->assertStringContainsString('source=main', $html);
        $this->assertStringContainsString('expense_categories%5B0%5D=Office', $html);
        $this->assertStringContainsString('print=1', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        request()->merge(['report_lang' => 'km']);
        $khmer = \Illuminate\Support\Facades\Blade::render($source, $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('ចំណូលសរុប', $khmer);
        $this->assertStringNotContainsString('ការលក់មុនដកទំនិញត្រឡប់', $khmer);
        $this->assertStringNotContainsString('ទំនិញលក់ត្រឡប់</th>', $khmer);
    }

    public function test_print_preview_is_standalone_and_retains_report_filters_and_totals()
    {
        $this->seedSales();
        $result = $this->controllerRequest([
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'report_lang' => 'en',
            'location_ids' => [1], 'excluded_expense_categories' => ['Office'], 'print' => 1,
        ]);
        $data = $result->getData();
        $this->assertTrue($data['printMode']);
        $this->assertEquals(0, $data['report']['total']['expenses']);
        $this->assertEquals(50, $data['report']['total']['net_profit']);
        $directory = sys_get_temp_dir().'/finance-dashboard-test-views';
        if (! is_dir($directory)) mkdir($directory);
        config(['view.compiled' => $directory]);
        \Illuminate\Support\Facades\Blade::swap(new \Illuminate\View\Compilers\BladeCompiler($this->app['files'], $directory));
        $this->app['view.engine.resolver']->forget('blade');
        $html = view('localcashierreport::financial_dashboard', $data + ['errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('finance-print-preview', $html);
        $this->assertStringContainsString('@page{size:auto', $html);
        $this->assertStringContainsString('width:297mm', $html);
        $this->assertStringContainsString('2026-11-01 — 2026-11-30', $html);
        $this->assertStringContainsString('excluded_expense_categories%5B0%5D=Office', $html);
        $this->assertStringContainsString('$50.00', $html);
        $this->assertStringNotContainsString('/js/financial-dashboard.js', $html);
        $this->assertStringNotContainsString('id="main_app_header"', $html);
        $portrait = $this->controllerRequest([
            'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'report_lang' => 'en',
            'location_ids' => [1], 'excluded_expense_categories' => ['Office'], 'print' => 1, 'orientation' => 'portrait',
        ])->getData();
        $this->assertSame('portrait', $portrait['printOrientation']);
        $portraitHtml = view('localcashierreport::financial_dashboard', $portrait + ['errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('width:210mm', $portraitHtml);
        $this->assertStringNotContainsString('size:A4', $portraitHtml);
        $this->assertStringContainsString('name="orientation"', $portraitHtml);
        $this->assertStringContainsString('name="excluded_expense_categories[]" value="Office"', $portraitHtml);
        $this->assertStringContainsString('repeat(4,minmax(0,1fr))!important', $portraitHtml);
    }

    public function test_real_other_category_and_remaining_categories_have_distinct_chart_labels_and_filters()
    {
        $this->seedSales();
        DB::table('expense_categories')->insert(['id' => 3, 'name' => 'ផ្សេងៗ']);
        DB::table('transactions')->insert(['id' => 30, 'type' => 'expense', 'final_total' => 1000, 'expense_category_id' => 3]);
        for ($i = 1; $i <= 7; $i++) {
            DB::table('expense_categories')->insert(['id' => $i + 3, 'name' => 'Category '.$i]);
            DB::table('transactions')->insert(['id' => 30 + $i, 'type' => 'expense', 'final_total' => $i, 'expense_category_id' => $i + 3]);
        }
        $data = $this->controllerRequest(['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'report_lang' => 'km', 'location_ids' => [1]])->getData();
        $this->assertEquals(1043, $data['report']['total']['expenses']);
        $directory = sys_get_temp_dir().'/finance-dashboard-test-views';
        if (! is_dir($directory)) mkdir($directory);
        config(['view.compiled' => $directory]);
        \Illuminate\Support\Facades\Blade::swap(new \Illuminate\View\Compilers\BladeCompiler($this->app['files'], $directory));
        $this->app['view.engine.resolver']->forget('blade');
        $source = file_get_contents(base_path('Modules/LocalCashierReport/Resources/views/financial_dashboard.blade.php'));
        $source = preg_replace('/^@extends\([^\n]*\)\r?$/m', '', $source)."\n@yield('content')";
        $html = \Illuminate\Support\Facades\Blade::render($source, $data + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " finance-bar-row ")]');
        $filtersByLabel = [];
        foreach ($links as $link) {
            $label = trim($xpath->query('.//div[contains(@class, "finance-bar-label")]/span', $link)->item(0)->textContent);
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $filtersByLabel[$label] = $query['expense_categories'];
        }
        $this->assertCount(9, $filtersByLabel);
        $this->assertSame(['ផ្សេងៗ'], $filtersByLabel['ផ្សេងៗ']);
        $this->assertSame(['Category 1'], $filtersByLabel['ប្រភេទចំណាយដែលនៅសល់ (1)']);
    }
}

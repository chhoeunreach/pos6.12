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
    private $salesPermissions = [];
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
                $table->string('invoice_no')->nullable();
                $table->integer('contact_id')->nullable();
                $table->integer('created_by')->default(1);
                $table->boolean('is_direct_sale')->default(false);
                $table->string('ref_no')->nullable();
                $table->string('sub_type')->nullable();
                $table->integer('commission_agent')->nullable();
                $table->decimal('total_before_tax', 22, 4)->default(0);
                $table->string('discount_type')->nullable();
                $table->decimal('discount_amount', 22, 4)->default(0);
                $table->integer('tax_id')->nullable();
                $table->decimal('tax_amount', 22, 4)->default(0);
                $table->decimal('shipping_charges', 22, 4)->default(0);
                $table->string('payment_status')->default('due');
                $table->timestamp('updated_at')->nullable();
            });
            $schema->create('transaction_sell_lines', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('transaction_id');
                $table->integer('product_id')->default(1);
                $table->integer('variation_id')->default(1);
                $table->integer('lot_no_line_id')->nullable();
                $table->integer('parent_sell_line_id')->nullable();
                $table->string('children_type')->nullable();
                $table->decimal('unit_price_inc_tax')->nullable();
                $table->decimal('quantity')->default(1);
                $table->decimal('quantity_returned')->default(0);
            });
            $schema->create('purchase_lines', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('transaction_id');
                $table->integer('variation_id')->default(1);
                $table->decimal('purchase_price_inc_tax')->nullable();
                $table->string('lot_number')->nullable();
                $table->decimal('quantity', 22, 4)->default(1);
                $table->decimal('purchase_price', 22, 4)->default(0);
                $table->decimal('pp_without_discount', 22, 4)->default(0);
                $table->decimal('item_tax', 22, 4)->default(0);
                $table->decimal('discount_percent', 22, 4)->default(0);
                $table->integer('tax_id')->nullable();
                $table->timestamp('updated_at')->nullable();
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
                $table->string('name')->default('Test product');
                $table->string('sku')->default('SKU-1');
            });
            $schema->create('variations', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('product_id')->default(1);
                $table->string('name')->default('DUMMY');
                $table->string('sub_sku')->nullable();
                $table->decimal('dpp_inc_tax')->nullable();
                $table->decimal('default_purchase_price')->nullable();
            });
            $schema->create('transaction_payments', function (Blueprint $table) { $table->integer('transaction_id'); $table->decimal('amount', 22, 4); $table->boolean('is_return')->default(false); });
            $schema->create('activity_log', function (Blueprint $table) {
                $table->increments('id'); $table->string('log_name')->nullable(); $table->string('description');
                $table->string('subject_type')->nullable(); $table->integer('subject_id')->nullable(); $table->integer('business_id')->nullable();
                $table->string('causer_type')->nullable(); $table->integer('causer_id')->nullable(); $table->text('properties')->nullable(); $table->timestamps();
            });
            $schema->create('contacts', function (Blueprint $table) { $table->integer('id'); $table->string('name'); });
            $schema->create('business_locations', function (Blueprint $table) { $table->integer('id'); $table->integer('business_id')->default(1); $table->string('name'); });
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
        $user->shouldReceive('permitted_locations')->andReturn([1]);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($permissions) => $this->allowed && count(array_intersect($permissions, $this->salesPermissions)) > 0);
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => $this->allowed && ($permission === 'profit_loss_report.view' || in_array($permission, $this->salesPermissions, true)));
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

    private function salesRequest(array $data = [], bool $costs = false, bool $revenues = false)
    {
        $request = Request::create('/reports/financial-dashboard/sales', 'GET', $data + ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'location_ids' => [1]]);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $cashier->shouldReceive('getAccessibleLocations')->with(1)->andReturn($this->locations);
        $dashboard = new FinancialDashboardController($cashier, new FinancialDashboardReport);

        $controller = new \Modules\LocalCashierReport\Http\Controllers\FinancialSalesController($dashboard);
        return ($revenues ? $controller->revenues($request, new FinancialDashboardReport) : ($costs ? $controller->costs($request, new FinancialDashboardReport) : $controller->index($request)))->getData(true);
    }

    public function test_accessory_and_service_revenue_details_match_net_sales_and_keep_sources_separate()
    {
        $this->salesPermissions = ['sell.view'];
        $accessory = DB::connection('finance_accessory');
        $accessory->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 999, 'status' => 'final'],
            ['id' => 2, 'type' => 'sell_return', 'final_total' => 999, 'status' => 'final'],
            ['id' => 10, 'type' => 'purchase', 'final_total' => 0, 'status' => 'received'],
        ]);
        $accessory->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'purchase_price_inc_tax' => 20]);
        $accessory->table('transaction_sell_lines')->insert([
            ['id' => 1, 'transaction_id' => 1, 'quantity' => 2, 'unit_price_inc_tax' => 50, 'lot_no_line_id' => 1, 'parent_sell_line_id' => null],
            ['id' => 2, 'transaction_id' => 2, 'quantity' => 1, 'unit_price_inc_tax' => 50, 'lot_no_line_id' => null, 'parent_sell_line_id' => 1],
        ]);
        $service = DB::connection('finance_service');
        $service->table('products')->update(['enable_stock' => false]);
        $service->table('transactions')->insert(['id' => 1, 'type' => 'sell', 'final_total' => 999]);
        $service->table('transaction_sell_lines')->insert([
            ['id' => 1, 'transaction_id' => 1, 'quantity' => 3, 'unit_price_inc_tax' => 30, 'children_type' => null],
            ['id' => 2, 'transaction_id' => 1, 'quantity' => 1, 'unit_price_inc_tax' => 30, 'children_type' => 'combo'],
        ]);
        $totals = $this->build([1, -2, -3])['total'];
        $accessoryRows = $this->salesRequest(['source' => 'accessory', 'location_ids' => [-2, -3]], false, true);
        $this->assertEquals(50, $accessoryRows['amount']);
        $this->assertEquals($totals['accessory_sales'], $accessoryRows['amount']);
        $this->assertEquals(2, $accessoryRows['total']);
        $this->assertSame(['accessory'], array_values(array_unique(array_column($accessoryRows['rows'], 'source'))));
        $accessory->table('purchase_lines')->update(['purchase_price_inc_tax' => 500]);
        $this->assertEquals(50, $this->salesRequest(['source' => 'accessory', 'location_ids' => [-2]], false, true)['amount']);
        $serviceRows = $this->salesRequest(['source' => 'service', 'location_ids' => [-2, -3]], false, true);
        $this->assertEquals(90, $serviceRows['amount']);
        $this->assertEquals($totals['service_sales'], $serviceRows['amount']);
        $this->assertEquals(1, $serviceRows['total']);
        $this->salesPermissions = ['view_own_sell_only'];
        try { $this->salesRequest(['source' => 'accessory', 'location_ids' => [-2]], false, true); $this->fail('Independent source user IDs must not share own-sale access'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    private function lotHistoryRequest(string $source, string $lot)
    {
        $request = Request::create('/reports/financial-dashboard/lot-history/'.$source.'/1/'.$lot, 'GET', ['source' => $source, 'report_lang' => 'en']);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $cashier->shouldReceive('getAccessibleLocations')->with(1)->andReturn($this->locations);
        $dashboard = new FinancialDashboardController($cashier, new FinancialDashboardReport);

        return (new \Modules\LocalCashierReport\Http\Controllers\FinancialLotHistoryController($dashboard))->show($request, $source, 1, $lot);
    }

    public function test_lot_links_and_history_keep_main_accessory_and_service_records_separate()
    {
        $this->salesPermissions = ['sell.view', 'stock_inventory.view'];
        foreach (['main' => 10, 'accessory' => 20, 'service' => 30] as $source => $price) {
            $db = DB::connection('finance_'.$source);
            $db->table('transactions')->insert(['id' => 10, 'type' => 'purchase', 'status' => 'received', 'ref_no' => $source.'-PURCHASE']);
            $db->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'lot_number' => 'SHARED-LOT', 'purchase_price_inc_tax' => $price]);
            $db->table('transactions')->insert(['id' => 1, 'type' => 'sell', 'invoice_no' => $source.'-SALE']);
            $db->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'lot_no_line_id' => 1, 'unit_price_inc_tax' => $price + 50]);
            $db->table('transactions')->insert(['id' => 11, 'type' => 'purchase', 'status' => 'received', 'location_id' => 99, 'transaction_date' => '2000-01-01', 'ref_no' => 'HIDDEN']);
            $db->table('purchase_lines')->insert(['id' => 2, 'transaction_id' => 11, 'lot_number' => 'SHARED-LOT', 'purchase_price_inc_tax' => 999]);
            $view = $this->lotHistoryRequest($source, 'SHARED-LOT');
            $history = $view->getData();
            $this->assertSame($source, $history['sourceName']);
            $this->assertEquals(2, $history['movements']->count());
            $this->assertSame([$source.'-SALE', $source.'-PURCHASE'], $history['movements']->pluck('reference')->all());
            foreach ($history['movements'] as $movement) $this->assertEquals($movement->type === 'sell' ? $price + 50 : $price, $movement->unit_price);
            $details = $this->salesRequest(['source' => $source, 'location_ids' => [1, -2, -3]], true);
            $this->assertStringContainsString($source === 'main' ? '/smart-stock-inventory/lot/history/SHARED-LOT' : '/lot-history/'.$source.'/1/SHARED-LOT', $details['rows'][0]['lot_history_links'][0]['url']);
            $html = $view->render();
            $this->assertStringContainsString($source.'-PURCHASE', $html);
            foreach (['purchase_price', 'sell_price', 'transfer_price', 'return_price', 'adjustment_price'] as $column) {
                $this->assertStringContainsString(\Modules\LocalCashierReport\Support\ReportLanguage::text('finance_lot_'.$column), $html);
            }
        }
        $this->salesPermissions = ['sell.view'];
        $this->assertSame([], $this->salesRequest([], true)['rows'][0]['lot_history_links']);
        try { $this->lotHistoryRequest('main', 'SHARED-LOT'); $this->fail('History permission is required'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_dashboard_date_range_defaults_to_today_and_preserves_selected_dates()
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-11-07 14:00:00'));
        try {
            $filters = $this->controllerRequest()->getData()['filters'];
            $this->assertSame('2026-11-07', $filters['start_date']);
            $this->assertSame('2026-11-07', $filters['end_date']);
            $selected = $this->controllerRequest(['start_date' => '2026-11-01', 'end_date' => '2026-11-04'])->getData()['filters'];
            $this->assertSame('2026-11-01', $selected['start_date']);
            $this->assertSame('2026-11-04', $selected['end_date']);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_default_locations_exclude_the_configured_names_but_allow_manual_selection()
    {
        $names = config('localcashierreport.financial_dashboard_default_excluded_locations');
        $this->assertCount(16, $names);
        $excludedIds = [];
        foreach ($names as $index => $name) {
            $excludedIds[] = $id = 100 + $index;
            // Match labels even with case, spacing and invisible Khmer formatting.
            $label = ' '.str_replace('-', " -\u{200B}\u{200B} ", $name).' ';
            $this->locations->push((object) ['id' => $id, 'name' => mb_strtoupper($label)]);
        }
        $this->locations->push((object) ['id' => 200, 'name' => 'Assets Shop']);

        $default = $this->controllerRequest()->getData();
        $this->assertSame([1, 2, -2, -3, 200], $default['filters']['location_ids']);
        $this->assertCount(21, $default['locations']);
        $manual = $this->controllerRequest(['location_filter_applied' => 1, 'location_ids' => $excludedIds])->getData();
        $this->assertSame($excludedIds, $manual['filters']['location_ids']);
    }

    public function test_sales_modal_matches_pos_units_and_filters_locations_dates_and_sources()
    {
        $this->salesPermissions = ['sell.view'];
        $this->seedSales();
        DB::table('transactions')->insert([
            ['id' => 20, 'business_id' => 2, 'location_id' => 1, 'status' => 'final'],
            ['id' => 21, 'business_id' => 1, 'location_id' => 2, 'status' => 'final'],
            ['id' => 22, 'business_id' => 1, 'location_id' => 1, 'status' => 'draft'],
        ]);
        foreach ([20, 21, 22] as $id) DB::table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => $id, 'quantity' => 100]);
        DB::table('transaction_sell_lines')->insert(['id' => 30, 'transaction_id' => 1, 'quantity' => 100, 'children_type' => 'combo']);
        $result = $this->salesRequest(['location_ids' => [1, -2, -3, 999]]);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals($this->build()['total']['units'], $result['units']);
        $this->assertEquals(-1, $result['rows'][0]['units']);
        $this->assertEquals(-110, $result['rows'][0]['amount']);
        $this->assertEquals(-1, $this->salesRequest(['start_date' => '2026-11-04'])['units']);
        $this->assertEquals(0, $this->salesRequest(['source' => 'accessory'])['total']);
        $this->assertEquals(0, $this->salesRequest(['location_ids' => [999]])['total']);
    }

    public function test_sales_modal_requires_sales_permission()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        try { $this->salesRequest(); } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_sales_modal_limits_own_sales_and_paginates()
    {
        $this->salesPermissions = ['view_own_sell_only'];
        for ($id = 1; $id <= 27; $id++) {
            DB::table('transactions')->insert(['id' => $id, 'created_by' => $id === 27 ? 2 : 1]);
            DB::table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => $id]);
        }
        $first = $this->salesRequest();
        $this->assertEquals(26, $first['total']);
        $this->assertEquals(26, $first['units']);
        $this->assertCount(25, $first['rows']);
        $second = $this->salesRequest(['page' => 2]);
        $this->assertCount(1, $second['rows']);
        $this->assertEquals(1, $second['rows'][0]['id']);
        $custom = $this->salesRequest(['per_page' => 10, 'page' => 2]);
        $this->assertCount(10, $custom['rows']);
        $this->assertEquals(3, $custom['last_page']);
        $this->assertEquals(16, $custom['rows'][0]['id']);
        $all = $this->salesRequest(['per_page' => -1, 'page' => 2]);
        $this->assertCount(26, $all['rows']);
        $this->assertEquals(1, $all['last_page']);
        $this->assertEquals(1, $all['page']);
        $this->assertEquals(-1, $all['per_page']);
    }

    public function test_sales_modal_edit_actions_use_each_sales_form_and_respect_permissions()
    {
        $this->salesPermissions = ['sell.view'];
        DB::table('transactions')->insert([
            ['id' => 1, 'is_direct_sale' => false, 'type' => 'sell'],
            ['id' => 2, 'is_direct_sale' => true, 'type' => 'sell'],
            ['id' => 3, 'is_direct_sale' => false, 'type' => 'sell_return'],
        ]);
        foreach ([1, 2, 3] as $id) DB::table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => $id, 'quantity_returned' => 1]);
        foreach ($this->salesRequest()['rows'] as $row) $this->assertNull($row['edit_url']);
        $this->salesPermissions = ['sell.view', 'sell.update', 'direct_sell.update', 'access_sell_return'];
        $rows = collect($this->salesRequest()['rows'])->keyBy('id');
        $this->assertEquals(route('pos.edit', 1), $rows[1]['edit_url']);
        $this->assertEquals(route('sells.edit', 2), $rows[2]['edit_url']);
        $this->assertEquals(route('sell-return.edit', 3), $rows[3]['edit_url']);
        $this->salesPermissions = ['sell.view', 'sell.update'];
        $rows = collect($this->salesRequest()['rows'])->keyBy('id');
        $this->assertNotNull($rows[1]['edit_url']);
        $this->assertNull($rows[2]['edit_url']);
        $this->assertNull($rows[3]['edit_url']);
    }

    public function test_sales_modal_sorts_numeric_columns_across_pages_and_rejects_unknown_columns()
    {
        $this->salesPermissions = ['sell.view'];
        for ($id = 1; $id <= 12; $id++) {
            DB::table('transactions')->insert(['id' => $id, 'final_total' => $id * 10, 'invoice_no' => sprintf('INV-%02d', 13 - $id)]);
            DB::table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => $id, 'quantity' => $id]);
        }
        $first = $this->salesRequest(['sort' => 'amount', 'direction' => 'asc', 'per_page' => 10]);
        $second = $this->salesRequest(['sort' => 'amount', 'direction' => 'asc', 'per_page' => 10, 'page' => 2]);
        $this->assertEquals(range(1, 10), array_column($first['rows'], 'id'));
        $this->assertEquals([11, 12], array_column($second['rows'], 'id'));
        $descending = $this->salesRequest(['sort' => 'units', 'direction' => 'desc', 'per_page' => -1]);
        $this->assertEquals(range(12, 1), array_column($descending['rows'], 'id'));
        $invoices = $this->salesRequest(['sort' => 'invoice', 'direction' => 'asc', 'per_page' => -1]);
        $this->assertEquals(range(12, 1), array_column($invoices['rows'], 'id'));
        $this->expectException(ValidationException::class);
        $this->salesRequest(['sort' => 'sale.created_by']);
    }

    public function test_cost_sales_item_columns_use_saved_selling_prices_and_historical_lot_costs()
    {
        $this->salesPermissions = ['sell.view'];
        $this->seedSales();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['unit_price_inc_tax' => 110]);
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => 'PHONE-LOT']);
        DB::table('products')->where('id', 1)->update(['name' => 'Laptop', 'sku' => 'BASE-SKU']);
        DB::table('variations')->where('id', 1)->update(['sub_sku' => 'LAPTOP-001', 'name' => '16GB']);
        $details = $this->salesRequest(['per_page' => -1], true);
        $sale = collect($details['rows'])->firstWhere('transaction_id', 1);
        $returned = collect($details['rows'])->firstWhere('transaction_id', 2);
        $this->assertSame('LAPTOP-001', $sale['sku']);
        $this->assertSame('PHONE-LOT', $sale['lots']);
        $this->assertSame('Laptop', $sale['product']);
        $this->assertEquals(2, $sale['units']);
        $this->assertEquals(60, $sale['purchase_price']);
        $this->assertEquals(110, $sale['selling_price']);
        $this->assertEquals(120, $sale['amount']);
        $this->assertEquals(220, $sale['selling_total']);
        $this->assertEquals(100, $sale['profit_loss']);
        $this->assertEquals(-1, $returned['units']);
        $this->assertEquals(-60, $returned['amount']);
        $this->assertEquals(-50, $returned['profit_loss']);
        $this->assertEquals($this->build()['total']['cost'], $details['amount']);
        $accessory = DB::connection('finance_accessory');
        $accessory->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'status' => 'final'], ['id' => 2, 'type' => 'purchase', 'status' => 'received'],
        ]);
        $accessory->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'quantity' => 2, 'unit_price_inc_tax' => 50]);
        $accessory->table('purchase_lines')->insert([
            ['id' => 1, 'transaction_id' => 2, 'purchase_price_inc_tax' => 20, 'lot_number' => 'A'],
            ['id' => 2, 'transaction_id' => 2, 'purchase_price_inc_tax' => 30, 'lot_number' => 'B'],
        ]);
        $accessory->table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1], ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 1],
        ]);
        $allocated = $this->salesRequest(['location_ids' => [-2]], true)['rows'][0];
        $this->assertEquals(25, $allocated['purchase_price']);
        $this->assertEquals(50, $allocated['amount']);
        $this->assertEquals(50, $allocated['profit_loss']);
        $this->assertEqualsCanonicalizing(['A', 'B'], explode(',', $allocated['lots']));
        $sorted = $this->salesRequest(['location_ids' => [1, -2], 'sort' => 'purchase_price', 'direction' => 'asc', 'per_page' => -1], true);
        $this->assertEquals([25, 60, 60], array_column($sorted['rows'], 'purchase_price'));
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $lotEditor = $this->purchaseCostRequest([], false, 'accessory');
        $this->assertCount(2, $lotEditor['lots']);
        $this->purchaseCostRequest(['cost_version' => $lotEditor['cost_version'], 'prices' => [
            ['id' => 1, 'purchase_price' => 24], ['id' => 2, 'purchase_price' => 36],
        ]], true, 'accessory');
        $this->assertEquals(60, $this->salesRequest(['location_ids' => [-2]], true)['amount']);
        $this->assertEquals(60, DB::table('purchase_lines')->where('id', 1)->value('purchase_price_inc_tax'));
        $this->assertEquals(1, $accessory->table('activity_log')->count());
        $this->assertEquals(0, DB::table('activity_log')->count());

        $searched = $this->salesRequest(['q' => 'laptop-001', 'per_page' => 10], true);
        $this->assertEquals(2, $searched['total']);
        $this->assertEquals(60, $searched['amount']);
        $this->assertEquals(50, $searched['profit_loss']);
        $this->assertEquals(0, $searched['estimated_count']);
        $this->assertEquals(0, $searched['missing_count']);
        $this->assertEquals(0, $this->salesRequest(['q' => 'not-a-real-sku'], true)['total']);


    }

    public function test_cost_sales_details_handle_missing_costs_sorting_pagination_and_access()
    {
        $this->salesPermissions = ['sell.view'];
        DB::table('variations')->update(['dpp_inc_tax' => 10]);
        for ($id = 1; $id <= 12; $id++) {
            DB::table('transactions')->insert(['id' => $id]);
            DB::table('transaction_sell_lines')->insert(['id' => $id, 'transaction_id' => $id, 'quantity' => $id]);
        }
        $details = $this->salesRequest(['per_page' => 10, 'sort' => 'amount', 'direction' => 'asc', 'page' => 2], true);
        $this->assertEquals([110, 120], array_column($details['rows'], 'amount'));
        $this->assertEquals(780, $details['amount']);
        $this->assertEquals(2, $details['last_page']);
        $all = $this->salesRequest(['per_page' => -1], true);
        $this->assertCount(12, $all['rows']);
        $this->assertGreaterThan(0, $all['rows'][0]['estimated']);
        DB::table('variations')->update(['dpp_inc_tax' => null]);
        $missing = $this->salesRequest([], true);
        $this->assertNull($missing['amount']);
        $this->assertNull($missing['rows'][0]['amount']);
        DB::table('transaction_sell_lines')->delete();
        $missingLines = $this->salesRequest([], true);
        $this->assertEquals(0, $missingLines['total']);
        $this->assertEquals(0, $missingLines['amount']);
        $this->salesPermissions = [];
        try { $this->salesRequest([], true); $this->fail('Sales access is required'); } catch (HttpException $e) { $this->assertEquals(403, $e->getStatusCode()); }
    }

    public function test_sku_purchase_history_sorts_numeric_values_across_pages_and_preserves_sort_links()
    {
        $this->salesPermissions = ['purchase.view'];
        DB::table('business_locations')->insert(['id' => 1, 'name' => 'Branch A']);
        for ($id = 1; $id <= 61; $id++) {
            DB::table('transactions')->insert(['id' => $id, 'type' => 'purchase', 'status' => 'received', 'ref_no' => 'PUR-'.$id]);
            DB::table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id, 'lot_number' => 'LOT-'.$id,
                'quantity' => 61 - $id, 'purchase_price_inc_tax' => $id === 61 ? null : $id]);
        }
        $first = $this->skuPurchaseHistoryRequest('main', ['sort' => 'purchase_price', 'direction' => 'asc'])->getData()['rows'];
        $this->assertEquals(range(1, 50), $first->pluck('id')->all());
        $view = $this->skuPurchaseHistoryRequest('main', ['sort' => 'purchase_price', 'direction' => 'asc', 'page' => 2, 'report_lang' => 'en']);
        $second = $view->getData()['rows'];
        $this->assertEquals(range(51, 61), $second->pluck('id')->all());
        $this->assertStringContainsString('sort=purchase_price', $second->previousPageUrl());
        $this->assertStringContainsString('direction=asc', $second->previousPageUrl());
        $html = $view->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[@class="purchase-history-sort"]');
        $this->assertSame(6, $links->length);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('1', $query['page']);
            $this->assertSame('en', $query['report_lang']);
        }
        parse_str(parse_url($links->item(5)->getAttribute('href'), PHP_URL_QUERY), $priceLink);
        $this->assertSame('purchase_price', $priceLink['sort']);
        $this->assertSame('desc', $priceLink['direction']);
        $this->assertSame('ascending', $links->item(5)->parentNode->getAttribute('aria-sort'));
        $descending = $this->skuPurchaseHistoryRequest('main', ['sort' => 'purchase_price', 'direction' => 'desc'])->getData()['rows'];
        $this->assertEquals(range(60, 11), $descending->pluck('id')->all());
        $quantities = $this->skuPurchaseHistoryRequest('main', ['sort' => 'quantity', 'direction' => 'asc'])->getData()['rows'];
        $this->assertEquals(range(61, 12), $quantities->pluck('id')->all());
        foreach (['date', 'reference', 'location', 'lot'] as $sort) {
            $rows = $this->skuPurchaseHistoryRequest('main', ['sort' => $sort, 'direction' => 'asc'])->getData()['rows'];
            $this->assertEquals(61, $rows->total());
        }
    }

    public function test_sku_purchase_history_rejects_unknown_sort_columns_and_directions()
    {
        $this->salesPermissions = ['purchase.view'];
        foreach ([['sort' => 't.id desc'], ['direction' => 'invalid']] as $query) {
            try { $this->skuPurchaseHistoryRequest('main', $query); $this->fail('Invalid sorting must be rejected'); }
            catch (ValidationException $e) { $this->assertArrayHasKey(array_key_first($query), $e->errors()); }
        }
    }


    private function skuPurchaseHistoryRequest(string $source, array $data = [], int $variation = 1)
    {
        $request = Request::create('/reports/financial-dashboard/purchase-price-history/'.$source.'/'.$variation, 'GET', $data);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return (new \Modules\LocalCashierReport\Http\Controllers\FinancialPurchasePriceHistoryController)->show($request, $source, $variation);
    }

    public function test_sku_purchase_history_shows_only_received_purchases_for_the_exact_source_and_permitted_location()
    {
        $this->salesPermissions = ['purchase.view'];
        foreach (['main', 'accessory', 'service'] as $source) {
            $db = DB::connection('finance_'.$source);
            $db->table('variations')->update(['sub_sku' => $source.'-SKU']);
            foreach ([1 => [], 2 => ['transaction_date' => '2026-11-08 12:00:00'], 3 => ['location_id' => 2], 4 => ['business_id' => 2],
                5 => ['status' => 'ordered'], 6 => ['type' => 'purchase_transfer'], 7 => ['type' => 'sell'], 8 => []] as $id => $attributes) {
                $db->table('transactions')->insert($attributes + ['id' => $id, 'type' => 'purchase', 'status' => 'received', 'ref_no' => $source.'-'.$id, 'transaction_date' => '2020-01-01 12:00:00']);
                $db->table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id, 'variation_id' => $id === 8 ? 2 : 1, 'quantity' => 1, 'purchase_price_inc_tax' => $id * 10]);
            }
            $view = $this->skuPurchaseHistoryRequest($source);
            $history = $view->getData();
            $this->assertSame($source.'-SKU', $history['product']->sku);
            $this->assertEquals([2, 1], $history['rows']->pluck('id')->all());
            $this->assertEquals([20, 10], $history['rows']->pluck('purchase_price_inc_tax')->all());
            $html = $view->render();
            $this->assertStringContainsString($source.'-2', $html);
            $this->assertStringNotContainsString($source.'-3', $html);
            $this->assertStringNotContainsString('finance_lot_sell_price', $html);
        }
        for ($id = 20; $id < 75; $id++) {
            DB::table('transactions')->insert(['id' => $id, 'type' => 'purchase', 'status' => 'received']);
            DB::table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id, 'quantity' => 1, 'purchase_price_inc_tax' => 30]);
        }
        $first = $this->skuPurchaseHistoryRequest('main')->getData()['rows'];
        $second = $this->skuPurchaseHistoryRequest('main', ['page' => 2])->getData()['rows'];
        $this->assertCount(50, $first->items());
        $this->assertCount(7, $second->items());
        $this->assertEquals(57, $first->total());
        $this->assertSame([], array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->salesPermissions = [];
        try { $this->skuPurchaseHistoryRequest('main'); $this->fail('Purchase history permission is required'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }


    private function linePriceRequest(array $data = [], bool $save = false, string $source = 'main', int $line = 1, bool $listing = false, bool $json = false)
    {
        $url = route('reports.financial-purchase-line.'.($listing ? 'index' : 'show'), $listing ? ['sourceName' => $source, 'purchase' => 10] : ['sourceName' => $source, 'line' => $line]);
        $request = Request::create($url, $save ? 'PUT' : 'GET', $data);
        if ($json) $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $module = Mockery::mock(\App\Utils\ModuleUtil::class);
        $module->shouldReceive('isSubscribed')->with(1)->andReturn(true);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $controller = new \Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseLineController(
            new FinancialDashboardController($cashier, new FinancialDashboardReport), new FinancialDashboardReport, $module);
        if ($listing) return $controller->index($request, $source, 10);
        return $save ? $controller->update($request, $source, $line)->getData(true) : $controller->show($request, $source, $line);
    }

    public function test_single_row_price_update_preserves_the_other_499_rows_and_receipt_total()
    {
        $this->salesPermissions = ['purchase.update'];
        $this->seedSales();
        DB::table('purchase_lines')->update(['quantity' => 1, 'purchase_price' => 60]);
        $lines = [];
        for ($id = 2; $id <= 500; $id++) $lines[] = ['id' => $id, 'transaction_id' => 10, 'quantity' => 1, 'purchase_price' => 60, 'purchase_price_inc_tax' => 60, 'lot_number' => 'ROW-'.$id];
        DB::table('purchase_lines')->insert($lines);
        DB::table('transactions')->where('id', 10)->update(['total_before_tax' => 30000, 'final_total' => 30000]);
        $otherRows = DB::table('purchase_lines')->where('id', '!=', 250)->orderBy('id')->get()->toArray();
        $page = $this->linePriceRequest([], false, 'main', 250)->getData();
        $this->assertCount(1, $page['details']['lots']);
        $json = $this->linePriceRequest([], false, 'main', 250, false, true)->getData(true);
        $this->assertCount(1, $json['lots']);
        $this->assertEquals(250, $json['lots'][0]['id']);
        $this->assertSame(route('purchases.edit', 10), $json['full_purchase_url']);
        $this->assertStringContainsString('/purchase-price/main/250', $json['update_url']);
        $this->assertEquals(250, $page['row']->id);
        $source = file_get_contents(base_path('Modules/LocalCashierReport/Resources/views/purchase_line_price.blade.php'));
        $source = preg_replace('/^@extends\([^\n]*\)\r?$/m', '', $source)."\n@yield('content')\n@yield('javascript')";
        $html = \Illuminate\Support\Facades\Blade::render($source, $page);
        $this->assertSame(1, substr_count($html, 'name="prices[0][purchase_price]"'));
        $this->assertStringNotContainsString('prices[1]', $html);
        $version = $page['details']['cost_version'];
        $saved = $this->linePriceRequest(['cost_version' => $version, 'prices' => [['id' => 250, 'purchase_price' => 85]]], true, 'main', 250);
        $this->assertTrue($saved['success']);
        $this->assertNotSame($version, $saved['cost_version']);
        $this->assertEquals(85, DB::table('purchase_lines')->where('id', 250)->value('purchase_price_inc_tax'));
        $this->assertEquals(30025, DB::table('transactions')->where('id', 10)->value('final_total'));
        $this->assertEquals($otherRows, DB::table('purchase_lines')->where('id', '!=', 250)->orderBy('id')->get()->toArray());
        $this->assertEquals(500, DB::table('purchase_lines')->count());
        $listing = $this->linePriceRequest([], false, 'main', 1, true)->getData()['rows'];
        $this->assertEquals(500, $listing->total());
        $this->assertCount(25, $listing->items());
        $search = $this->linePriceRequest(['q' => 'ROW-250'], false, 'main', 1, true)->getData()['rows'];
        $this->assertCount(1, $search->items());
        $this->assertEquals(250, $search->first()->id);
        try {
            $this->linePriceRequest(['cost_version' => $version, 'prices' => [['id' => 250, 'purchase_price' => 90]]], true, 'main', 250);
            $this->fail('A stale price must be rejected');
        } catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
    }

    public function test_single_row_price_editor_respects_source_permissions_location_and_submitted_id()
    {
        $this->salesPermissions = ['purchase.update'];
        foreach (['main', 'accessory', 'service'] as $source) {
            $db = DB::connection('finance_'.$source);
            $db->table('transactions')->insert(['id' => 10, 'type' => 'purchase', 'status' => 'received', 'final_total' => 60]);
            $db->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'quantity' => 1, 'purchase_price' => 60, 'purchase_price_inc_tax' => 60]);
        }
        $details = $this->linePriceRequest([], false, 'service')->getData()['details'];
        $this->linePriceRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 75]]], true, 'service');
        $this->assertEquals(75, DB::connection('finance_service')->table('purchase_lines')->value('purchase_price_inc_tax'));
        $this->assertEquals(60, DB::connection('finance_main')->table('purchase_lines')->value('purchase_price_inc_tax'));
        $this->assertEquals(60, DB::connection('finance_accessory')->table('purchase_lines')->value('purchase_price_inc_tax'));
        try {
            $this->linePriceRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 2, 'purchase_price' => 100]]], true, 'service');
            $this->fail('Cannot replace the selected row ID');
        } catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        foreach ([['location_id' => 2], ['business_id' => 2], ['status' => 'ordered'], ['type' => 'opening_stock']] as $attributes) {
            DB::table('transactions')->where('id', 10)->update(['location_id' => 1, 'business_id' => 1, 'status' => 'received', 'type' => 'purchase']);
            DB::table('transactions')->where('id', 10)->update($attributes);
            try { $this->linePriceRequest(); $this->fail('Unavailable receipt must be rejected'); }
            catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        }
        $this->salesPermissions = [];
        try { $this->linePriceRequest(); $this->fail('Purchase update permission is required'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }


    private function purchaseCostRequest(array $data = [], bool $save = false, string $source = 'main', int $line = 1, bool $stock = false)
    {
        $request = Request::create('/reports/financial-dashboard/cost-sales/'.$source.'/'.$line, $save ? 'PUT' : 'GET', $data + ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'location_ids' => [1, -2, -3]]);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $cashier->shouldReceive('getAccessibleLocations')->with(1)->andReturn($this->locations);
        $dashboard = new FinancialDashboardController($cashier, new FinancialDashboardReport);
        $module = Mockery::mock(\App\Utils\ModuleUtil::class);
        $module->shouldReceive('isSubscribed')->with(1)->andReturn(true);
        $class = $stock ? \Modules\SmartStockInventory\Http\Controllers\StockPurchaseCostController::class : \Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseCostController::class;
        $controller = new $class($dashboard, new FinancialDashboardReport, $module);
        return ($save ? $controller->update($request, $source, $line) : $controller->edit($request, $source, $line))->getData(true);
    }

    public function test_quick_purchase_cost_updates_the_original_lot_all_sales_returns_and_purchase_totals()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 10, 'purchase_price' => 60]);
        DB::table('transactions')->where('id', 10)->update(['final_total' => 600, 'total_before_tax' => 600]);
        $details = $this->purchaseCostRequest();
        $this->assertCount(1, $details['lots']);
        $this->assertEquals(60, $details['lots'][0]['purchase_price']);
        $response = $this->purchaseCostRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 80]]], true);
        $this->assertTrue($response['success']);
        $this->assertEquals(80, DB::table('purchase_lines')->value('purchase_price_inc_tax'));
        $this->assertEquals(800, DB::table('transactions')->where('id', 10)->value('final_total'));
        $this->assertEquals(10, DB::table('purchase_lines')->value('quantity'));
        $this->assertEquals(80, $this->build()['total']['cost']);
        $this->assertEquals(1, DB::table('activity_log')->count());
        $this->expectException(HttpException::class);
        try { $this->purchaseCostRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 90]]], true); }
        catch (HttpException $e) { $this->assertEquals(409, $e->getStatusCode()); throw $e; }
    }

    public function test_quick_purchase_cost_preserves_tax_and_discounts_and_recalculates_payment_status()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 2, 'purchase_price' => 100, 'purchase_price_inc_tax' => 110, 'item_tax' => 10, 'discount_percent' => 10]);
        DB::table('transactions')->where('id', 10)->update(['total_before_tax' => 200, 'tax_amount' => 9.9, 'discount_type' => 'percentage', 'discount_amount' => 10, 'shipping_charges' => 5]);
        DB::table('transaction_payments')->insert(['transaction_id' => 10, 'amount' => 225]);
        $details = $this->purchaseCostRequest();
        $this->purchaseCostRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 132]]], true);
        $lot = DB::table('purchase_lines')->first();
        $this->assertEquals(120, $lot->purchase_price);
        $this->assertEquals(12, $lot->item_tax);
        $this->assertEqualsWithDelta(133.3333, $lot->pp_without_discount, .0001);
        $purchase = DB::table('transactions')->where('id', 10)->first();
        $this->assertEquals(240, $purchase->total_before_tax);
        $this->assertEquals(11.88, $purchase->tax_amount);
        $this->assertEquals(254.48, $purchase->final_total);
        $this->assertSame('partial', $purchase->payment_status);
    }

    public function test_stock_report_purchase_price_modal_updates_shared_lot_and_respects_own_sale_access()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        $metadata = $this->purchaseCostRequest([], false, 'main', 1, true);
        $this->assertEquals(60, $metadata['lots'][0]['purchase_price']);
        $this->purchaseCostRequest(['cost_version' => $metadata['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 75]]], true, 'main', 1, true);
        $this->assertEquals(75, DB::table('purchase_lines')->where('id', 1)->value('purchase_price_inc_tax'));
        $this->salesPermissions = ['view_own_sell_only', 'purchase.update'];
        DB::table('transactions')->where('id', 1)->update(['created_by' => 2]);
        try { $this->purchaseCostRequest([], false, 'main', 1, true); $this->fail('Another owner cannot edit this lot through the report'); }
        catch (HttpException $e) { $this->assertEquals(404, $e->getStatusCode()); }
        $this->salesPermissions = ['sell.view'];
        try { $this->purchaseCostRequest([], false, 'main', 1, true); $this->fail('Purchase update permission is required'); }
        catch (HttpException $e) { $this->assertEquals(403, $e->getStatusCode()); }
    }

    public function test_stock_quick_cost_honors_gate_granted_report_access_without_direct_permissions()
    {
        $this->seedSales();
        // Administrators are authorized by Gate::before, so can() may grant
        // access even when Spatie's direct permission lookup returns false.
        $admin = Mockery::mock(User::class)->makePartial();
        $admin->id = 1;
        $admin->shouldReceive('can')->andReturnUsing(fn ($permission) => in_array($permission, ['purchase.update', 'stock_report.view', 'direct_sell.view'], true));
        $admin->shouldReceive('hasAnyPermission')->andReturn(false);
        $admin->shouldReceive('permitted_locations')->andReturn([1]);
        $this->actingAs($admin);

        $metadata = $this->purchaseCostRequest([], false, 'main', 1, true);
        $this->assertEquals(60, $metadata['lots'][0]['purchase_price']);
        $result = $this->purchaseCostRequest(['cost_version' => $metadata['cost_version'], 'prices' => [['id' => 1, 'purchase_price' => 75]]], true, 'main', 1, true);
        $this->assertTrue($result['success']);
        $this->assertEquals(75, DB::table('purchase_lines')->where('id', 1)->value('purchase_price_inc_tax'));
    }

    public function test_quick_purchase_cost_requires_permission_and_rejects_foreign_lot_ids()
    {
        $this->seedSales();
        $this->salesPermissions = ['sell.view'];
        try { $this->purchaseCostRequest(); $this->fail('Purchase update permission is required'); } catch (HttpException $e) { $this->assertEquals(403, $e->getStatusCode()); }
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $details = $this->purchaseCostRequest();
        try { $this->purchaseCostRequest(['cost_version' => $details['cost_version'], 'prices' => [['id' => 999, 'purchase_price' => 2]]], true); $this->fail('Foreign lots cannot be edited'); }
        catch (HttpException $e) { $this->assertEquals(422, $e->getStatusCode()); }
        $this->assertEquals(60, DB::table('purchase_lines')->value('purchase_price_inc_tax'));
        try { $this->purchaseCostRequest(['location_ids' => [2]]); $this->fail('Unselected locations cannot be edited'); } catch (HttpException $e) { $this->assertEquals(404, $e->getStatusCode()); }
    }

    public function test_cost_sales_only_offers_quick_edits_for_accessible_received_purchase_lots()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        $this->assertNotNull($this->salesRequest([], true)['rows'][0]['cost_edit_url']);

        foreach ([['type' => 'purchase_transfer'], ['type' => 'purchase', 'status' => 'ordered'],
            ['type' => 'purchase', 'status' => 'received', 'location_id' => 99]] as $attributes) {
            DB::table('transactions')->where('id', 10)->update($attributes);
            $rows = $this->salesRequest([], true)['rows'];
            $this->assertNotEmpty($rows);
            foreach ($rows as $row) $this->assertNull($row['cost_edit_url']);
            try {
                $this->purchaseCostRequest();
                $this->fail('A stale edit link must explain why the receipt cannot be edited');
            } catch (HttpException $e) {
                $this->assertEquals(422, $e->getStatusCode());
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_quick_cost_link_loads_through_the_registered_route()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        $url = $this->salesRequest([], true)['rows'][0]['cost_edit_url'];
        $request = Request::create($url, 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $cashier = Mockery::mock(LocalCashierReportController::class);
        $cashier->shouldReceive('getAccessibleLocations')->with(1)->andReturn($this->locations);
        $this->app->instance(FinancialDashboardController::class, new FinancialDashboardController($cashier, new FinancialDashboardReport));
        $route = $this->app['router']->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $response = $route->run();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $response->getData(true)['lots']);
        $this->assertEquals($request->fullUrl(), $response->getData(true)['update_url']);
    }

    public function test_cost_details_links_to_the_latest_received_purchase_by_lot_and_variation()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => 'SERIAL-1']);
        foreach ([
            11 => ['transaction_date' => '2026-11-07 10:00:00', 'ref_no' => 'PUR-11'],
            12 => ['transaction_date' => '2026-11-07 10:00:00', 'ref_no' => 'PUR-12'],
            13 => ['business_id' => 2],
            14 => ['location_id' => 99],
            15 => ['status' => 'ordered'],
            16 => ['type' => 'purchase_transfer'],
            17 => [],
        ] as $id => $attributes) {
            DB::table('transactions')->insert($attributes + ['id' => $id, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-11-08 10:00:00']);
            DB::table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id, 'lot_number' => 'SERIAL-1', 'variation_id' => $id === 17 ? 2 : 1, 'purchase_price_inc_tax' => 90]);
        }
        $row = $this->salesRequest([], true)['rows'][0];
        $this->assertSame([['lot' => 'SERIAL-1', 'reference' => 'PUR-12', 'purchase_price' => 90, 'url' => route('reports.financial-purchase-line.show', ['sourceName' => 'main', 'line' => 12, 'report_lang' => 'km'])]], $row['purchase_edit_links']);
        $this->assertEquals(90, $row['purchase_price']);

        $this->salesPermissions = ['sell.view'];
        $this->assertSame([], $this->salesRequest([], true)['rows'][0]['purchase_edit_links']);
    }

    public function test_cost_details_does_not_link_to_an_unrelated_purchase_without_a_matching_lot()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        $this->assertSame([], $this->salesRequest([], true)['rows'][0]['purchase_edit_links']);
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => 'OPENING-LOT']);
        DB::table('transactions')->where('id', 10)->update(['type' => 'opening_stock']);
        $this->assertSame([], $this->salesRequest([], true)['rows'][0]['purchase_edit_links']);
    }

    public function test_actual_purchase_lot_price_is_used_for_sale_cost_returns_and_dashboard_totals()
    {
        $this->salesPermissions = ['sell.view', 'purchase.update'];
        $this->seedSales();
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => 'SJQWD2F2GCD', 'purchase_price_inc_tax' => 2540]);
        DB::table('transactions')->insert(['id' => 32765, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-11-07 10:00:00', 'ref_no' => 'PO2026/2209']);
        DB::table('purchase_lines')->insert(['id' => 32765, 'transaction_id' => 32765, 'lot_number' => 'SJQWD2F2GCD', 'purchase_price_inc_tax' => 2549]);
        $row = $this->salesRequest([], true)['rows'][0];
        $this->assertEquals(2549, $row['purchase_price']);
        $this->assertEquals(2549, $row['purchase_edit_links'][0]['purchase_price']);
        $this->assertSame('PO2026/2209', $row['purchase_edit_links'][0]['reference']);
        $this->assertSame(route('reports.financial-purchase-line.show', ['sourceName' => 'main', 'line' => 32765, 'report_lang' => 'km']), $row['purchase_edit_links'][0]['url']);
        $this->assertEquals(2549, $this->build()['total']['cost']);
        $this->assertEquals(110 - 2549, $this->build()['total']['gross_profit']);
        // A sale allocated to a stale transfer lot must use the same receipt.
        DB::table('transactions')->where('id', 10)->update(['type' => 'purchase_transfer']);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 2]);
        foreach ($this->salesRequest([], true)['rows'] as $sale) $this->assertEquals(2549, $sale['purchase_price']);
        $this->assertEquals(2549, $this->build()['total']['cost']);
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
        $this->salesPermissions = ['sell.view'];
        $this->seedSales();
        $accessory = DB::connection('finance_accessory');
        $accessory->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 50, 'status' => 'final'],
            ['id' => 10, 'type' => 'purchase', 'final_total' => 999, 'status' => 'received'],
        ]);
        $accessory->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'unit_price_inc_tax' => 50]);
        $accessory->table('purchase_lines')->insert(['id' => 1, 'transaction_id' => 10, 'purchase_price_inc_tax' => 20]);
        $accessory->table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1]);
        $service = DB::connection('finance_service');
        $service->table('products')->update(['enable_stock' => false]);
        $service->table('transactions')->insert([
            ['id' => 1, 'type' => 'sell', 'final_total' => 40, 'expense_category_id' => null],
            ['id' => 2, 'type' => 'expense', 'final_total' => 3, 'expense_category_id' => 1],
        ]);
        $service->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'unit_price_inc_tax' => 40]);
        $report = $this->build([1, -2, -3]);
        $this->assertCount(1, $report['branches']);
        $this->assertEquals(3, $report['total']['invoices']);
        $this->assertEquals(1, $report['total']['units']);
        $this->assertSame('Branch A', $report['top_location']['name']);
        $this->assertEquals(200, $report['total']['net_sales']);
        $this->assertEquals(80, $report['total']['cost']);
        $this->assertEquals(120, $report['total']['gross_profit']);
        $this->assertEquals($report['total']['main_sales'] + $report['total']['accessory_sales'] + $report['total']['service_sales'], $report['total']['net_sales']);
        $this->assertEquals($report['total']['net_sales'] - $report['total']['cost'], $report['total']['gross_profit']);
        $this->assertEquals(18, $report['total']['expenses']);
        $this->assertEquals(102, $report['total']['net_profit']);
        $this->assertEquals(51, $report['total']['margin']);
        $this->assertEquals(18, $report['expenses']['Office']['total']);
        $details = $this->salesRequest(['location_ids' => [1, -2, -3]], true);
        $this->assertEquals(80, $details['amount']);
        $this->assertEquals(4, $details['total']);
        $this->assertEqualsCanonicalizing(['main', 'accessory', 'service'], array_values(array_unique(array_column($details['rows'], 'source'))));
        $returnedCost = collect($details['rows'])->first(fn ($row) => $row['source'] === 'main' && $row['transaction_id'] === 2);
        $this->assertEquals(-60, $returnedCost['amount']);
        $this->assertEquals(-60, $this->salesRequest(['start_date' => '2026-11-04', 'end_date' => '2026-11-04'], true)['amount']);

    }

    public function test_accessory_and_service_use_saved_line_prices_and_return_quantities()
    {
        foreach (['finance_accessory', 'finance_service'] as $connection) {
            $db = DB::connection($connection);
            $db->table('products')->update(['enable_stock' => false]);
            $db->table('transactions')->insert([
                ['id' => 1, 'type' => 'sell', 'final_total' => 999, 'transaction_date' => '2026-11-01 12:00:00'],
                ['id' => 2, 'type' => 'sell_return', 'final_total' => 500, 'transaction_date' => '2026-11-04 12:00:00'],
                ['id' => 3, 'type' => 'expense', 'final_total' => 5, 'transaction_date' => '2026-11-04 12:00:00'],
            ]);
            $db->table('transaction_sell_lines')->insert([
                ['id' => 1, 'transaction_id' => 1, 'quantity' => 3, 'unit_price_inc_tax' => 12.5, 'parent_sell_line_id' => null, 'children_type' => null],
                ['id' => 2, 'transaction_id' => 1, 'quantity' => 2, 'unit_price_inc_tax' => 7, 'parent_sell_line_id' => null, 'children_type' => null],
                ['id' => 3, 'transaction_id' => 1, 'quantity' => 1, 'unit_price_inc_tax' => 0, 'parent_sell_line_id' => null, 'children_type' => null],
                ['id' => 4, 'transaction_id' => 1, 'quantity' => 3, 'unit_price_inc_tax' => 10, 'parent_sell_line_id' => 1, 'children_type' => 'combo'],
                ['id' => 5, 'transaction_id' => 2, 'quantity' => 1, 'unit_price_inc_tax' => null, 'parent_sell_line_id' => 1, 'children_type' => null],
            ]);
        }
        $report = $this->build([-2, -3]);
        $this->assertSame([], $report['unavailable']);
        $this->assertEquals(0, $report['total']['units']);
        $this->assertNull($report['top_location']);
        foreach (['sales' => 103, 'returns' => 25, 'net_sales' => 78, 'accessory_sales' => 39, 'service_sales' => 39, 'expenses' => 10, 'net_profit' => 68, 'missing_revenue' => 0] as $key => $value) {
            $this->assertEquals($value, $report['total'][$key], $key);
        }
        $returnsOnly = $this->build([-2, -3], ['start_date' => '2026-11-04', 'end_date' => '2026-11-04']);
        $this->assertEquals(-25, $returnsOnly['total']['net_sales']);
        $this->assertEquals(-35, $returnsOnly['total']['net_profit']);
    }

    public function test_missing_external_selling_prices_or_lines_do_not_show_partial_revenue_as_complete()
    {
        $this->seedSales();
        $db = DB::connection('finance_accessory');
        $db->table('products')->update(['enable_stock' => false]);
        $db->table('transactions')->insert([
            ['id' => 1, 'final_total' => 50],
            ['id' => 2, 'final_total' => 90],
        ]);
        $db->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'quantity' => 2, 'unit_price_inc_tax' => null]);
        $report = $this->build([1, -2]);
        $this->assertSame([], $report['unavailable']);
        $this->assertEquals(2, $report['total']['missing_revenue']);
        $this->assertEquals(1, $report['total']['units']);
        $this->assertEquals(110, $report['total']['main_sales']);
        $this->assertEquals(15, $report['total']['expenses']);
        foreach (['accessory_sales', 'sales', 'returns', 'net_sales', 'gross_profit', 'net_profit', 'margin'] as $metric) {
            $this->assertNull($report['total'][$metric], $metric);
        }
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
        $accessory->table('transaction_sell_lines')->insert(['id' => 1, 'transaction_id' => 1, 'quantity' => 1, 'lot_no_line_id' => 1, 'unit_price_inc_tax' => 25]);

        $report = $this->build([1, -4, -6, 2, 3]);
        $this->assertCount(1, $report['branches']);
        $branch = array_values($report['branches'])[0];
        $this->assertSame('កម្ពុជាក្រោម', $branch['name']);
        $this->assertSame([1, -4, -6, 2, 3], $branch['location_ids']);
        $this->assertEquals(6, $branch['units']);
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
        $this->assertEquals(1, $report['total']['units']);
        $this->assertSame('Branch A', $report['top_location']['name']);
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

    public function test_return_period_keeps_allocated_cost_from_an_older_sale_at_another_location()
    {
        $this->seedSales();
        DB::table('transactions')->where('id', 1)->update(['location_id' => 2, 'transaction_date' => '2026-10-15 12:00:00']);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 2]);
        // Force allocation-based costing rather than the historical-price fallback.
        DB::table('transactions')->where('id', 10)->update(['location_id' => 1]);

        $report = $this->build([1], ['start_date' => '2026-11-04', 'end_date' => '2026-11-04']);
        $this->assertEquals(-1, $report['total']['units']);
        $this->assertEquals(-60, $report['total']['cost']);
        $this->assertEquals(0, $report['total']['missing_costs']);
        $this->assertEquals(0, $report['total']['estimated_costs']);
    }

    public function test_combo_component_lines_do_not_inflate_units_sold()
    {
        $this->seedSales();
        DB::table('transaction_sell_lines')->insert(['id' => 20, 'transaction_id' => 1, 'quantity' => 5, 'children_type' => 'combo']);
        $this->assertEquals(1, $this->build()['total']['units']);
    }

    public function test_dashboard_renders_four_kpis_the_combined_table_and_side_chart()
    {
        $this->salesPermissions = ['sell.view', 'all_expense.access', 'expense.edit'];
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
        $this->assertStringContainsString('finance-sales-details', $html);
        $this->assertStringContainsString('finance-sales-modal', $html);
        $this->assertStringContainsString('finance-pos-report-modal', $html);

        $this->assertStringContainsString('finance-sales-cost-head', $html);
        foreach (['SKU', 'Lots', 'Product', 'Qty', 'Purchase Price', 'Selling Price', 'Profit/Loss'] as $column) $this->assertStringContainsString($column, $html);

        $this->assertStringContainsString('data-cost-details="1"', $html);
        $this->assertStringContainsString('/reports/financial-dashboard/cost-sales?', $html);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $detailHeadings = $xpath->query('//table[contains(@class,"finance-combined-table")]//th[@scope="row"]/a[@data-cost-details="1"]');
        $this->assertEquals(3, $detailHeadings->length);
        foreach ($xpath->query('//a[@data-detail-kind="revenue"]') as $link) {
            $this->assertStringContainsString('/reports/financial-dashboard/revenue-sales?', $link->getAttribute('href'));
            $this->assertStringContainsString('finance-sales-details', $link->getAttribute('class'));
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $detailQuery);
            $this->assertContains($detailQuery['source'], ['accessory', 'service']);
            $this->assertEquals('2026-11-01', $detailQuery['start_date']);
            $this->assertEquals('2026-11-30', $detailQuery['end_date']);
        }

        $posLinks = $xpath->query('//a[contains(@class,"finance-pos-report-details")]');
        $this->assertEquals(3, $posLinks->length);
        foreach ($posLinks as $link) {
            $this->assertStringContainsString('/smart-stock-inventory/stock-reports/sell?', $link->getAttribute('href'));
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $posQuery);
            $this->assertEquals(1, $posQuery['finance_embed']);
            $this->assertEquals('2026-11-01', $posQuery['start_date']);
            $this->assertEquals('2026-11-30', $posQuery['end_date']);
            $this->assertEquals([1], $posQuery['location_id']);
        }
        $categoryLinks = $xpath->query('//th[@scope="row"]/a[contains(@class,"finance-expense-details")] | //a[contains(@class,"finance-bar-row") and contains(@class,"finance-expense-details")]');
        $this->assertEquals(2, $categoryLinks->length);
        foreach ($categoryLinks as $link) {
            $this->assertStringContainsString('/reports/financial-dashboard/expenses?', $link->getAttribute('href'));
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $categoryQuery);
            $this->assertSame(['Office'], $categoryQuery['expense_categories']);
            $this->assertEquals(1, $categoryQuery['expense_filter_applied']);
            $this->assertEquals('2026-11-01', $categoryQuery['start_date']);
            $this->assertEquals([1], $categoryQuery['location_ids']);
        }
        $this->assertStringContainsString('/reports/financial-dashboard/sales?', $html);
        $this->assertStringContainsString('Top location', $html);
        $this->assertStringContainsString('Gross profit margin', $html);
        $metricLabels = [];
        foreach ($xpath->query('//table[contains(@class,"finance-combined-table")]//th[@scope="row"]') as $label) $metricLabels[] = trim($label->textContent);
        $positions = [];
        foreach (['Total amount (POS)', 'Accessory — net sales', 'Service — net sales', 'All-department net sales', 'Cost of goods sold, net of returns', 'Gross profit'] as $expected) {
            foreach ($metricLabels as $index => $label) {
                if (str_starts_with($label, $expected)) { $positions[] = $index; break; }
            }
        }
        $this->assertSame([0, 1, 2, 3, 4, 5], $positions);
        $this->assertStringContainsString('POS + Accessory + Service, net of returns', $html);

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
        $this->assertStringContainsString('expense_categories%5B0%5D=Office', $html);
        $this->assertStringContainsString('print=1', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        request()->merge(['report_lang' => 'km']);
        $khmer = \Illuminate\Support\Facades\Blade::render($source, $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('ចំណូលគ្រប់ផ្នែក', $khmer);
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

    public function test_dashboard_report_is_cached_and_force_refresh_updates_the_cache()
    {
        config(['localcashierreport.financial_dashboard_cache_ttl' => 120]);
        $this->seedSales();
        $this->assertEquals(220, $this->build()['total']['sales']);

        // Post a sale after the report has been cached.
        DB::table('transactions')->insert(['id' => 5, 'type' => 'sell', 'final_total' => 50, 'status' => 'final', 'transaction_date' => '2026-11-02 12:00:00']);
        DB::table('transaction_sell_lines')->insert(['id' => 3, 'transaction_id' => 5, 'quantity' => 1, 'lot_no_line_id' => null, 'parent_sell_line_id' => null]);

        $this->assertEquals(220, $this->build()['total']['sales'], 'A cached report should be reused for identical filters.');

        $filters = ['location_ids' => [1], 'start_date' => '2026-11-01', 'end_date' => '2026-11-30'];
        $fresh = (new FinancialDashboardReport)->build(1, $this->locations, $filters, true);
        $this->assertEquals(270, $fresh['total']['sales'], 'Force refresh should aggregate the new sale.');
        $this->assertEquals(270, $this->build()['total']['sales'], 'Force refresh should replace the cached report.');
    }
}

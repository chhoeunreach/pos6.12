<?php

namespace Tests\Feature;

use App\Utils\ProductUtil;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\SmartStockInventory\Http\Controllers\SkuController;
use Modules\SmartStockInventory\Services\SkuInventoryService;
use Tests\TestCase;

class SkuManagementTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config(['database.default' => 'sku_test', 'database.connections.sku_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id'); $table->integer('business_id'); $table->string('name'); $table->string('sku');
            $table->integer('unit_id')->nullable(); $table->integer('category_id')->nullable(); $table->integer('brand_id')->nullable();
            $table->integer('sub_category_id')->nullable(); $table->boolean('is_inactive')->default(false);
        });
        Schema::create('variations', function (Blueprint $table) {
            $table->integer('id'); $table->integer('product_id'); $table->string('name'); $table->string('sub_sku'); $table->softDeletes();
        });
        Schema::create('units', function (Blueprint $table) { $table->integer('id'); $table->string('short_name'); });
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->integer('variation_id'); $table->integer('location_id'); $table->double('qty_available');
        });
        Schema::create('business_locations', function (Blueprint $table) {
            $table->integer('id'); $table->integer('business_id'); $table->string('name');
        });
        DB::table('products')->insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'Phone', 'sku' => 'PHONE'],
            ['id' => 2, 'business_id' => 1, 'name' => 'No stock', 'sku' => 'EMPTY'],
            ['id' => 3, 'business_id' => 2, 'name' => 'Other business', 'sku' => 'OTHER'],
        ]);
        DB::table('variations')->insert([
            ['id' => 11, 'product_id' => 1, 'name' => 'Blue', 'sub_sku' => 'PHONE-BLUE'],
            ['id' => 12, 'product_id' => 1, 'name' => 'Red', 'sub_sku' => 'PHONE-RED'],
            ['id' => 21, 'product_id' => 2, 'name' => 'DUMMY', 'sub_sku' => 'EMPTY'],
            ['id' => 31, 'product_id' => 3, 'name' => 'DUMMY', 'sub_sku' => 'OTHER'],
        ]);
        DB::table('variation_location_details')->insert([
            ['variation_id' => 11, 'location_id' => 1, 'qty_available' => 5],
            ['variation_id' => 11, 'location_id' => 2, 'qty_available' => 100],
            ['variation_id' => 12, 'location_id' => 1, 'qty_available' => -2],
        ]);
        DB::table('business_locations')->insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'Shop'],
            ['id' => 2, 'business_id' => 1, 'name' => 'Warehouse'],
            ['id' => 3, 'business_id' => 2, 'name' => 'Other business'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sku_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_sku_list_includes_no_lot_and_zero_stock_products_and_scopes_stock_by_location(): void
    {
        $service = new SkuInventoryService(\Mockery::mock(ProductUtil::class));
        $rows = $service->products(1, [1])->get()->keyBy('variation_id');
        $this->assertCount(3, $rows);
        $this->assertSame(5.0, (float) $rows[11]->current_stock);
        $this->assertSame(0.0, (float) $rows[21]->current_stock);
        $this->assertSame([11], $service->products(1, [1], ['search' => 'PHONE-BLUE'])->get()->pluck('variation_id')->all());
        $this->assertSame([21], $service->products(1, [1], ['stock_status' => 'zero'])->get()->pluck('variation_id')->all());
        $this->assertSame([12], $service->products(1, [1], ['stock_status' => 'negative'])->get()->pluck('variation_id')->all());
        $this->assertSame(105.0, (float) $service->products(1, [1, 2], ['search' => 'PHONE-BLUE'])->first()->current_stock);
        $this->assertSame(0.0, (float) $service->products(1, [], ['search' => 'PHONE-BLUE'])->first()->current_stock);
        $this->assertNull($service->products(1, [1])->where('v.id', 31)->first());
    }

    public function test_history_keeps_location_balances_when_filtering_dates_and_types(): void
    {
        $util = \Mockery::mock(ProductUtil::class);
        $util->shouldReceive('getVariationStockHistory')->once()->with(1, 11, 1)->andReturn([
            ['date' => '2026-10-01 10:00:00', 'transaction_id' => 1, 'type' => 'purchase', 'quantity_change' => 10, 'stock' => 10],
            ['date' => '2026-10-05 10:00:00', 'transaction_id' => 2, 'type' => 'sell', 'quantity_change' => -3, 'stock' => 7],
        ]);
        $util->shouldReceive('getVariationStockHistory')->once()->with(1, 11, 2)->andReturn([
            ['date' => '2026-10-05 11:00:00', 'transaction_id' => 3, 'type' => 'sell', 'quantity_change' => -1, 'stock' => 20],
        ]);
        $rows = (new SkuInventoryService($util))->history(1, 11, [1, 2, 3], ['start_date' => '2026-10-05', 'end_date' => '2026-10-05', 'type' => 'sell']);
        $this->assertCount(2, $rows);
        $this->assertSame([3, 2], $rows->pluck('transaction_id')->all());
        $this->assertSame([20, 7], $rows->pluck('stock')->all());
        $this->assertSame(['Warehouse', 'Shop'], $rows->pluck('location_name')->all());
    }

    public function test_history_rejects_another_business_variation(): void
    {
        session(['user.business_id' => 1]);
        $request = Request::create('/smart-stock-inventory/sku/history/31');
        $request->setUserResolver(fn () => new class { public function can($permission) { return true; } });
        $controller = \Mockery::mock(SkuController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('permittedLocationIds')->with(1)->andReturn([1]);
        try {
            $controller->history($request, new SkuInventoryService(\Mockery::mock(ProductUtil::class)), 31);
            $this->fail('Expected another business product to be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_history_rejects_unpermitted_location(): void
    {
        session(['user.business_id' => 1]);
        $request = Request::create('/smart-stock-inventory/sku/history/11', 'GET', ['location_id' => 2]);
        $request->setUserResolver(fn () => new class { public function can($permission) { return true; } });
        $controller = \Mockery::mock(SkuController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('permittedLocationIds')->with(1)->andReturn([1]);
        try {
            $controller->history($request, new SkuInventoryService(\Mockery::mock(ProductUtil::class)), 11);
            $this->fail('Expected denied location to be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_sku_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('ssi.sku.index'));
        $this->assertTrue(Route::has('ssi.sku.history'));
        $this->assertTrue(Route::has('ssi.sku.export'));
    }
    private function createLotFixtures(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->integer('id'); $table->integer('business_id')->default(1); $table->integer('location_id')->default(1);
            $table->string('type')->default('purchase'); $table->string('status')->default('received'); $table->integer('return_parent_id')->nullable();
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->integer('id'); $table->integer('transaction_id'); $table->integer('product_id')->default(1); $table->integer('variation_id')->default(11);
            $table->string('lot_number')->nullable(); $table->double('purchase_price_inc_tax')->nullable()->default(100); $table->double('pp_without_discount')->nullable()->default(125); $table->double('quantity')->default(10); $table->double('quantity_returned')->default(0); $table->timestamps();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->integer('id'); $table->integer('transaction_id'); $table->integer('variation_id')->default(11);
            $table->integer('lot_no_line_id')->nullable(); $table->integer('parent_sell_line_id')->nullable(); $table->double('quantity_returned')->default(0);
            $table->double('unit_price_before_discount')->default(0); $table->double('unit_price_inc_tax')->default(0);
        });
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->integer('id'); $table->integer('transaction_id'); $table->integer('variation_id')->default(11); $table->integer('lot_no_line_id')->nullable();
        });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->integer('sell_line_id')->nullable(); $table->integer('stock_adjustment_line_id')->nullable(); $table->integer('purchase_line_id');
        });
        Schema::create('smart_stock_action_logs', function (Blueprint $table) {
            $table->increments('id');
            foreach (['user_id', 'business_id', 'location_id', 'record_id', 'reference_id'] as $column) { $table->integer($column)->nullable(); }
            foreach (['user_name', 'module_name', 'table_name', 'action_type', 'reference_type', 'old_data', 'new_data', 'reason', 'ip_address'] as $column) { $table->text($column)->nullable(); }
            $table->timestamps(); $table->softDeletes();
        });
        DB::table('transactions')->insert([
            ['id' => 1, 'business_id' => 1, 'location_id' => 1],
            ['id' => 2, 'business_id' => 1, 'location_id' => 1],
            ['id' => 3, 'business_id' => 1, 'location_id' => 2],
            ['id' => 4, 'business_id' => 2, 'location_id' => 3],
        ]);
        DB::table('purchase_lines')->insert([
            ['id' => 101, 'transaction_id' => 1, 'lot_number' => 'LOT-A'],
            ['id' => 102, 'transaction_id' => 2, 'lot_number' => 'LOT-B'],
            ['id' => 103, 'transaction_id' => 3, 'lot_number' => 'PRIVATE'],
            ['id' => 104, 'transaction_id' => 4, 'lot_number' => 'OTHER-BUSINESS'],
        ]);
    }

    public function test_lots_include_receipts_explicit_sales_fifo_adjustments_and_returns(): void
    {
        $this->createLotFixtures();
        DB::table('transactions')->insert([
            ['id' => 10, 'type' => 'sell', 'return_parent_id' => null],
            ['id' => 11, 'type' => 'sell', 'return_parent_id' => null],
            ['id' => 12, 'type' => 'stock_adjustment', 'return_parent_id' => null],
            ['id' => 13, 'type' => 'sell_return', 'return_parent_id' => 10],
            ['id' => 14, 'type' => 'purchase_return', 'return_parent_id' => 1],
            ['id' => 15, 'type' => 'sell_return', 'return_parent_id' => 10],
        ]);
        DB::table('transaction_sell_lines')->insert([
            ['id' => 201, 'transaction_id' => 10, 'lot_no_line_id' => 101, 'parent_sell_line_id' => null, 'quantity_returned' => 1],
            ['id' => 202, 'transaction_id' => 11, 'lot_no_line_id' => null, 'parent_sell_line_id' => null, 'quantity_returned' => 0],
            ['id' => 203, 'transaction_id' => 13, 'lot_no_line_id' => null, 'parent_sell_line_id' => 201, 'quantity_returned' => 0],
        ]);
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 201, 'purchase_line_id' => 102],
            ['sell_line_id' => 202, 'purchase_line_id' => 101],
            ['sell_line_id' => 202, 'purchase_line_id' => 102],
            ['sell_line_id' => 202, 'purchase_line_id' => 103],
            ['sell_line_id' => 202, 'purchase_line_id' => 104],
        ]);
        DB::table('stock_adjustment_lines')->insert(['id' => 301, 'transaction_id' => 12, 'lot_no_line_id' => 102]);
        DB::table('purchase_lines')->where('id', 101)->update(['quantity_returned' => 1]);
        $rows = collect([1, 10, 11, 12, 13, 14, 15])->map(fn ($id) => ['transaction_id' => $id]);
        $result = app(\Modules\SmartStockInventory\Services\SkuLotService::class)->attach($rows, 1, 11, [1])->keyBy('transaction_id');
        $this->assertSame(['LOT-A'], array_column($result[1]['lots'], 'lot_number'));
        $this->assertSame(['LOT-A'], array_column($result[10]['lots'], 'lot_number'));
        $this->assertSame(['LOT-A', 'LOT-B'], array_column($result[11]['lots'], 'lot_number'));
        $this->assertSame(['LOT-B'], array_column($result[12]['lots'], 'lot_number'));
        foreach ([13, 14, 15] as $id) { $this->assertSame(['LOT-A'], array_column($result[$id]['lots'], 'lot_number')); }
        $this->assertTrue($result[10]['lots'][0]['editable']);
        $this->assertEquals(100, $result[10]['lots'][0]['unit_cost']);
        $this->assertEquals(125, $result[10]['lots'][0]['unit_cost_before_discount']);
        $this->assertTrue($result[10]['lots'][0]['cost_editable']);
    }

    public function test_quick_lot_update_changes_receipt_and_linked_history_and_records_audit(): void
    {
        $this->createLotFixtures();
        $service = app(\Modules\SmartStockInventory\Services\SkuLotService::class);
        $service->update(1, 11, 101, [1], ['lot_number' => 'NEW-LOT', 'original_lot_number' => 'LOT-A', 'reason' => 'Correct label'], (object) ['id' => 7, 'username' => 'Staff'], '127.0.0.1');
        $this->assertSame('NEW-LOT', DB::table('purchase_lines')->where('id', 101)->value('lot_number'));
        $this->assertSame(10.0, (float) DB::table('purchase_lines')->where('id', 101)->value('quantity'));
        $this->assertSame('LOT-B', DB::table('purchase_lines')->where('id', 102)->value('lot_number'));
        $audit = DB::table('smart_stock_action_logs')->first();
        $this->assertSame('update_sku_lot', $audit->action_type);
        $this->assertSame(['lot_number' => 'LOT-A'], json_decode($audit->old_data, true));
        $this->assertSame(['lot_number' => 'NEW-LOT'], json_decode($audit->new_data, true));
        $rows = $service->attach(collect([['transaction_id' => 1]]), 1, 11, [1]);
        $this->assertSame('NEW-LOT', $rows[0]['lots'][0]['lot_number']);
        try {
            $service->update(1, 11, 101, [1], ['lot_number' => 'STALE', 'original_lot_number' => 'LOT-A', 'reason' => 'Stale edit'], (object) ['id' => 7, 'username' => 'Staff'], '127.0.0.1');
            $this->fail('Expected stale lot update to be rejected');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('lot_number', $e->errors());
        }
        $this->assertSame(1, DB::table('smart_stock_action_logs')->count());
    }

    public function test_quick_lot_update_rejects_wrong_location_business_and_variation(): void
    {
        $this->createLotFixtures();
        $service = app(\Modules\SmartStockInventory\Services\SkuLotService::class);
        foreach ([[1, 11, 103], [1, 11, 104], [1, 12, 101]] as [$business, $variation, $line]) {
            try {
                $service->update($business, $variation, $line, [1], ['lot_number' => 'WRONG', 'original_lot_number' => 'LOT-A', 'reason' => 'Test'], (object) ['id' => 7, 'username' => 'Staff'], '127.0.0.1');
                $this->fail('Expected out-of-scope lot update to be rejected');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
        $this->assertSame(0, DB::table('smart_stock_action_logs')->count());
        $this->assertFalse(DB::table('purchase_lines')->where('lot_number', 'WRONG')->exists());
    }

    public function test_sku_ajax_sorts_stock_across_pages_and_filters_product_attributes(): void
    {
        session(['user.business_id' => 1]);
        DB::table('products')->where('id', 1)->update(['unit_id' => 1, 'category_id' => 2, 'sub_category_id' => 3]);
        DB::table('products')->where('id', 2)->update(['is_inactive' => true]);
        $data = ['search' => '', 'draw' => 1, 'start' => 0, 'length' => 1, 'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => [['data' => 'current_stock', 'name' => 'current_stock', 'orderable' => 'true', 'searchable' => 'false']]];
        $request = Request::create('/smart-stock-inventory/sku', 'GET', $data, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => new class { public function can($permission) { return true; } });
        $controller = \Mockery::mock(SkuController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('permittedLocationIds')->with(1)->andReturn([1]);
        $service = new SkuInventoryService(\Mockery::mock(ProductUtil::class));
        $result = $controller->index($request, $service)->getData(true);
        $this->assertSame(3, $result['recordsFiltered']);
        $this->assertSame(11, $result['data'][0]['variation_id']);
        $this->assertEquals(5, $result['data'][0]['current_stock']);
        $request->merge(['start' => 1]);
        $result = $controller->index($request, $service)->getData(true);
        $this->assertSame(21, $result['data'][0]['variation_id']);
        $request->merge(['start' => 0, 'unit_id' => 1, 'category_id' => 2, 'sub_category_id' => 3, 'product_status' => 'active']);
        $result = $controller->index($request, $service)->getData(true);
        $this->assertSame(2, $result['recordsFiltered']);
        $request->merge(['product_status' => 'inactive']);
        $result = $controller->index($request, $service)->getData(true);
        $this->assertSame(0, $result['recordsFiltered']);
        $request->replace([
            'draw' => 2, 'start' => 0, 'length' => 10, 'sku_search' => 'PHONE',
            'search' => ['value' => 'Blue', 'regex' => 'false'], 'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [['data' => 'product_name', 'name' => 'product_display', 'searchable' => 'true', 'orderable' => 'true']],
        ]);
        $result = $controller->index($request, $service)->getData(true);
        $this->assertSame(1, $result['recordsFiltered']);
        $this->assertSame(11, $result['data'][0]['variation_id']);
        $this->assertSame([11], $service->products(1, [1], ['search' => 'PHONE', 'table_search' => 'Blue'])->get()->pluck('variation_id')->all());
    }

    public function test_sku_cost_editor_only_accepts_permitted_received_purchase_lots(): void
    {
        $this->createLotFixtures();
        $request = Request::create('/smart-stock-inventory/sku/purchase-cost/main/101');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('user.business_id', 1);
        $request->setUserResolver(fn () => new class {
            public function can($permission) { return in_array($permission, ['stock_inventory.view', 'view_purchase_price', 'purchase.update'], true); }
            public function permitted_locations() { return [1]; }
        });
        $class = new \ReflectionClass(\Modules\SmartStockInventory\Http\Controllers\SkuPurchaseCostController::class);
        $controller = $class->newInstanceWithoutConstructor();
        $scope = $class->getMethod('scope'); $scope->setAccessible(true);
        $this->assertSame([1, 'sku_test', [1], [101]], $scope->invoke($controller, $request, 'main', 101));
        DB::table('transactions')->where('id', 2)->update(['type' => 'purchase_transfer']);
        $this->assertSame([1, 'sku_test', [1], [102]], $scope->invoke($controller, $request, 'main', 102));
        DB::table('transactions')->where('id', 2)->update(['status' => 'ordered']);
        foreach ([102, 103, 104] as $line) {
            try { $scope->invoke($controller, $request, 'main', $line); $this->fail('Unavailable cost must be rejected'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        }
        $request->setUserResolver(fn () => new class { public function can($permission) { return $permission === 'stock_inventory.view'; } });
        try { $scope->invoke($controller, $request, 'main', 101); $this->fail('Purchase update permission is required'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_cost_quick_update_is_available_for_every_stock_history_transaction_type(): void
    {
        $this->createLotFixtures();
        DB::table('transactions')->where('id', 2)->update(['type' => 'purchase_transfer']);
        DB::table('transactions')->insert([
            ['id' => 5, 'type' => 'opening_stock', 'return_parent_id' => null],
            ['id' => 6, 'type' => 'production_purchase', 'return_parent_id' => null],
            ['id' => 10, 'type' => 'sell', 'return_parent_id' => null],
            ['id' => 11, 'type' => 'sell_transfer', 'return_parent_id' => null],
            ['id' => 12, 'type' => 'production_sell', 'return_parent_id' => null],
            ['id' => 13, 'type' => 'sell_return', 'return_parent_id' => 10],
            ['id' => 14, 'type' => 'purchase_return', 'return_parent_id' => 1],
            ['id' => 15, 'type' => 'stock_adjustment', 'return_parent_id' => null],
        ]);
        DB::table('purchase_lines')->insert([
            ['id' => 105, 'transaction_id' => 5, 'lot_number' => 'OPENING'],
            ['id' => 106, 'transaction_id' => 6, 'lot_number' => 'PRODUCED'],
        ]);
        DB::table('purchase_lines')->where('id', 101)->update(['quantity_returned' => 1]);
        DB::table('transaction_sell_lines')->insert([
            ['id' => 201, 'transaction_id' => 10, 'lot_no_line_id' => 102, 'parent_sell_line_id' => null],
            ['id' => 202, 'transaction_id' => 11, 'lot_no_line_id' => 101, 'parent_sell_line_id' => null],
            ['id' => 203, 'transaction_id' => 12, 'lot_no_line_id' => 106, 'parent_sell_line_id' => null],
            ['id' => 204, 'transaction_id' => 13, 'lot_no_line_id' => null, 'parent_sell_line_id' => 201],
        ]);
        DB::table('stock_adjustment_lines')->insert(['id' => 301, 'transaction_id' => 15, 'lot_no_line_id' => 101]);
        $ids = [1, 2, 5, 6, 10, 11, 12, 13, 14, 15];
        $rows = app(\Modules\SmartStockInventory\Services\SkuLotService::class)->attach(collect($ids)->map(fn ($id) => ['transaction_id' => $id]), 1, 11, [1]);
        $this->assertCount(count($ids), $rows);
        foreach ($rows as $row) {
            $this->assertNotEmpty($row['lots'], 'Expected linked cost for transaction ' . $row['transaction_id']);
            foreach ($row['lots'] as $lot) { $this->assertTrue($lot['cost_editable'], 'Cost edit unavailable on transaction ' . $row['transaction_id']); }
        }
    }

    public function test_sku_export_omits_purchase_costs_without_the_role_permission(): void
    {
        $this->createLotFixtures();
        session(['user.business_id' => 1]);
        $util = \Mockery::mock(ProductUtil::class);
        $util->shouldReceive('getVariationStockHistory')->with(1, 11, 1)->andReturn([
            ['transaction_id' => 1, 'date' => '2026-11-11', 'type' => 'purchase', 'type_label' => 'Purchase', 'ref_no' => 'PO-1', 'quantity_change' => 10, 'stock' => 10],
        ]);
        $inventory = new SkuInventoryService($util);
        $request = Request::create('/smart-stock-inventory/sku/history/11/export');
        $actor = new class {
            public $permissions = ['stock_inventory.view', 'stock_inventory.export'];
            public function can($permission) { return in_array($permission, $this->permissions, true); }
        };
        $request->setUserResolver(fn () => $actor);
        $controller = \Mockery::mock(SkuController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('permittedLocationIds')->with(1)->andReturn([1]);
        $exports = [];
        \Maatwebsite\Excel\Facades\Excel::shouldReceive('download')->twice()->withArgs(function ($export, $filename) use (&$exports) {
            $exports[] = $export->array(); return true;
        })->andReturn(response('download'));
        $controller->export($request, $inventory, 11);
        $this->assertNotContains('Unit Cost (Before Discount)', $exports[0][0]);
        $this->assertNotContains('Unit Cost (incl. tax)', $exports[0][0]);
        $actor->permissions[] = 'view_purchase_price';
        $controller->export($request, $inventory, 11);
        $this->assertContains('Unit Cost (Before Discount)', $exports[1][0]);
        $this->assertContains('Unit Cost (incl. tax)', $exports[1][0]);
    }

}

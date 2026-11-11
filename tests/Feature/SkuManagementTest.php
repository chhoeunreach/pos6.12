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
}

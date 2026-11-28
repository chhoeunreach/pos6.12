<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Modules\SmartStockInventory\Http\Controllers\PriceCompareController;
use Modules\SmartStockInventory\Models\SmartStockPriceCompare;
use Modules\SmartStockInventory\Services\PriceCompareService;
use Tests\TestCase;

class PriceCompareTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'price_compare_test',
            'database.connections.price_compare_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);

        Schema::create('business_locations', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('business_id');
            $table->string('name');
        });
        Schema::create('units', function (Blueprint $table) {
            $table->integer('id');
            $table->string('short_name');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('business_id');
            $table->string('name');
            $table->string('sku');
            $table->integer('unit_id')->nullable();
        });
        Schema::create('variations', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('product_id');
            $table->string('name');
            $table->string('sub_sku');
            $table->softDeletes();
        });
        Schema::create('contacts', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('business_id')->default(1);
            $table->integer('location_id')->default(1);
            $table->integer('contact_id')->nullable();
            $table->string('type')->default('purchase');
            $table->string('status')->default('received');
            $table->string('ref_no')->nullable();
            $table->string('transaction_date')->default('2026-11-01 10:00:00');
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('transaction_id');
            $table->integer('product_id')->default(1);
            $table->integer('variation_id');
            $table->double('quantity')->default(1);
            $table->double('purchase_price_inc_tax')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
        });
        Schema::create('smart_stock_action_logs', function (Blueprint $table) {
            $table->increments('id');
            foreach (['user_id', 'business_id', 'location_id', 'record_id', 'reference_id'] as $col) {
                $table->integer($col)->nullable();
            }
            foreach (['user_name', 'module_name', 'table_name', 'action_type', 'reference_type', 'old_data', 'new_data', 'reason', 'ip_address'] as $col) {
                $table->text($col)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });

        app(PriceCompareService::class)->ensureTablesExist();

        DB::table('business_locations')->insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'Main'],
            ['id' => 2, 'business_id' => 1, 'name' => 'Branch'],
            ['id' => 3, 'business_id' => 2, 'name' => 'Foreign'],
        ]);
        DB::table('units')->insert(['id' => 1, 'short_name' => 'pcs']);
        DB::table('products')->insert([
            ['id' => 1, 'business_id' => 1, 'name' => 'iPhone 15', 'sku' => 'IP15', 'unit_id' => 1],
            ['id' => 2, 'business_id' => 1, 'name' => 'Charger', 'sku' => 'CHG-20W', 'unit_id' => 1],
            ['id' => 3, 'business_id' => 2, 'name' => 'Other Biz', 'sku' => 'OTHER-1', 'unit_id' => 1],
        ]);
        DB::table('variations')->insert([
            ['id' => 11, 'product_id' => 1, 'name' => 'Black 128GB', 'sub_sku' => 'IP15-BLK'],
            ['id' => 21, 'product_id' => 2, 'name' => 'DUMMY', 'sub_sku' => 'CHG-20W'],
            ['id' => 31, 'product_id' => 3, 'name' => 'DUMMY', 'sub_sku' => 'OTHER-1'],
        ]);
        DB::table('contacts')->insert(['id' => 5, 'name' => 'Apple Supplier']);

        DB::table('transactions')->insert([
            ['id' => 101, 'business_id' => 1, 'location_id' => 1, 'contact_id' => 5, 'type' => 'purchase', 'status' => 'received', 'ref_no' => 'PO-001', 'transaction_date' => '2026-11-01 09:00:00'],
            ['id' => 102, 'business_id' => 1, 'location_id' => 2, 'contact_id' => 5, 'type' => 'purchase', 'status' => 'received', 'ref_no' => 'PO-002', 'transaction_date' => '2026-11-10 09:00:00'],
            ['id' => 103, 'business_id' => 1, 'location_id' => 1, 'contact_id' => 5, 'type' => 'purchase', 'status' => 'received', 'ref_no' => 'PO-003', 'transaction_date' => '2026-11-20 09:00:00'],
            // Ignored rows: pending purchase + opening stock + foreign business
            ['id' => 104, 'business_id' => 1, 'location_id' => 1, 'contact_id' => 5, 'type' => 'purchase', 'status' => 'pending', 'ref_no' => 'PO-PEND', 'transaction_date' => '2026-11-25 09:00:00'],
            ['id' => 105, 'business_id' => 1, 'location_id' => 1, 'contact_id' => 5, 'type' => 'opening_stock', 'status' => 'received', 'ref_no' => 'OS-1', 'transaction_date' => '2026-11-26 09:00:00'],
            ['id' => 106, 'business_id' => 2, 'location_id' => 3, 'contact_id' => 5, 'type' => 'purchase', 'status' => 'received', 'ref_no' => 'PO-BIZ2', 'transaction_date' => '2026-11-27 09:00:00'],
        ]);

        DB::table('purchase_lines')->insert([
            ['id' => 1, 'transaction_id' => 101, 'product_id' => 1, 'variation_id' => 11, 'quantity' => 5, 'purchase_price_inc_tax' => 700.00],
            ['id' => 2, 'transaction_id' => 102, 'product_id' => 1, 'variation_id' => 11, 'quantity' => 5, 'purchase_price_inc_tax' => 760.00],
            ['id' => 3, 'transaction_id' => 103, 'product_id' => 1, 'variation_id' => 11, 'quantity' => 5, 'purchase_price_inc_tax' => 730.00],
            ['id' => 4, 'transaction_id' => 104, 'product_id' => 1, 'variation_id' => 11, 'quantity' => 5, 'purchase_price_inc_tax' => 999.00],
            ['id' => 5, 'transaction_id' => 105, 'product_id' => 1, 'variation_id' => 11, 'quantity' => 5, 'purchase_price_inc_tax' => 500.00],
            ['id' => 6, 'transaction_id' => 106, 'product_id' => 3, 'variation_id' => 31, 'quantity' => 5, 'purchase_price_inc_tax' => 123.00],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('price_compare_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_compare_price_routes_are_registered(): void
    {
        foreach ([
            'ssi.compare_price.index',
            'ssi.compare_price.create',
            'ssi.compare_price.store',
            'ssi.compare_price.show',
            'ssi.compare_price.edit',
            'ssi.compare_price.update',
            'ssi.compare_price.destroy',
            'ssi.compare_price.export',
            'ssi.compare_price.template',
            'ssi.compare_price.import_preview',
            'ssi.compare_price.search_sku',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route: {$routeName}");
        }
    }

    public function test_parse_resolve_and_enrich_with_real_purchase_prices_and_export_columns(): void
    {
        $service = app(PriceCompareService::class);
        $parsed = $service->parsePastedText("SKU\tQty\nip15-blk\t3\nIP15-BLK\t2\nCHG-20W\t4\nMISSING-SKU\t1\nOTHER-1\t9");
        $resolved = $service->resolveSkus(1, $parsed);

        $this->assertCount(2, $resolved['matched']);
        $this->assertSame('IP15-BLK', $resolved['matched'][0]['sku']);
        $this->assertSame(5.0, $resolved['matched'][0]['qty']); // 3 + 2 merged
        $this->assertSame('Black 128GB', $resolved['matched'][0]['variant']);
        $this->assertSame('CHG-20W', $resolved['matched'][1]['sku']);
        $this->assertSame('', $resolved['matched'][1]['variant']); // DUMMY stripped

        $this->assertCount(2, $resolved['missing']); // MISSING-SKU + foreign business OTHER-1
        $this->assertSame(['MISSING-SKU', 'OTHER-1'], array_column($resolved['missing'], 'sku'));

        // All permitted locations [1, 2]: prices are [700, 760, 730] => min=700, avg=730, max=760, latest=730
        $enrichedAll = $service->enrichWithRealPurchasePrices(1, $resolved['matched'], [1, 2], null, true);
        $iphone = $enrichedAll[0];
        $charger = $enrichedAll[1];

        $this->assertEquals(700.0, $iphone['min_price']);
        $this->assertEquals(730.0, $iphone['avg_price']);
        $this->assertEquals(760.0, $iphone['max_price']);
        $this->assertEquals(730.0, $iphone['latest_price']);
        $this->assertSame(3, $iphone['purchase_count']);
        $this->assertSame('PO-003', $iphone['latest_ref_no']);

        // Charger has no received purchases -> null costs cleanly (not 0)
        $this->assertNull($charger['min_price']);
        $this->assertNull($charger['avg_price']);
        $this->assertNull($charger['max_price']);
        $this->assertNull($charger['latest_price']);

        // Single location scope [1]: only [700, 730] => avg = 715, max = 730
        $enrichedLoc1 = $service->enrichWithRealPurchasePrices(1, $resolved['matched'], [1, 2], 1, true);
        $this->assertEquals(700.0, $enrichedLoc1[0]['min_price']);
        $this->assertEquals(715.0, $enrichedLoc1[0]['avg_price']);
        $this->assertEquals(730.0, $enrichedLoc1[0]['max_price']);
        $this->assertEquals(730.0, $enrichedLoc1[0]['latest_price']);

        // Export has the exact 8 requested headers
        $exportRows = $service->exportRows($enrichedAll, true);
        $this->assertSame([
            'SKU',
            'Product Name',
            'Variant',
            'Qty',
            'តម្លៃដែលទិញចូលទាបជាងគេ',
            'តម្លៃមធ្យម',
            'តម្លៃខ្ពស់ជាងគេ',
            'តម្លៃទិញចូលក្រោយគេ',
        ], array_keys($exportRows[0]));
        $this->assertEquals(700.0, $exportRows[0]['តម្លៃដែលទិញចូលទាបជាងគេ']);
        $this->assertEquals(730.0, $exportRows[0]['តម្លៃមធ្យម']);
        $this->assertEquals(760.0, $exportRows[0]['តម្លៃខ្ពស់ជាងគេ']);
        $this->assertEquals(730.0, $exportRows[0]['តម្លៃទិញចូលក្រោយគេ']);
    }

    public function test_controller_crud_import_preview_and_export_workflow(): void
    {
        session(['user.business_id' => 1]);
        $actor = new class {
            public $id = 7;
            public $first_name = 'Buyer';
            public $last_name = 'User';
            public $username = 'buyer';
            public function can($permission) { return true; }
        };

        $controller = \Mockery::mock(PriceCompareController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('permittedLocationIds')->with(1)->andReturn([1, 2]);
        $controller->shouldReceive('currentUser')->andReturn($actor);
        $service = app(PriceCompareService::class);

        // 1. Import preview with paste_text
        $previewReq = Request::create('/smart-stock-inventory/compare-price/import-preview', 'POST', [
            'paste_text' => "IP15-BLK\t6\nUNKNOWN-99\t1",
        ]);
        $previewReq->setUserResolver(fn () => $actor);
        $previewData = $controller->importPreview($previewReq, $service)->getData(true);
        $this->assertCount(1, $previewData['matched']);
        $this->assertEquals(760.0, $previewData['matched'][0]['max_price']);
        $this->assertCount(1, $previewData['missing']);

        // 2. Store new compare sheet
        $storeReq = Request::create('/smart-stock-inventory/compare-price', 'POST', [
            'ref_no' => 'CP-TEST-01',
            'title' => 'Weekly Reorder',
            'compare_date' => '2026-11-28',
            'items' => [
                ['sku' => 'IP15-BLK', 'qty' => 6],
                ['sku' => 'CHG-20W', 'qty' => 10],
            ],
        ]);
        $storeReq->setUserResolver(fn () => $actor);
        $storeResp = $controller->store($storeReq, $service);
        $this->assertSame(302, $storeResp->getStatusCode());

        $saved = SmartStockPriceCompare::first();
        $this->assertNotNull($saved);
        $this->assertSame('CP-TEST-01', $saved->ref_no);
        $this->assertCount(2, $saved->lines);

        // 3. Export captures 8 columns
        $capturedRows = [];
        Excel::shouldReceive('download')->once()->withArgs(function ($export, $filename) use (&$capturedRows) {
            $capturedRows = $export->array();
            return str_starts_with($filename, 'CP-TEST-01_');
        })->andReturn(response('xlsx'));

        $exportReq = Request::create('/smart-stock-inventory/compare-price/' . $saved->id . '/export');
        $exportReq->setUserResolver(fn () => $actor);
        $controller->export($exportReq, $service, $saved->id);
        $this->assertCount(3, $capturedRows); // 1 header row + 2 data rows
        $this->assertSame('SKU', $capturedRows[0][0]);
        $this->assertSame('IP15-BLK', $capturedRows[1][0]);
        $this->assertEquals(6.0, $capturedRows[1][3]);
        $this->assertEquals(700.0, $capturedRows[1][4]);

        // 4. Update qty & lines
        $updateReq = Request::create('/smart-stock-inventory/compare-price/' . $saved->id, 'PUT', [
            'ref_no' => 'CP-TEST-01',
            'title' => 'Updated Reorder',
            'compare_date' => '2026-11-28',
            'items' => [
                ['sku' => 'IP15-BLK', 'qty' => 12],
            ],
        ]);
        $updateReq->setUserResolver(fn () => $actor);
        $controller->update($updateReq, $service, $saved->id);
        $saved->refresh()->load('lines');
        $this->assertSame('Updated Reorder', $saved->title);
        $this->assertCount(1, $saved->lines);
        $this->assertEquals(12.0, $saved->lines[0]->qty);

        // 5. Soft delete
        $delReq = Request::create('/smart-stock-inventory/compare-price/' . $saved->id, 'DELETE', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $delReq->setUserResolver(fn () => $actor);
        $delResp = $controller->destroy($delReq, $service, $saved->id)->getData(true);
        $this->assertTrue($delResp['success']);
        $this->assertSoftDeleted('smart_stock_price_compares', ['id' => $saved->id]);
    }
}

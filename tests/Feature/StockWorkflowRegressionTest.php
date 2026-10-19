<?php

namespace Tests\Feature;

use App\Business;
use App\Exceptions\PurchaseSellMismatch;
use App\Http\Controllers\MobileApi\PurchaseController;
use App\Http\Controllers\MobileApi\SaleController;
use App\Http\Controllers\MobileApi\StockController;
use App\Http\Requests\MobileApi\StockAdjustmentRequest;
use App\Http\Requests\MobileApi\StockTransferRequest;
use App\Http\Requests\MobileApi\UpdatePurchaseRequest;
use App\PurchaseLine;
use App\Services\PurchaseLineMatcher;
use App\Services\PurchaseReturnStockService;
use App\Services\StockTransferStockService;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockWorkflowRegressionTest extends TestCase
{
    private $originalConnection;
    private $productUtil;
    private $transactionUtil;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'stock_test', 'logging.channels.stock_test' => [
            'driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class,
        ]]);
        $this->originalConnection = config('database.default');
        config(['database.default' => 'stock_workflows', 'database.connections.stock_workflows' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            foreach (['business_id', 'location_id', 'contact_id', 'created_by'] as $name) {
                $table->integer($name)->default(1);
            }
            foreach (['transfer_parent_id', 'return_parent_id', 'tax_id', 'pay_term_number', 'customer_group_id'] as $name) {
                $table->integer($name)->nullable();
            }
            foreach (['type', 'status', 'transaction_date', 'ref_no', 'invoice_no', 'payment_status',
                'discount_type', 'additional_notes', 'pay_term_type', 'adjustment_type'] as $name) {
                $table->string($name)->nullable();
            }
            foreach (['total_before_tax', 'final_total', 'tax_amount', 'discount_amount', 'shipping_charges'] as $name) {
                $table->double($name)->default(0);
            }
            $table->timestamps();
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            foreach (['transaction_id', 'product_id', 'variation_id'] as $name) {
                $table->integer($name)->default(1);
            }
            foreach (['sub_unit_id', 'tax_id', 'purchase_order_line_id', 'purchase_requisition_line_id'] as $name) {
                $table->integer($name)->nullable();
            }
            foreach (['quantity', 'quantity_sold', 'quantity_returned', 'quantity_adjusted', 'mfg_quantity_used',
                'purchase_price', 'purchase_price_inc_tax', 'pp_without_discount', 'discount_percent', 'item_tax'] as $name) {
                $table->double($name)->default(0);
            }
            foreach (['lot_number', 'mfg_date', 'exp_date'] as $name) { $table->string($name)->nullable(); }
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->increments('id');
            foreach (['transaction_id', 'product_id', 'variation_id'] as $name) { $table->integer($name)->default(1); }
            foreach (['lot_no_line_id', 'sub_unit_id', 'tax_id', 'discount_id', 'parent_sell_line_id', 'res_service_staff_id'] as $name) {
                $table->integer($name)->nullable();
            }
            foreach (['quantity', 'quantity_returned', 'unit_price', 'unit_price_inc_tax', 'unit_price_before_discount',
                'line_discount_amount', 'item_tax'] as $name) { $table->double($name)->default(0); }
            $table->string('line_discount_type')->nullable();
            $table->string('sell_line_note')->nullable();
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sell_line_id')->nullable();
            $table->integer('stock_adjustment_line_id')->nullable();
            $table->integer('purchase_line_id');
            $table->double('quantity')->default(0);
            $table->double('qty_returned')->default(0);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id'); $table->integer('business_id')->default(1);
            $table->string('name'); $table->integer('unit_id')->default(1); $table->boolean('enable_stock')->default(true);
        });
        Schema::create('units', function (Blueprint $table) {
            $table->increments('id'); $table->boolean('allow_decimal')->default(true);
            $table->double('base_unit_multiplier')->default(1); $table->softDeletes();
        });
        Schema::create('variations', function (Blueprint $table) {
            $table->increments('id'); $table->integer('product_id')->default(1);
            $table->integer('product_variation_id')->default(1); $table->string('sub_sku'); $table->softDeletes();
        });
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->increments('id');
            foreach (['product_id', 'variation_id', 'product_variation_id', 'location_id'] as $name) { $table->integer($name)->default(1); }
            $table->double('qty_available')->default(0); $table->timestamps();
        });
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->increments('id');
            foreach (['transaction_id', 'product_id', 'variation_id'] as $name) { $table->integer($name); }
            $table->integer('lot_no_line_id')->nullable(); $table->double('quantity'); $table->double('unit_price'); $table->timestamps();
        });
        Schema::create('business', function (Blueprint $table) {
            $table->increments('id'); $table->string('name'); $table->integer('currency_id')->default(1);
            $table->string('accounting_method')->default('fifo'); $table->text('pos_settings')->nullable();
        });
        foreach (['business_locations', 'contacts'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id'); $table->integer('business_id')->default(1); $table->string('name'); $table->softDeletes();
            });
        }
        Schema::create('currencies', function (Blueprint $table) {
            $table->increments('id'); $table->string('thousand_separator')->default(','); $table->string('decimal_separator')->default('.');
        });
        Schema::create('tax_rates', function (Blueprint $table) { $table->increments('id'); $table->softDeletes(); });
        Schema::create('transaction_payments', function (Blueprint $table) { $table->increments('id'); $table->integer('transaction_id'); });
        DB::table('currencies')->insert(['id' => 1]);
        DB::table('business')->insert(['id' => 1, 'name' => 'Test']);
        DB::table('business_locations')->insert([['id' => 1, 'name' => 'Source'], ['id' => 2, 'name' => 'Destination']]);
        DB::table('contacts')->insert(['id' => 1, 'name' => 'Supplier']);
        DB::table('units')->insert(['id' => 1]);
        DB::table('products')->insert(['id' => 1, 'name' => 'Product']);
        DB::table('variations')->insert(['id' => 1, 'sub_sku' => 'SKU']);
        DB::table('transactions')->insert(['id' => 1, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-09-01 12:00:00']);
        DB::table('purchase_lines')->insert(['id' => 1, 'quantity' => 10, 'lot_number' => 'SERIAL-A', 'purchase_price' => 100, 'purchase_price_inc_tax' => 100]);
        DB::table('variation_location_details')->insert([['location_id' => 1, 'qty_available' => 10], ['location_id' => 2, 'qty_available' => 0]]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1; $user->business_id = 1; $user->username = 'stock-test';
        $user->shouldReceive('can')->andReturn(true);
        $user->shouldReceive('permitted_locations')->andReturn('all');
        $this->actingAs($user);
        session()->put('user', ['business_id' => 1, 'id' => 1]);
        session()->put('business', ['enable_product_expiry' => 0, 'accounting_method' => 'fifo', 'date_format' => 'Y-m-d', 'time_format' => '24']);
        session()->put('currency', ['thousand_separator' => ',', 'decimal_separator' => '.']);
        Event::fake([\App\Events\StockTransferCreatedOrModified::class, \App\Events\StockAdjustmentCreatedOrModified::class]);
        $this->productUtil = \Mockery::mock(ProductUtil::class)->makePartial();
        $this->transactionUtil = \Mockery::mock(TransactionUtil::class)->makePartial();
        foreach ([$this->productUtil, $this->transactionUtil] as $util) {
            $util->shouldReceive('setAndGetReferenceCount')->andReturn(1);
            $util->shouldReceive('generateReferenceNumber')->andReturn('REF-TEST');
        }
        $this->transactionUtil->shouldReceive('activityLog')->andReturnNull();
        $this->transactionUtil->shouldReceive('updatePaymentStatus')->andReturn('due');
        $this->transactionUtil->shouldReceive('canBeEdited')->andReturn(true);
        $this->transactionUtil->shouldReceive('purchaseCurrencyDetails')->andReturn((object) ['thousand_separator' => ',', 'decimal_separator' => '.']);
        $this->app->instance(ProductUtil::class, $this->productUtil);
        $this->app->instance(TransactionUtil::class, $this->transactionUtil);
    }

    protected function tearDown(): void
    {
        DB::purge('stock_workflows');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function prepareStockHistorySchema(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->double('secondary_unit_quantity')->default(0);
        });
        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            $table->double('secondary_unit_quantity')->default(0);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('supplier_business_name')->nullable();
        });
    }

    public function test_stock_history_totals_eleven_transfer_lots_per_invoice(): void
    {
        $this->prepareStockHistorySchema();
        DB::table('transactions')->insert([
            ['id' => 2, 'type' => 'sell_transfer', 'status' => 'final', 'location_id' => 1,
                'ref_no' => 'ST2026/1499', 'transaction_date' => '2026-09-02 12:00:00'],
            ['id' => 3, 'type' => 'purchase_transfer', 'status' => 'received', 'location_id' => 2,
                'ref_no' => 'ST2026/1499', 'transaction_date' => '2026-09-02 12:00:00'],
        ]);
        for ($lot = 1; $lot <= 11; $lot++) {
            DB::table('purchase_lines')->insert(['transaction_id' => 3, 'quantity' => 1,
                'lot_number' => 'LOT-'.$lot]);
            DB::table('transaction_sell_lines')->insert(['transaction_id' => 2, 'quantity' => 1]);
        }
        // Other variations on the same invoice must not enter this product's history.
        DB::table('purchase_lines')->insert(['transaction_id' => 3, 'variation_id' => 2, 'quantity' => 90]);
        $incoming = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $outgoing = $this->productUtil->getVariationStockHistory(1, 1, 1);
        $this->assertCount(1, $incoming);
        $this->assertEquals(11, $incoming[0]['quantity_change']);
        $this->assertEquals(11, $incoming[0]['stock']);
        $this->assertEquals('ST2026/1499', $incoming[0]['ref_no']);
        $this->assertCount(2, $outgoing);
        $this->assertEquals(-11, $outgoing[0]['quantity_change']);
        $this->assertEquals(-1, $outgoing[0]['stock']);
    }

    public function test_stock_history_counts_sku_quantities_independently_of_lot_labels_and_sale_selection(): void
    {
        $this->prepareStockHistorySchema();
        DB::table('purchase_lines')->insert([
            ['transaction_id' => 1, 'variation_id' => 1, 'quantity' => 3, 'lot_number' => 'SERIAL-A'],
            ['transaction_id' => 1, 'variation_id' => 1, 'quantity' => 2, 'lot_number' => null],
            ['transaction_id' => 1, 'variation_id' => 2, 'quantity' => 90, 'lot_number' => 'OTHER-SKU'],
        ]);
        $sale = $this->sale(4);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 1);
        $this->assertEquals(15, $history[1]['quantity_change']);
        $this->assertEquals(-4, $history[0]['quantity_change']);
        $this->assertEquals(11, $history[0]['stock']);

        // Serial corrections identify the units; they do not add a quantity movement.
        DB::table('purchase_lines')->where('variation_id', 1)->update(['lot_number' => 'RENAMED-LOT']);
        DB::table('transaction_sell_lines')->where('id', $sale->id)->update(['lot_no_line_id' => null]);
        $this->assertSame($history, $this->productUtil->getVariationStockHistory(1, 1, 1));
    }

    public function test_stock_history_flags_missing_transfer_lots_without_inventing_received_quantity(): void
    {
        $this->prepareStockHistorySchema();
        DB::table('transactions')->insert([
            ['id' => 2, 'type' => 'sell_transfer', 'status' => 'final', 'location_id' => 1,
                'transfer_parent_id' => null, 'transaction_date' => '2026-09-02 12:00:00'],
            ['id' => 3, 'type' => 'purchase_transfer', 'status' => 'received', 'location_id' => 2,
                'transfer_parent_id' => 2, 'transaction_date' => '2026-09-02 12:00:00'],
        ]);
        for ($lot = 1; $lot <= 10; $lot++) {
            DB::table('transaction_sell_lines')->insert(['transaction_id' => 2, 'quantity' => 1]);
        }
        DB::table('purchase_lines')->insert(['transaction_id' => 3, 'quantity' => 1]);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $this->assertCount(1, $history);
        $this->assertEquals(1, $history[0]['quantity_change']);
        $this->assertEquals(10, $history[0]['expected_quantity']);
        $this->assertTrue($history[0]['quantity_mismatch']);
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        DB::table('purchase_lines')->where('transaction_id', 3)->update(['quantity' => 10]);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $this->assertFalse($history[0]['quantity_mismatch']);
        DB::table('purchase_lines')->where('transaction_id', 3)->delete();
        $history = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $this->assertCount(1, $history);
        $this->assertEquals(0, $history[0]['quantity_change']);
        $this->assertEquals(10, $history[0]['expected_quantity']);
        $this->assertTrue($history[0]['quantity_mismatch']);
        DB::table('purchase_lines')->insert(['transaction_id' => 3, 'quantity' => 10]);
        DB::table('transactions')->where('id', 2)->update(['business_id' => 2]);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $this->assertNull($history[0]['expected_quantity']);
        $this->assertFalse($history[0]['quantity_mismatch']);
    }

    public function test_stock_history_aggregates_receipts_and_returns_without_join_multiplication(): void
    {
        $this->prepareStockHistorySchema();
        DB::table('purchase_lines')->insert(['transaction_id' => 1, 'quantity' => 5, 'quantity_returned' => 2,
            'secondary_unit_quantity' => 3]);
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_returned' => 1]);
        DB::table('transactions')->insert(['id' => 2, 'type' => 'purchase_return', 'status' => 'final',
            'return_parent_id' => 1, 'transaction_date' => '2026-09-02 12:00:00']);
        // Existing return formats can include their own quantity-returned receipt rows.
        DB::table('purchase_lines')->insert(['transaction_id' => 2, 'quantity' => 0, 'quantity_returned' => 0]);
        DB::table('purchase_lines')->insert(['transaction_id' => 2, 'quantity' => 0, 'quantity_returned' => 0]);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 1);
        $this->assertCount(2, $history);
        $this->assertEquals(15, $history[1]['quantity_change']);
        $this->assertEquals(3, $history[1]['purchase_secondary_unit_quantity']);
        $this->assertEquals(-3, $history[0]['quantity_change']);
        $this->assertEquals(12, $history[0]['stock']);
        $this->assertSame([], $this->productUtil->getVariationStockHistory(2, 1, 1));
    }

    public function test_stock_history_uses_sale_return_lines_and_preserves_legacy_fallback(): void
    {
        $this->prepareStockHistorySchema();
        $sale = $this->sale(4, 2);
        DB::table('transaction_sell_lines')->where('id', $sale->id)->update(['unit_price_inc_tax' => 150]);
        DB::table('transactions')->insert(['id' => 3, 'type' => 'sell_return', 'status' => 'final',
            'return_parent_id' => $sale->transaction_id, 'total_before_tax' => 300, 'final_total' => 300,
            'transaction_date' => '2026-09-03 12:00:00']);
        $legacy = $this->productUtil->getVariationStockHistory(1, 1, 1);
        $this->assertEquals(2, $legacy[0]['quantity_change']);
        DB::table('transaction_sell_lines')->insert(['transaction_id' => 3, 'quantity' => 1]);
        DB::table('transaction_sell_lines')->insert(['transaction_id' => 3, 'quantity' => 2]);
        $history = $this->productUtil->getVariationStockHistory(1, 1, 1);
        $this->assertCount(3, $history);
        $this->assertEquals(3, $history[0]['quantity_change']);
        $this->assertEquals(9, $history[0]['stock']);
    }

    public function test_stock_history_page_does_not_repair_saved_stock_on_read(): void
    {
        $this->productUtil->shouldReceive('getVariationStockDetails')->once()->with(1, 1, 1)
            ->andReturn(['current_stock' => 10]);
        $this->productUtil->shouldReceive('getVariationStockHistory')->once()->with(1, 1, 1)
            ->andReturn([['stock' => 99]]);
        $request = Request::create('/products/stock-history/1?location_id=1', 'GET', [], [], [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $controller = new \App\Http\Controllers\ProductController($this->productUtil, \Mockery::mock(ModuleUtil::class));
        $view = $controller->productStockHistory(1);
        $this->assertEquals(10, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
        $this->assertEquals(10, $view->getData()['stock_details']['current_stock']);
    }

    private function form(string $class, array $input)
    {
        $request = \Mockery::mock($class)->makePartial();
        $request->shouldReceive('validated')->andReturn($input);
        return $request;
    }

    private function transfer(array $changes = [])
    {
        $input = array_replace_recursive([
            'location_id' => 1, 'transfer_location_id' => 2, 'transaction_date' => '2026-09-02 12:00:00',
            'status' => 'completed', 'products' => [['product_id' => 1, 'variation_id' => 1,
                'quantity' => 2, 'unit_cost' => 100, 'lot_no_line_id' => 1]],
        ], $changes);
        return $this->app->make(StockController::class)->storeTransfer($this->form(StockTransferRequest::class, $input));
    }

    private function sale(float $quantity, float $returned = 0): TransactionSellLine
    {
        $sale = Transaction::create(['type' => 'sell', 'status' => 'final', 'transaction_date' => '2026-09-02 12:00:00',
            'business_id' => 1, 'location_id' => 1, 'contact_id' => 1]);
        $line = $sale->sell_lines()->create(['product_id' => 1, 'variation_id' => 1, 'quantity' => $quantity,
            'quantity_returned' => $returned, 'lot_no_line_id' => 1, 'unit_price' => 150,
            'unit_price_inc_tax' => 150, 'unit_price_before_discount' => 150]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line->id,
            'purchase_line_id' => 1, 'quantity' => $quantity, 'qty_returned' => $returned]);
        PurchaseLine::find(1)->update(['quantity_sold' => $quantity - $returned]);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 10 - $quantity + $returned]);
        return $line;
    }

    private function updatePurchase(array $changes = [])
    {
        $input = array_replace_recursive(['location_id' => 1, 'contact_id' => 1, 'status' => 'received',
            'transaction_date' => '2026-09-01 12:00:00', 'products' => [['purchase_line_id' => 1,
                'product_id' => 1, 'variation_id' => 1, 'quantity' => 10, 'purchase_price' => 100,
                'purchase_price_inc_tax' => 100, 'lot_number' => 'SERIAL-A']]], $changes);
        return $this->app->make(PurchaseController::class)->update($this->form(UpdatePurchaseRequest::class, $input), 1);
    }

    public function test_mobile_transfer_preserves_serial_counters_and_both_location_balances(): void
    {
        $response = $this->transfer();
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([8, 2], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $this->assertSame('SERIAL-A', $receipt->lot_number);
        $this->assertEquals(100, $receipt->purchase_price);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->sum('quantity'));
    }

    public function test_eleven_lots_of_same_sku_increase_destination_stock_by_eleven(): void
    {
        $this->prepareStockHistorySchema();
        DB::table('variations')->where('id', 1)->update(['sub_sku' => '10100793-23']);
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 1, 'lot_number' => 'LOT-1']);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 11]);
        $products = [];
        for ($lot = 1; $lot <= 11; $lot++) {
            $receiptId = $lot === 1 ? 1 : DB::table('purchase_lines')->insertGetId([
                'transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1, 'quantity' => 1,
                'lot_number' => 'LOT-'.$lot, 'purchase_price' => 100, 'purchase_price_inc_tax' => 100,
            ]);
            $products[] = ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1,
                'unit_cost' => 100, 'lot_no_line_id' => $receiptId];
        }
        $response = $this->transfer(['products' => $products]);
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $sell = Transaction::where('type', 'sell_transfer')->firstOrFail();
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $this->assertEquals(11, $sell->sell_lines()->count());
        $this->assertEquals(11, $sell->sell_lines()->sum('quantity'));
        $this->assertEquals(11, $receipt->purchase_lines()->count());
        $this->assertEquals(11, $receipt->purchase_lines()->sum('quantity'));
        $this->assertEquals(11, $receipt->purchase_lines()->distinct()->count('lot_number'));
        $this->assertEquals([0, 11], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
        $this->assertEquals(11, DB::table('transaction_sell_lines_purchase_lines')->sum('quantity'));
        $history = $this->productUtil->getVariationStockHistory(1, 1, 2);
        $this->assertCount(1, $history);
        $this->assertEquals(11, $history[0]['quantity_change']);
        $this->assertEquals(11, $history[0]['stock']);
    }

    public function test_mobile_transfer_shortage_rolls_back_both_documents_and_stock(): void
    {
        $response = $this->transfer(['products' => [['quantity' => 11]]]);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(1, Transaction::count());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(10, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_pending_mobile_transfer_can_complete_through_shared_service_once(): void
    {
        $response = $this->transfer(['status' => 'pending']);
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $sell = Transaction::where('type', 'sell_transfer')->first();
        $receipt = Transaction::where('type', 'purchase_transfer')->first();
        $this->app->make(StockTransferStockService::class)->apply($sell, $receipt, 'fifo');
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([8, 2], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
    }

    public function test_abnormal_mobile_adjustment_deducts_location_and_receipt_stock(): void
    {
        $input = ['location_id' => 1, 'transaction_date' => '2026-09-02 12:00:00', 'products' => [[
            'product_id' => 1, 'variation_id' => 1, 'quantity' => 2, 'type' => 'abnormal', 'unit_cost' => 100, 'lot_no_line_id' => 1,
        ]]];
        $response = $this->app->make(StockController::class)->storeAdjustment($this->form(StockAdjustmentRequest::class, $input));
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_adjusted);
        $this->assertEquals(8, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_mobile_purchase_cannot_change_location_without_a_transfer(): void
    {
        $response = $this->updatePurchase(['location_id' => 2]);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(1, Transaction::find(1)->location_id);
        $this->assertEquals([10, 0], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
    }

    public function test_mobile_purchase_reduction_below_consumption_rolls_back(): void
    {
        $this->sale(8);
        $response = $this->updatePurchase(['products' => [['quantity' => 5]]]);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(10, PurchaseLine::find(1)->quantity);
        $this->assertEquals(8, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(2, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_mobile_receipt_uses_numeric_api_quantities_independent_of_currency_locale(): void
    {
        session()->put('currency', ['thousand_separator' => '.', 'decimal_separator' => ',']);
        $response = $this->updatePurchase(['products' => [['quantity' => 10.125]]]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(10.125, PurchaseLine::find(1)->quantity);
        $this->assertEquals(10.125, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_explicit_purchase_ids_preserve_duplicate_sku_receipt_rows(): void
    {
        PurchaseLine::create(['transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1, 'quantity' => 3, 'lot_number' => 'SERIAL-A']);
        $products = [['purchase_line_id' => 1, 'product_id' => 1, 'variation_id' => 1],
            ['purchase_line_id' => 2, 'product_id' => 1, 'variation_id' => 1]];
        $matched = $this->app->make(PurchaseLineMatcher::class)->match(Transaction::find(1), $products);
        $this->assertSame([1, 2], array_column($matched, 'purchase_line_id'));
    }

    public function test_ambiguous_purchase_rows_require_stable_ids(): void
    {
        PurchaseLine::create(['transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1, 'quantity' => 3, 'lot_number' => 'SERIAL-A']);
        $this->expectException(PurchaseSellMismatch::class);
        $this->app->make(PurchaseLineMatcher::class)->match(Transaction::find(1), [[
            'product_id' => 1, 'variation_id' => 1, 'lot_number' => 'SERIAL-A',
        ]]);
    }

    public function test_supplier_return_rejects_quantity_already_sold(): void
    {
        $this->sale(8);
        $this->expectException(PurchaseSellMismatch::class);
        $this->app->make(PurchaseReturnStockService::class)->apply(Transaction::find(1), [1 => 5]);
    }

    public function test_supplier_return_rejects_negative_quantities(): void
    {
        $this->expectException(PurchaseSellMismatch::class);
        $this->app->make(PurchaseReturnStockService::class)->apply(Transaction::find(1), [1 => -1]);
    }

    public function test_supplier_return_applies_only_net_delta_and_includes_manufacturing(): void
    {
        PurchaseLine::find(1)->update(['mfg_quantity_used' => 2]);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 8]);
        $service = $this->app->make(PurchaseReturnStockService::class);
        $this->assertEquals(300, $service->apply(Transaction::find(1), [1 => 3]));
        $this->assertEquals(300, $service->apply(Transaction::find(1), [1 => 3]));
        $this->assertEquals(5, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
        $service->apply(Transaction::find(1), [1 => 1]);
        $this->assertEquals(7, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_mobile_supplier_return_records_parent_totals_and_original_receipt_counters(): void
    {
        $controller = $this->app->make(PurchaseController::class);
        $input = ['transaction_date' => '2026-09-03', 'products' => [['purchase_line_id' => 1, 'quantity' => 2, 'unit_cost' => 999]]];
        $response = $controller->purchaseReturn(1, Request::create('/purchases/1/return', 'POST', $input));
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(2, $response->getData(true)['data']['purchase_lines'][0]['quantity']);
        $this->assertEquals(1, $response->getData(true)['data']['return_parent_id']);
        $return = Transaction::where('type', 'purchase_return')->first();
        $this->assertEquals(1, $return->return_parent_id);
        $this->assertEquals(200, $return->final_total);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_returned);
        $this->assertEquals(1, PurchaseLine::count());
        $response = $controller->purchaseReturn(1, Request::create('/purchases/1/return', 'POST', $input));
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(400, $return->fresh()->final_total);
        $this->assertEquals(4, PurchaseLine::find(1)->quantity_returned);
        $this->assertEquals(6, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_mobile_customer_return_restores_original_sale_lot_and_uses_original_price(): void
    {
        $line = $this->sale(2);
        $input = ['transaction_date' => '2026-09-03', 'products' => [['sell_line_id' => $line->id, 'quantity' => 1, 'unit_price' => 999]]];
        $response = $this->app->make(SaleController::class)->sellReturn($line->transaction_id, Request::create('/sales/2/return', 'POST', $input));
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $return = Transaction::where('type', 'sell_return')->first();
        $this->assertEquals($line->transaction_id, $return->return_parent_id);
        $this->assertEquals(150, $return->final_total);
        $this->assertEquals(1, $line->fresh()->quantity_returned);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(9, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_status_endpoint_repeated_receive_changes_quantity_only_once(): void
    {
        Transaction::find(1)->update(['status' => 'pending']);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 0]);
        $controller = new \App\Http\Controllers\PurchaseController($this->productUtil, $this->transactionUtil,
            $this->app->make(BusinessUtil::class), $this->app->make(ModuleUtil::class));
        $request = Request::create('/purchases/update-status', 'POST', ['purchase_id' => 1, 'status' => 'received']);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $this->assertEquals(1, $controller->updateStatus($request)['success']);
        $this->assertEquals(1, $controller->updateStatus($request)['success']);
        $this->assertEquals(10, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }
    public function test_purchase_reduction_uses_net_sales_after_customer_returns(): void
    {
        $this->sale(8, 6);
        $response = $this->updatePurchase(['products' => [['quantity' => 2]]]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $mapping = DB::table('transaction_sell_lines_purchase_lines')->first();
        $this->assertEquals(8, $mapping->quantity);
        $this->assertEquals(6, $mapping->qty_returned);
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_supplier_return_does_not_allow_manufactured_stock_to_be_returned(): void
    {
        PurchaseLine::find(1)->update(['mfg_quantity_used' => 8]);
        $this->expectException(PurchaseSellMismatch::class);
        $this->app->make(PurchaseReturnStockService::class)->apply(Transaction::find(1), [1 => 3]);
    }

    public function test_supplier_return_rejects_a_line_from_another_purchase(): void
    {
        $other = Transaction::create(['type' => 'purchase', 'status' => 'received']);
        $line = $other->purchase_lines()->create(['product_id' => 1, 'variation_id' => 1, 'quantity' => 5]);
        $request = Request::create('/purchases/1/return', 'POST', ['transaction_date' => '2026-09-03',
            'products' => [['purchase_line_id' => $line->id, 'quantity' => 1]]]);
        $response = $this->app->make(PurchaseController::class)->purchaseReturn(1, $request);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(0, $line->fresh()->quantity_returned);
        $this->assertEquals(0, Transaction::where('type', 'purchase_return')->count());
    }

    public function test_browser_supplier_return_rejects_excess_and_accepts_available_stock(): void
    {
        $this->sale(8);
        $controller = new \App\Http\Controllers\PurchaseReturnController($this->transactionUtil, $this->productUtil);
        foreach ([5 => 0, 2 => 1] as $quantity => $success) {
            $request = Request::create('/purchase-return', 'POST', ['transaction_id' => 1,
                'returns' => [1 => $quantity], 'tax_amount' => 0]);
            $request->setLaravelSession($this->app['session.store']);
            $this->app->instance('request', $request);
            $controller->store($request);
            $this->assertEquals($success, session('status.success'), session('status.msg'));
        }
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_returned);
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_unreceiving_consumed_purchase_rolls_back_status_and_location_stock(): void
    {
        $this->sale(8);
        $controller = new \App\Http\Controllers\PurchaseController($this->productUtil, $this->transactionUtil,
            $this->app->make(BusinessUtil::class), $this->app->make(ModuleUtil::class));
        $request = Request::create('/purchases/update-status', 'POST', ['purchase_id' => 1, 'status' => 'pending']);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $result = $controller->updateStatus($request);
        $this->assertEquals(0, $result['success']);
        $this->assertSame('received', Transaction::find(1)->status);
        $this->assertEquals(2, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_receipt_date_cannot_move_after_a_transfer_that_used_its_stock(): void
    {
        $response = $this->transfer();
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $response = $this->updatePurchase(['transaction_date' => '2026-09-03 12:00:00']);
        $this->assertEquals(422, $response->getStatusCode(), $response->getContent());
        $this->assertSame('2026-09-01 12:00:00', Transaction::find(1)->transaction_date);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_supplier_return_failure_rolls_back_other_line_changes(): void
    {
        $second = PurchaseLine::create(['transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1,
            'quantity' => 1, 'purchase_price_inc_tax' => 100]);
        try {
            $this->app->make(PurchaseReturnStockService::class)->apply(Transaction::find(1), [1 => 1, $second->id => 2]);
            $this->fail('An invalid return must roll back the whole document.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals(0, PurchaseLine::find(1)->quantity_returned);
            $this->assertEquals(0, $second->fresh()->quantity_returned);
            $this->assertEquals(10, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
        }
    }

    private function fullAuthority(): \App\Services\AuthorityFullRepairService
    {
        Schema::table('products', function (Blueprint $table) { $table->string('type')->default('single'); $table->string('sku')->default('SKU'); });
        Schema::table('units', function (Blueprint $table) { $table->string('short_name')->default('pc'); });
        Schema::table('variations', function (Blueprint $table) { $table->string('name')->default('Default'); $table->double('sell_price_inc_tax')->default(150); });
        Schema::create('product_variations', function (Blueprint $table) { $table->integer('id'); $table->string('name'); });
        DB::table('product_variations')->insert(['id' => 1, 'name' => 'Default']);
        Schema::create('smart_stock_action_logs', function (Blueprint $table) {
            $table->increments('id'); $table->integer('user_id'); $table->integer('business_id'); $table->integer('location_id')->nullable();
            $table->string('action_type'); $table->string('reference_type'); $table->integer('reference_id')->nullable();
            $table->text('reason'); $table->text('old_data'); $table->text('new_data'); $table->timestamps();
        });
        return $this->app->make(\App\Services\AuthorityFullRepairService::class);
    }

    private function recoveryFixture(): array
    {
        $this->fullAuthority();
        PurchaseLine::find(1)->update(['quantity' => 1]);
        $source = PurchaseLine::create(['transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1,
            'quantity' => 1, 'lot_number' => 'SERIAL-B', 'purchase_price' => 100, 'purchase_price_inc_tax' => 100]);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 2]);
        $response = $this->transfer(['products' => [
            ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1, 'unit_cost' => 100, 'lot_no_line_id' => 1],
            ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1, 'unit_cost' => 100, 'lot_no_line_id' => $source->id],
        ]]);
        $this->assertEquals(201, $response->getStatusCode(), $response->getContent());
        $transfer = Transaction::where('type', 'sell_transfer')->firstOrFail();
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $rows = DB::table('purchase_lines')->where('transaction_id', $receipt->id)->orderBy('id')->get();
        $backup = ['sha256' => str_repeat('a', 64), 'transactions' => [
            $transfer->id => (array) DB::table('transactions')->where('id', $transfer->id)->first(),
            $receipt->id => (array) DB::table('transactions')->where('id', $receipt->id)->first(),
        ], 'receipts' => $rows->map(fn ($row) => (array) $row)->all()];
        $sale = Transaction::create(['business_id' => 1, 'location_id' => 2, 'type' => 'sell', 'status' => 'final',
            'transaction_date' => '2026-09-03 12:00:00']);
        $line = $sale->sell_lines()->create(['product_id' => 1, 'variation_id' => 1, 'quantity' => 1,
            'lot_no_line_id' => $rows[1]->id, 'unit_price' => 150, 'unit_price_inc_tax' => 150]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line->id,
            'purchase_line_id' => $rows[1]->id, 'quantity' => 1]);
        DB::table('purchase_lines')->where('id', $rows[1]->id)->delete();
        DB::table('purchase_lines')->where('id', $rows[0]->id)->update(['lot_number' => 'SERIAL-B']);
        DB::table('variation_location_details')->where('location_id', 2)->update(['qty_available' => 0]);
        return [$this->app->make(\App\Services\AuthorityTransferRecoveryService::class), $backup,
            $transfer->id, $receipt->id, $rows[1]->id, $rows[0]->id, $line->id];
    }

    public function test_authority_recovery_preview_restores_original_ids_in_a_rolled_back_proposal(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing] = $this->recoveryFixture();
        $plan = $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup);
        $this->assertCount(2, $plan['receipt_repairs']);
        $this->assertTrue($plan['can_apply']);
        $this->assertNull(DB::table('purchase_lines')->find($deleted));
        $this->assertSame('SERIAL-B', DB::table('purchase_lines')->find($existing)->lot_number);
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
    }

    public function test_authority_recovery_reconnects_deleted_receipts_and_reconciles_actual_remaining_stock(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing, $saleLine] = $this->recoveryFixture();
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        $result = $service->apply(1, $transfer, 1, $reason, $backup, $plan['token']);
        $this->assertEquals(2, DB::table('purchase_lines')->where('transaction_id', $receipt)->sum('quantity'));
        $this->assertSame('SERIAL-A', DB::table('purchase_lines')->find($existing)->lot_number);
        $this->assertSame('SERIAL-B', DB::table('purchase_lines')->find($deleted)->lot_number);
        $this->assertEquals(1, DB::table('purchase_lines')->find($deleted)->quantity_sold);
        $this->assertEquals($deleted, DB::table('transaction_sell_lines')->find($saleLine)->lot_no_line_id);
        $this->assertEquals($deleted, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $saleLine)->value('purchase_line_id'));
        $this->assertEquals(1, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
        $this->assertEquals(2, DB::table('smart_stock_action_logs')->count());
        $this->assertNotEmpty($result['audit_log_id']);
    }

    public function test_authority_recovery_rejects_a_current_backup_that_is_already_missing_lots(): void
    {
        [$service, $backup, $transfer] = $this->recoveryFixture();
        array_pop($backup['receipts']);
        $this->expectException(PurchaseSellMismatch::class);
        $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup);
    }

    public function test_authority_recovery_rejects_foreign_receipt_id_collision_without_modifying_it(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted] = $this->recoveryFixture();
        DB::table('transactions')->insert(['id' => 99, 'business_id' => 2, 'location_id' => 9, 'type' => 'purchase', 'status' => 'received']);
        DB::table('purchase_lines')->insert(['id' => $deleted, 'transaction_id' => 99, 'product_id' => 99,
            'variation_id' => 99, 'quantity' => 10, 'lot_number' => 'FOREIGN']);
        try { $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup); $this->fail('Foreign IDs must block recovery.'); }
        catch (PurchaseSellMismatch $exception) { $this->assertSame('FOREIGN', DB::table('purchase_lines')->find($deleted)->lot_number); }
    }

    public function test_authority_recovery_rejects_a_stale_preview_before_restoring_any_lots(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted] = $this->recoveryFixture();
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        DB::table('variation_location_details')->where('location_id', 2)->update(['qty_available' => 5]);
        try { $service->apply(1, $transfer, 1, $reason, $backup, $plan['token']); $this->fail('Stale preview must fail.'); }
        catch (PurchaseSellMismatch $exception) { $this->assertNull(DB::table('purchase_lines')->find($deleted)); }
    }

    public function test_authority_recovery_audit_failure_rolls_back_lot_and_balance_changes(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing] = $this->recoveryFixture();
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        DB::statement("CREATE TRIGGER fail_recovery_audit BEFORE INSERT ON smart_stock_action_logs WHEN NEW.action_type='authority_transfer_receipt_recovery' BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
        try { $service->apply(1, $transfer, 1, $reason, $backup, $plan['token']); $this->fail('Audit failure must roll back recovery.'); }
        catch (\Illuminate\Database\QueryException $exception) {
            $this->assertNull(DB::table('purchase_lines')->find($deleted));
            $this->assertSame('SERIAL-B', DB::table('purchase_lines')->find($existing)->lot_number);
            $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
            $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        }
    }

    public function test_authority_recovery_only_accepts_a_business_administrator(): void
    {
        $staff = \Mockery::mock(User::class)->makePartial(); $staff->id = 2; $staff->business_id = 1;
        $staff->shouldReceive('can')->with('stock_inventory.authority')->andReturn(true);
        $staff->shouldReceive('can')->with('superadmin')->andReturn(false);
        $staff->shouldReceive('hasRole')->andReturn(false);
        $staff->shouldReceive('permitted_locations')->andReturn('all');
        $this->actingAs($staff);
        $request = Request::create('/smart-stock-inventory/authority/2/recovery');
        $request->setLaravelSession($this->app['session.store']); $request->setUserResolver(fn () => $staff);
        $this->app->instance('request', $request);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class)->recovery($request, 2);
    }

    public function test_authority_recovery_rejects_wrong_business_backup_documents(): void
    {
        [$service, $backup, $transfer] = $this->recoveryFixture();
        $backup['transactions'][$transfer]['business_id'] = 2;
        $this->expectException(PurchaseSellMismatch::class);
        $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup);
    }

    public function test_authority_recovery_rejects_an_invented_serial_even_when_quantity_matches(): void
    {
        [$service, $backup, $transfer] = $this->recoveryFixture();
        $backup['receipts'][0]['lot_number'] = 'INVENTED';
        $this->expectException(PurchaseSellMismatch::class);
        $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup);
    }

    public function test_authority_recovery_preserves_unexplained_adjustment_counters(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing] = $this->recoveryFixture();
        DB::table('purchase_lines')->where('id', $existing)->update(['quantity_adjusted' => 1]);
        try { $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup); $this->fail('Unknown adjustment consumption must block recovery.'); }
        catch (PurchaseSellMismatch $exception) { $this->assertEquals(1, DB::table('purchase_lines')->find($existing)->quantity_adjusted); }
    }

    public function test_authority_recovery_rejects_a_foreign_sale_referencing_a_deleted_receipt(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing, $saleLine] = $this->recoveryFixture();
        $saleTransaction = DB::table('transaction_sell_lines')->find($saleLine)->transaction_id;
        DB::table('transactions')->where('id', $saleTransaction)->update(['business_id' => 2]);
        try { $service->preview(1, $transfer, 1, 'Restore original receiving lots from verified backup.', $backup); $this->fail('Foreign references must block recovery.'); }
        catch (PurchaseSellMismatch $exception) { $this->assertNull(DB::table('purchase_lines')->find($deleted)); }
    }

    public function test_authority_recovery_token_is_bound_to_actor_and_backup_contents(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted] = $this->recoveryFixture();
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        foreach ([false, true] as $tamper) {
            $input = $backup;
            if ($tamper) { $input['receipts'][0]['quantity'] = 9; }
            try { $service->apply(1, $transfer, $tamper ? 1 : 2, $reason, $input, $plan['token']); $this->fail('Changed actor or backup must fail.'); }
            catch (PurchaseSellMismatch $exception) { $this->assertNull(DB::table('purchase_lines')->find($deleted)); }
        }
    }

    public function test_authority_recovery_preview_has_a_single_combined_apply_form(): void
    {
        [$service, $backup, $transferId] = $this->recoveryFixture();
        $plan = $service->preview(1, $transferId, 1, 'Restore original receiving lots from verified backup.', $backup);
        $transfer = Transaction::find($transferId); $payload = 'encrypted-preview';
        $request = Request::create('/smart-stock-inventory/authority/'.$transferId.'/recovery/preview', 'POST', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setLaravelSession($this->app['session.store']); $this->app->instance('request', $request);
        $html = view('smartstockinventory::authority.recovery_preview_content', compact('transfer', 'plan', 'payload'))->render();
        $this->assertSame(1, substr_count($html, '<form '));
        $this->assertStringContainsString('Deleted receipt', $html);
        $this->assertStringContainsString('Apply reviewed receipt recovery and full repair', $html);
        $this->assertStringContainsString('SERIAL-A', $html);
    }

    public function test_authority_recovery_routes_require_authority_permission(): void
    {
        $middleware = $this->app->make(\Modules\SmartStockInventory\Http\Middleware\SmartStockAccessMiddleware::class);
        $method = new \ReflectionMethod($middleware, 'requiredPermissionForRoute'); $method->setAccessible(true);
        foreach (['recovery', 'recovery_preview', 'recovery_apply'] as $action) {
            $this->assertSame('stock_inventory.authority', $method->invoke($middleware, 'ssi.authority.'.$action));
        }
    }

    public function test_authority_recovery_rejects_invalid_encrypted_apply_payload(): void
    {
        [$service, $backup, $transferId] = $this->recoveryFixture();
        $request = Request::create('/smart-stock-inventory/authority/'.$transferId.'/recovery/apply', 'POST', [
            'payload' => 'invalid', 'token' => str_repeat('a', 64), 'confirmed' => 1,
        ]);
        $request->setLaravelSession($this->app['session.store']); $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class)->applyRecovery($request, $transferId);
        $this->assertEquals(0, session('status.success'));
    }

    public function test_authority_recovery_controller_accepts_backup_and_applies_the_encrypted_preview(): void
    {
        [$service, $backup, $transferId, $receiptId, $deleted] = $this->recoveryFixture();
        $sql = '';
        foreach (['transactions' => array_values($backup['transactions']), 'purchase_lines' => $backup['receipts']] as $table => $rows) {
            $fields = array_keys($rows[0]);
            $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`, `', $fields).'`) VALUES ';
            $values = [];
            foreach ($rows as $row) {
                $values[] = '('.implode(',', array_map(fn ($value) => $value === null ? 'NULL'
                    : "'".str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value)."'", array_values($row))).')';
            }
            $sql .= implode(',', $values).";\n";
        }
        $path = tempnam(sys_get_temp_dir(), 'pos-original-backup-'); file_put_contents($path, $sql);
        try {
            $file = new \Illuminate\Http\UploadedFile($path, 'original.sql', 'text/plain', null, true);
            $request = Request::create('/smart-stock-inventory/authority/'.$transferId.'/recovery/preview', 'POST',
                ['reason' => 'Restore original receiving lots from verified backup.', 'verified' => 1], [], ['backup' => $file]);
            $request->setLaravelSession($this->app['session.store']); $request->setUserResolver(fn () => auth()->user());
            $this->app->instance('request', $request);
            $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
            $view = $controller->previewRecovery($request, $transferId);
            $this->assertSame('smartstockinventory::authority.recovery_preview', $view->name());
            $data = $view->getData();
            $this->assertNull(DB::table('purchase_lines')->find($deleted));
            $apply = Request::create('/smart-stock-inventory/authority/'.$transferId.'/recovery/apply', 'POST', [
                'payload' => $data['payload'], 'token' => $data['plan']['token'], 'confirmed' => 1,
            ]);
            $apply->setLaravelSession($this->app['session.store']); $apply->setUserResolver(fn () => auth()->user());
            $this->app->instance('request', $apply);
            $controller->applyRecovery($apply, $transferId);
            $this->assertEquals(1, session('status.success'), session('status.msg'));
            $this->assertNotNull(DB::table('purchase_lines')->find($deleted));
            $this->assertEquals(1, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        } finally { unlink($path); }
    }

    public function test_authority_recovery_rejects_a_receipt_price_change_after_preview(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing] = $this->recoveryFixture();
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        DB::table('purchase_lines')->where('id', $existing)->update(['purchase_price_inc_tax' => 999]);
        try { $service->apply(1, $transfer, 1, $reason, $backup, $plan['token']); $this->fail('Changed receipt prices must invalidate the preview.'); }
        catch (PurchaseSellMismatch $exception) {
            $this->assertNull(DB::table('purchase_lines')->find($deleted));
            $this->assertEquals(999, DB::table('purchase_lines')->find($existing)->purchase_price_inc_tax);
        }
    }

    public function test_authority_recovery_preserves_leading_zero_serial_identities(): void
    {
        [$service, $backup, $transfer, $receipt, $deleted, $existing] = $this->recoveryFixture();
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => '000123']);
        DB::table('purchase_lines')->where('id', $existing)->update(['lot_number' => '123']);
        $backup['receipts'][0]['lot_number'] = '000123';
        $reason = 'Restore original receiving lots from verified backup.';
        $plan = $service->preview(1, $transfer, 1, $reason, $backup);
        $service->apply(1, $transfer, 1, $reason, $backup, $plan['token']);
        $this->assertSame('000123', DB::table('purchase_lines')->find($existing)->lot_number);
    }

    public function test_full_authority_preview_is_read_only_and_repairs_duplicate_selected_allocations(): void
    {
        $line = $this->sale(2);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line->id, 'purchase_line_id' => 1, 'quantity' => 2]);
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $reason = 'Reconcile duplicate allocations from verified invoice records.';
        $plan = $service->preview(1, 1, $reason);
        $this->assertCount(1, $plan['allocation_repairs']);
        $this->assertCount(1, $plan['counter_repairs']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals(4, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->count());
        $this->assertEquals(1, $result['audit_log_id']);
        $this->assertFalse($service->preview(1, 1, $reason)['can_apply']);
    }

    public function test_full_authority_repairs_location_only_after_receipt_and_movement_totals_agree(): void
    {
        $this->sale(2);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 77]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->assertCount(1, $plan['location_repairs']);
        $this->assertEquals(8, $plan['location_repairs'][0]['after']);
        $service->apply(1, 1, $plan['reason'], $plan['token']);
        $this->assertEquals(8, DB::table('variation_location_details')->where('location_id', 1)->value('qty_available'));
    }

    public function test_full_authority_rejects_stale_preview_and_does_not_partially_apply(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        PurchaseLine::find(1)->update(['quantity' => 11]);
        try {
            $service->apply(1, 1, $plan['reason'], $plan['token']);
            $this->fail('A stale preview must be rejected.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertStringContainsString('Stock records changed', $exception->getMessage());
            $this->assertEquals(3, PurchaseLine::find(1)->quantity_sold);
            $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        }
    }

    public function test_full_authority_does_not_guess_receipts_for_incomplete_fifo_lines(): void
    {
        $line = $this->sale(2); $line->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['quantity' => 1]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->assertEmpty($plan['allocation_repairs']);
        $this->assertEmpty($plan['counter_repairs']);
        $this->assertEmpty($plan['location_repairs']);
        $this->assertNotEmpty($plan['unresolved']);
    }

    public function test_full_authority_keeps_transfer_with_wrong_receiving_serial_unmodified(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['lot_number' => 'WRONG-RECEIVING-SERIAL']);
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertSame('WRONG-RECEIVING-SERIAL', $receipt->fresh()->lot_number);
        $this->assertEquals(4, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_full_authority_does_not_invent_stock_to_cover_shortages(): void
    {
        $this->sale(11);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->assertFalse($plan['can_apply']);
        $this->assertEquals(10, PurchaseLine::find(1)->quantity);
        $this->assertNotEmpty($plan['unresolved']);
    }

    public function test_full_authority_tokens_are_bound_to_administrator_and_reason(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->expectException(PurchaseSellMismatch::class);
        $service->apply(1, 2, $plan['reason'], $plan['token']);
    }

    public function test_full_authority_rolls_back_stock_if_audit_logging_fails(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        Schema::table('smart_stock_action_logs', function (Blueprint $table) { $table->string('required_extra_field'); });
        try {
            $service->apply(1, 1, $plan['reason'], $plan['token']);
            $this->fail('A failed audit log must roll back the stock repair.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertEquals(3, PurchaseLine::find(1)->quantity_sold);
            $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        }
    }

    public function test_full_authority_preview_page_escapes_records_and_shows_exact_changes(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3, 'lot_number' => '<script>bad()</script>']);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified stock count and invoice movement reconciliation.');
        $this->app['request']->headers->set('X-Requested-With', 'XMLHttpRequest');
        $html = view('smartstockinventory::authority.full_preview_content', compact('plan'))->render();
        $this->assertStringContainsString('Apply reviewed full repair', $html);
        $this->assertStringNotContainsString('<script>bad()</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
    }

    private function fullAuthorityRequest(array $input): Request
    {
        $request = Request::create('/smart-stock-inventory/authority/fix-mismatches', 'POST', $input);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return $request;
    }

    public function test_full_authority_http_requires_preview_token_and_confirmation(): void
    {
        $this->fullAuthority();
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        $service = $this->app->make(\App\Services\StockAuthorityReconciliationService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->fixMismatches($this->fullAuthorityRequest(['reason' => 'Verified administrator stock correction.']), $service);
    }

    public function test_full_authority_http_preview_then_apply_records_authenticated_administrator(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3]);
        $this->fullAuthority();
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        $service = $this->app->make(\App\Services\StockAuthorityReconciliationService::class);
        $reason = 'Verified administrator stock correction.';
        $view = $controller->previewFullFix($this->fullAuthorityRequest(['reason' => $reason]), $service);
        $this->assertSame('smartstockinventory::authority.full_preview', $view->name());
        $this->assertEquals(3, PurchaseLine::find(1)->quantity_sold);
        $plan = $view->getData()['plan'];
        $response = $controller->fixMismatches($this->fullAuthorityRequest([
            'reason' => $reason, 'token' => $plan['token'], 'confirmed' => 1,
        ]), $service);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->value('user_id'));
    }

    public function test_full_authority_http_rejects_authority_staff_without_administrator_role(): void
    {
        $this->fullAuthority();
        $staff = \Mockery::mock(User::class)->makePartial();
        $staff->id = 2; $staff->business_id = 1; $staff->username = 'auditor';
        $staff->shouldReceive('can')->andReturnUsing(fn ($permission) => $permission !== 'superadmin');
        $staff->shouldReceive('getRoleNames')->andReturn(collect(['Stock Auditor']));
        $staff->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($staff);
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        try {
            $controller->previewFullFix($this->fullAuthorityRequest(['reason' => 'Verified stock audit review.']),
                $this->app->make(\App\Services\StockAuthorityReconciliationService::class));
            $this->fail('Full repair must require an administrator.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertEquals(403, $exception->getStatusCode());
        }
    }

    public function test_full_authority_route_uses_authority_permission(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('ssi.authority.preview_full_fix');
        $this->assertContains('POST', $route->methods());
        $middleware = new \Modules\SmartStockInventory\Http\Middleware\SmartStockAccessMiddleware();
        $method = new \ReflectionMethod($middleware, 'requiredPermissionForRoute'); $method->setAccessible(true);
        $this->assertSame('stock_inventory.authority', $method->invoke($middleware, 'ssi.authority.preview_full_fix'));
    }

    public function test_full_authority_does_not_change_other_business_records(): void
    {
        $this->sale(2);
        DB::table('transactions')->insert(['id' => 90, 'business_id' => 2, 'location_id' => 90, 'type' => 'purchase', 'status' => 'received']);
        DB::table('purchase_lines')->insert(['id' => 90, 'transaction_id' => 90, 'product_id' => 1, 'variation_id' => 1,
            'quantity' => 5, 'quantity_sold' => 999, 'lot_number' => 'FOREIGN-PRIVATE']);
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertStringNotContainsString('FOREIGN-PRIVATE', json_encode($plan));
        $service->apply(1, 1, $plan['reason'], $plan['token']);
        $this->assertEquals(999, PurchaseLine::find(90)->quantity_sold);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_full_authority_blocks_foreign_allocation_referencing_this_business_receipt(): void
    {
        DB::table('transactions')->insert(['id' => 90, 'business_id' => 2, 'type' => 'sell', 'status' => 'final']);
        DB::table('transaction_sell_lines')->insert(['id' => 900, 'transaction_id' => 90, 'quantity' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 900, 'purchase_line_id' => 1, 'quantity' => 1]);
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertEquals(4, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_full_authority_preserves_customer_return_quantities_when_merging_duplicate_allocations(): void
    {
        $line = $this->sale(2, 1);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => $line->id,
            'purchase_line_id' => 1, 'quantity' => 2, 'qty_returned' => 1]);
        PurchaseLine::find(1)->update(['quantity_sold' => 2]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified duplicate allocations and original customer return.');
        $service->apply(1, 1, $plan['reason'], $plan['token']);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $mapping = DB::table('transaction_sell_lines_purchase_lines')->first();
        $this->assertEquals(2, $mapping->quantity);
        $this->assertEquals(1, $mapping->qty_returned);
        $this->assertEquals(1, $line->fresh()->quantity_returned);
    }

    public function test_full_authority_never_allocates_stock_dated_after_transfer(): void
    {
        $this->transfer();
        Transaction::find(1)->update(['transaction_date' => '2026-09-05 12:00:00']);
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertSame('2026-09-05 12:00:00', Transaction::find(1)->transaction_date);
    }

    public function test_full_authority_old_service_entry_point_requires_preview(): void
    {
        $this->fullAuthority();
        $this->expectException(PurchaseSellMismatch::class);
        $this->app->make(\App\Services\StockAuthorityReconciliationService::class)->reconcileAll(1, 'Administrator repair reason.', 1);
    }

    public function test_full_authority_repair_preview_includes_missing_location_balance_blocker(): void
    {
        DB::table('variation_location_details')->where('location_id', 1)->delete();
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertStringContainsString('location balance row is missing', json_encode($plan['unresolved']));
    }

    public function test_full_authority_http_accepts_actual_business_administrator_without_superadmin(): void
    {
        $this->fullAuthority();
        $admin = \Mockery::mock(User::class)->makePartial();
        $admin->id = 3; $admin->business_id = 1; $admin->username = 'business-admin';
        $admin->shouldReceive('can')->andReturnUsing(fn ($permission) => $permission !== 'superadmin');
        $admin->shouldReceive('getRoleNames')->andReturn(collect(['Admin#1']));
        $admin->shouldReceive('hasRole')->with('Admin#1')->andReturn(true);
        $this->actingAs($admin);
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        $view = $controller->previewFullFix($this->fullAuthorityRequest(['reason' => 'Verified business administrator repair.']),
            $this->app->make(\App\Services\StockAuthorityReconciliationService::class));
        $this->assertSame('smartstockinventory::authority.full_preview', $view->name());
    }

    public function test_full_authority_replayed_apply_cannot_write_twice(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 4]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $service->apply(1, 1, $plan['reason'], $plan['token']);
        try {
            $service->apply(1, 1, $plan['reason'], $plan['token']);
            $this->fail('Replaying an applied preview must be rejected.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
            $this->assertEquals(1, DB::table('smart_stock_action_logs')->count());
        }
    }

    public function test_full_authority_does_not_reassign_returned_stock_to_another_receipt(): void
    {
        $line = $this->sale(2, 1);
        $second = PurchaseLine::create(['transaction_id' => 1, 'product_id' => 1, 'variation_id' => 1, 'quantity' => 3, 'lot_number' => 'SERIAL-B']);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $line->id)->update(['purchase_line_id' => $second->id]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertEquals($second->id, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_full_authority_preserves_verified_adjustment_consumption_when_repairing_sold_counter(): void
    {
        $adjustment = Transaction::create(['type' => 'stock_adjustment', 'status' => 'received']);
        DB::table('stock_adjustment_lines')->insert(['id' => 1, 'transaction_id' => $adjustment->id,
            'product_id' => 1, 'variation_id' => 1, 'quantity' => 2, 'unit_price' => 100]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['stock_adjustment_line_id' => 1,
            'purchase_line_id' => 1, 'quantity' => 2]);
        PurchaseLine::find(1)->update(['quantity_sold' => 3, 'quantity_adjusted' => 2]);
        DB::table('variation_location_details')->where('location_id', 1)->update(['qty_available' => 8]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified loss adjustment and source stock records.');
        $this->assertCount(1, $plan['counter_repairs']);
        $service->apply(1, 1, $plan['reason'], $plan['token']);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_adjusted);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('quantity'));
    }

    public function test_full_authority_does_not_release_stock_against_unallocated_adjustment(): void
    {
        $adjustment = Transaction::create(['type' => 'stock_adjustment', 'status' => 'received']);
        DB::table('stock_adjustment_lines')->insert(['transaction_id' => $adjustment->id,
            'product_id' => 1, 'variation_id' => 1, 'quantity' => 2, 'unit_price' => 100]);
        PurchaseLine::find(1)->update(['quantity_sold' => 3]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertEquals(3, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_full_authority_blocks_unverified_manufacturing_counters(): void
    {
        PurchaseLine::find(1)->update(['quantity_sold' => 3, 'mfg_quantity_used' => 2]);
        $service = $this->fullAuthority();
        $plan = $service->preview(1, 1, 'Verified full business inventory correction.');
        $this->assertFalse($plan['can_apply']);
        $this->assertNotEmpty($plan['unresolved']);
        $this->assertEquals(3, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_full_authority_http_rejects_administrator_from_another_business(): void
    {
        $this->fullAuthority();
        $admin = \Mockery::mock(User::class)->makePartial();
        $admin->id = 3; $admin->business_id = 2; $admin->username = 'other-admin';
        $admin->shouldReceive('can')->andReturnUsing(fn ($permission) => $permission !== 'superadmin');
        $admin->shouldReceive('getRoleNames')->andReturn(collect(['Admin#2']));
        $admin->shouldReceive('hasRole')->andReturn(true);
        $this->actingAs($admin);
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        try {
            $controller->previewFullFix($this->fullAuthorityRequest(['reason' => 'Verified administrator correction.']),
                $this->app->make(\App\Services\StockAuthorityReconciliationService::class));
            $this->fail('An administrator must belong to the selected business.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertEquals(403, $exception->getStatusCode());
        }
    }

    private function forceAuthority(): \App\Services\AuthorityForceRepairService
    {
        $this->fullAuthority();

        return $this->app->make(\App\Services\AuthorityForceRepairService::class);
    }

    private function corruptTransferReceivingSerial(string $serial = 'WRONG-RECEIVING-SERIAL'): PurchaseLine
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['lot_number' => $serial]);

        return $receipt;
    }

    public function test_force_repair_preview_is_read_only_and_reconciles_transfer_receiving_serial(): void
    {
        $receipt = $this->corruptTransferReceivingSerial();
        $service = $this->forceAuthority();
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $plan = $service->preview(1, 1, $reason);
        $this->assertGreaterThan(0, $plan['changed_rows']);
        $this->assertNotEmpty($plan['transfer_repairs']);
        $this->assertTrue($plan['can_apply']);
        $this->assertSame('WRONG-RECEIVING-SERIAL', $receipt->fresh()->lot_number);
        $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
    }

    public function test_force_repair_apply_rewrites_receiving_serial_and_audit_logs(): void
    {
        $receipt = $this->corruptTransferReceivingSerial();
        $service = $this->forceAuthority();
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $plan = $service->preview(1, 1, $reason);
        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertSame('SERIAL-A', $receipt->fresh()->lot_number);
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->where('action_type', 'authority_force_repair')->count());
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->value('user_id'));
        $this->assertNotEmpty($result['audit_log_id']);
    }

    public function test_force_repair_token_is_stable_across_repeated_previews(): void
    {
        $this->corruptTransferReceivingSerial();
        $service = $this->forceAuthority();
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $first = $service->preview(1, 1, $reason);
        $second = $service->preview(1, 1, $reason);
        $this->assertSame($first['token'], $second['token']);
        $this->assertSame($first['changed_rows'], $second['changed_rows']);
        $this->assertSame(count($first['transfer_repairs']), count($second['transfer_repairs']));
    }

    public function test_force_repair_restores_quantity_when_receiving_serial_already_matches(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $service = $this->forceAuthority();
        $reason = 'Restore receiving quantity from recorded outgoing allocations.';
        $plan = $service->preview(1, 1, $reason);

        $this->assertEquals(1, $receipt->fresh()->quantity, 'Preview must leave the receipt unchanged.');
        $this->assertNotEmpty($plan['transfer_repairs']);
        $this->assertEquals(1, $plan['changed_rows']);
        $service->apply(1, 1, $reason, $plan['token']);

        $this->assertEquals(2, $receipt->fresh()->quantity);
        $this->assertSame('SERIAL-A', $receipt->fresh()->lot_number);
        $this->assertEquals(2, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        $again = $service->preview(1, 1, $reason);
        $this->assertEmpty($again['transfer_repairs']);
        $this->assertEquals(0, $again['changed_rows']);
    }

    public function test_force_repair_updates_matching_lot_before_removing_surplus_receiving_row(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $surplus = $receipt->replicate();
        $surplus->lot_number = 'UNEXPECTED';
        $surplus->save();
        $service = $this->forceAuthority();
        $reason = 'Reconcile receiving lots with recorded outgoing allocations.';
        $plan = $service->preview(1, 1, $reason);
        $this->assertEquals(2, $plan['changed_rows']);
        $service->apply(1, 1, $reason, $plan['token']);

        $this->assertEquals(2, $receipt->fresh()->quantity);
        $this->assertNull($surplus->fresh());
        $this->assertEquals(2, DB::table('purchase_lines')->where('transaction_id', $receipt->transaction_id)->sum('quantity'));
        $this->assertEmpty($service->preview(1, 1, $reason)['transfer_repairs']);
    }

    public function test_force_repair_rejects_a_stale_preview_without_writing(): void
    {
        $this->corruptTransferReceivingSerial();
        $service = $this->forceAuthority();
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $plan = $service->preview(1, 1, $reason);
        PurchaseLine::find(1)->update(['quantity' => 11]);
        try {
            $service->apply(1, 1, $reason, $plan['token']);
            $this->fail('A stale force preview must be rejected.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertStringContainsString('Stock records changed', $exception->getMessage());
            $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        }
    }

    public static function receivingQuantityExamples(): array
    {
        return [
            '3498 partial receipt' => ['ST2026/3498-1', '10100595-21', false],
            '0159 partial receipt' => ['ST2026/0159-1', '10100595-24', false],
            '4375 partial receipt' => ['ST2026/4375-1', 'TEST-4375', false],
            '3498 empty receipt' => ['ST2026/3498-1', '10100595-21', true],
            '0159 empty receipt' => ['ST2026/0159-1', '10100595-24', true],
            '4375 empty receipt' => ['ST2026/4375-1', 'TEST-4375', true],
        ];
    }

    /** @dataProvider receivingQuantityExamples */
    public function test_receiving_repair_restores_missing_lots_and_clears_history_warning(string $reference, string $sku, bool $empty): void
    {
        $this->prepareStockHistorySchema();
        DB::table('variations')->where('id', 1)->update(['sub_sku' => $sku]);
        $this->transfer(['ref_no' => $reference]);
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        if ($empty) {
            $receipt->purchase_lines()->delete();
        } else {
            $receipt->purchase_lines()->update(['quantity' => 1]);
        }
        $service = $this->forceAuthority();
        $reason = 'Restore receiving lots from surviving transfer allocations.';
        $plan = $service->preview(1, 1, $reason);
        $this->assertEquals($empty ? 0 : 1, $receipt->purchase_lines()->sum('quantity'));
        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertTrue($result['is_fully_verified']);
        $this->assertEmpty($result['remaining_receiving_mismatches']);
        $audit = json_decode(DB::table('smart_stock_action_logs')->where('id', $result['audit_log_id'])->value('new_data'), true);
        $this->assertTrue($audit['is_fully_verified']);
        $this->assertEmpty($audit['remaining_receiving_mismatches']);

        $this->assertEquals(2, $receipt->purchase_lines()->sum('quantity'));
        $this->assertEquals(0, DB::table('purchase_lines')->where('transaction_id', 0)->count());
        $history = collect($this->productUtil->getVariationStockHistory(1, 1, 2))->firstWhere('transaction_id', $receipt->id);
        $this->assertFalse($history['quantity_mismatch']);
        $this->assertEquals(2, $history['quantity_change']);
        $this->assertEmpty($service->preview(1, 1, $reason)['transfer_repairs']);
    }

    public function test_receiving_repair_groups_the_same_lot_independently_of_source_cost(): void
    {
        PurchaseLine::find(1)->update(['quantity' => 1]);
        $second = PurchaseLine::find(1)->replicate();
        $second->purchase_price = 125;
        $second->purchase_price_inc_tax = 125;
        $second->save();
        $this->transfer(['products' => [
            ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1, 'unit_cost' => 100, 'lot_no_line_id' => 1],
            ['product_id' => 1, 'variation_id' => 1, 'quantity' => 1, 'unit_cost' => 125, 'lot_no_line_id' => $second->id],
        ]]);
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $receipt->purchase_lines()->orderByDesc('id')->first()->delete();
        $service = $this->forceAuthority();
        DB::transaction(function () use ($service) {
            $transferId = Transaction::where('type', 'sell_transfer')->value('id');
            $result = $service->reconcileReceivingForTransfers(1, [$transferId]);
            $this->assertEquals(0, $result['mismatched_after']);
            $this->assertEquals(0, $service->reconcileReceivingForTransfers(1, [$transferId])['mismatched_before']);
        });
        $this->assertEquals(2, $receipt->purchase_lines()->sum('quantity'));
    }

    public function test_transfer_completion_rolls_back_if_source_allocations_are_incomplete(): void
    {
        $this->transactionUtil->shouldReceive('mapPurchaseSell')->once()->andReturn(false);
        $response = $this->transfer();
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(1, Transaction::count());
        $this->assertEquals([10, 0], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
    }

    public function test_receiving_repair_preserves_multiple_referenced_rows_for_the_same_lot(): void
    {
        $this->transfer(['products' => [['quantity' => 4]]]);
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $first = $receipt->purchase_lines()->first();
        $first->update(['quantity' => 1]);
        $second = $first->replicate();
        $second->quantity = 2;
        $second->save();
        $sale = Transaction::create(['business_id' => 1, 'location_id' => 2, 'type' => 'sell',
            'status' => 'final', 'transaction_date' => '2026-09-03 12:00:00']);
        foreach ([$first, $second] as $row) {
            $line = $sale->sell_lines()->create(['product_id' => 1, 'variation_id' => 1,
                'quantity' => 1, 'lot_no_line_id' => $row->id]);
            DB::table('transaction_sell_lines_purchase_lines')->insert([
                'sell_line_id' => $line->id, 'purchase_line_id' => $row->id, 'quantity' => 1,
            ]);
            $row->update(['quantity_sold' => 1]);
        }
        $service = $this->forceAuthority();
        $reason = 'Restore receiving shortage while preserving later sale links.';
        $plan = $service->preview(1, 1, $reason);
        $service->apply(1, 1, $reason, $plan['token']);

        $this->assertEquals(4, $receipt->purchase_lines()->sum('quantity'));
        $this->assertEquals(2, $receipt->purchase_lines()->count());
        $this->assertEquals([$first->id, $second->id], $sale->sell_lines()->orderBy('id')->pluck('lot_no_line_id')->all());
        $this->assertEmpty($service->preview(1, 1, $reason)['transfer_repairs']);
    }

    public function test_transfer_completion_rolls_back_if_receiving_quantity_is_lost(): void
    {
        $this->productUtil->shouldReceive('adjustStockOverSelling')->once()->andReturnUsing(function ($purchase) {
            $purchase->purchase_lines()->update(['quantity' => 1]);
        });
        $response = $this->transfer();
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(1, Transaction::count());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([10, 0], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
    }

    public function test_transfer_completion_rolls_back_if_receiving_serial_changes_with_equal_total_quantity(): void
    {
        $this->productUtil->shouldReceive('adjustStockOverSelling')->once()->andReturnUsing(function ($purchase) {
            $purchase->purchase_lines()->update(['lot_number' => 'WRONG-SERIAL']);
        });
        $response = $this->transfer();
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals(1, Transaction::count());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([10, 0], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
    }

    public function test_force_repair_reports_exact_receiving_shortage_when_fifo_allocations_are_incomplete(): void
    {
        DB::table('variations')->where('id', 1)->update(['sub_sku' => '10100595-21']);
        $this->transfer(['ref_no' => 'ST2026/3498-1']);
        $transfer = Transaction::where('type', 'sell_transfer')->firstOrFail();
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $transfer->sell_lines()->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['quantity' => 1]);
        $receipt->purchase_lines()->update(['quantity' => 1]);
        $service = $this->forceAuthority();
        $reason = 'Inspect unresolved receiving shortage without inventing source allocations.';
        $plan = $service->preview(1, 1, $reason);

        $this->assertCount(1, $plan['remaining_receiving_mismatches']);
        $row = $plan['remaining_receiving_mismatches'][0];
        $this->assertSame('ST2026/3498-1', $row['receiving_reference']);
        $this->assertSame('10100595-21', $row['sku']);
        $this->assertEquals(2, $row['sent_quantity']);
        $this->assertEquals(1, $row['received_quantity']);
        $this->assertEquals(1, $receipt->purchase_lines()->sum('quantity'));
        $this->app['request']->headers->set('X-Requested-With', 'XMLHttpRequest');
        $html = view('smartstockinventory::authority.force_preview_content', compact('plan'))->render();
        $this->assertStringContainsString('Receiving quantity mismatches remain', $html);
        $this->assertStringContainsString('10100595-21', $html);
        $this->assertStringNotContainsString('Everything is reconciled.', $html);

        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertStringContainsString('1 receiving quantity mismatch(es) remain', $result['message']);
        $this->assertEquals(1, $receipt->purchase_lines()->sum('quantity'));
    }

    public function test_force_repair_http_preview_renders_and_apply_requires_confirmation(): void
    {
        $this->corruptTransferReceivingSerial();
        $this->forceAuthority();
        $controller = $this->app->make(\Modules\SmartStockInventory\Http\Controllers\AuthorityController::class);
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $view = $controller->previewForceFix($this->fullAuthorityRequest(['reason' => $reason]));
        $this->assertSame('smartstockinventory::authority.force_preview', $view->name());
        $plan = $view->getData()['plan'];
        $this->assertNotEmpty($plan['token']);
        $response = $controller->applyForceFix($this->fullAuthorityRequest([
            'reason' => $reason, 'token' => $plan['token'], 'confirmed' => 1,
        ]));
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->value('user_id'));
    }

    private function receivingCommandOptions(): array
    {
        $this->forceAuthority();
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id'); $table->integer('business_id'); $table->string('username'); $table->softDeletes();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id'); $table->string('name'); $table->string('guard_name');
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('users')->insert(['id' => 1, 'business_id' => 1, 'username' => 'cli-admin']);
        config(['constants.administrator_usernames' => 'cli-admin']);
        return ['business_id' => 1, '--user' => 1, '--repair-receiving' => true,
            '--reason' => 'Restore receiving lots from verified source allocations.'];
    }

    private function commandPreviewToken(array $options): string
    {
        $this->assertEquals(0, \Illuminate\Support\Facades\Artisan::call('stock:authority-fix-mismatches', $options));
        $output = \Illuminate\Support\Facades\Artisan::output();
        $plan = json_decode(substr($output, strpos($output, '{'), strrpos($output, '}') - strpos($output, '{') + 1), true, 512, JSON_THROW_ON_ERROR);
        return $plan['token'];
    }

    public function test_receiving_repair_command_previews_then_applies_the_reviewed_quantity_repair(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $options = $this->receivingCommandOptions();
        $token = $this->commandPreviewToken($options);
        $this->assertEquals(1, $receipt->fresh()->quantity);
        $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());

        $exit = \Illuminate\Support\Facades\Artisan::call('stock:authority-fix-mismatches', $options + ['--apply' => true, '--token' => $token]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertEquals(0, $exit, $output);
        $this->assertEquals(2, $receipt->fresh()->quantity);
        $this->assertStringContainsString('Full verification passed.', $output);
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->where('user_id', 1)->count());
    }

    public function test_receiving_repair_command_returns_failure_when_verification_remains_incomplete(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['quantity' => 1]);
        $options = $this->receivingCommandOptions();
        $token = $this->commandPreviewToken($options);
        $exit = \Illuminate\Support\Facades\Artisan::call('stock:authority-fix-mismatches', $options + ['--apply' => true, '--token' => $token]);

        $this->assertEquals(1, $exit);
        $this->assertEquals(1, $receipt->fresh()->quantity);
        $this->assertStringContainsString('Repair is incomplete.', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_receiving_repair_command_rejects_a_stale_token_without_applying(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $options = $this->receivingCommandOptions();
        $token = $this->commandPreviewToken($options);
        PurchaseLine::find(1)->update(['quantity' => 11]);
        $exit = \Illuminate\Support\Facades\Artisan::call('stock:authority-fix-mismatches', $options + ['--apply' => true, '--token' => $token]);

        $this->assertEquals(1, $exit);
        $this->assertEquals(1, $receipt->fresh()->quantity);
        $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
    }

    public function test_receiving_repair_command_restores_seven_sent_six_received_and_reconciles_negative_balance(): void
    {
        DB::table('variations')->where('id', 1)->update(['sub_sku' => '10100969-14']);
        DB::table('business_locations')->insert(['id' => 3, 'name' => 'Next destination']);
        $this->assertEquals(201, $this->transfer(['ref_no' => 'ST2026/5422', 'products' => [['quantity' => 7]]])->getStatusCode());
        $incoming = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $receipt = $incoming->purchase_lines()->first();
        $otherReceipts = [];
        foreach (['ST2026/4896', 'ST2026/5246', 'ST2026/5352'] as $reference) {
            $this->assertEquals(201, $this->transfer(['ref_no' => $reference, 'products' => [['quantity' => 1]]])->getStatusCode());
            $otherReceipts[] = Transaction::where('type', 'purchase_transfer')->orderByDesc('id')->first()->purchase_lines()->first();
        }
        $this->assertEquals(201, $this->transfer(['location_id' => 2, 'transfer_location_id' => 3,
            'ref_no' => 'ST2026/5470', 'transaction_date' => '2026-09-03 12:00:00',
            'products' => [['quantity' => 7, 'lot_no_line_id' => $receipt->id]]])->getStatusCode());
        foreach ($otherReceipts as $other) {
            $sale = Transaction::create(['business_id' => 1, 'location_id' => 2, 'type' => 'sell',
                'status' => 'final', 'transaction_date' => '2026-09-03 13:00:00']);
            $line = $sale->sell_lines()->create(['product_id' => 1, 'variation_id' => 1,
                'quantity' => 1, 'lot_no_line_id' => $other->id]);
            DB::table('transaction_sell_lines_purchase_lines')->insert([
                'sell_line_id' => $line->id, 'purchase_line_id' => $other->id, 'quantity' => 1,
            ]);
            $other->update(['quantity_sold' => 1]);
        }
        // Reproduce the damaged saved state from the screenshot after all later movements exist.
        $receipt->update(['quantity' => 6]);
        DB::table('variation_location_details')->where('location_id', 2)->update(['qty_available' => -1]);
        $receiptId = $receipt->id;
        $options = $this->receivingCommandOptions();
        $token = $this->commandPreviewToken($options);
        $this->assertEquals(6, $receipt->fresh()->quantity);
        $exit = \Illuminate\Support\Facades\Artisan::call('stock:authority-fix-mismatches', $options + ['--apply' => true, '--token' => $token]);

        $this->assertEquals(0, $exit, \Illuminate\Support\Facades\Artisan::output());
        $this->assertEquals(7, $receipt->fresh()->quantity);
        $this->assertEquals($receiptId, $incoming->purchase_lines()->first()->id);
        $this->assertEquals(0, DB::table('variation_location_details')->where('location_id', 2)->value('qty_available'));
        $this->assertEquals(7, DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', $receiptId)->sum('quantity'));
        $this->assertEmpty($this->app->make(\App\Services\LotStockAuditService::class)->audit(1)['transfer_issues']);
    }

    public function test_force_repair_does_not_shrink_receiving_stock_to_match_incomplete_fifo_allocations(): void
    {
        $this->transfer();
        $transfer = Transaction::where('type', 'sell_transfer')->firstOrFail();
        $receipt = Transaction::where('type', 'purchase_transfer')->firstOrFail();
        $transfer->sell_lines()->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['quantity' => 1]);
        $service = $this->forceAuthority();
        $reason = 'Preserve receiving stock while incomplete source allocations require verification.';
        $plan = $service->preview(1, 1, $reason);

        $this->assertEmpty($plan['transfer_repairs']);
        $this->assertFalse($plan['is_fully_verified']);
        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertEquals(2, $receipt->purchase_lines()->sum('quantity'));
        $this->assertFalse($result['is_fully_verified']);
        $this->assertNotEmpty($result['reconciliation']['unresolved']);
    }

    public function test_receiving_repair_with_an_empty_scope_does_not_repair_the_whole_business(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $service = $this->forceAuthority();
        $result = DB::transaction(fn () => $service->reconcileReceivingForTransfers(1, []));

        $this->assertEmpty($result['transfers']);
        $this->assertEquals(1, $receipt->fresh()->quantity);
    }

    public function test_force_repair_rolls_back_receiving_changes_when_its_audit_log_fails(): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1]);
        $service = $this->forceAuthority();
        $reason = 'Restore receiving quantity with mandatory transactional audit logging.';
        $plan = $service->preview(1, 1, $reason);
        DB::statement("CREATE TRIGGER fail_force_audit BEFORE INSERT ON smart_stock_action_logs WHEN NEW.action_type='authority_force_repair' BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
        try {
            $service->apply(1, 1, $reason, $plan['token']);
            $this->fail('Failed audit logging must roll back the receipt correction.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertEquals(1, $receipt->fresh()->quantity);
            $this->assertEquals([8, 2], DB::table('variation_location_details')->orderBy('location_id')->pluck('qty_available')->all());
            $this->assertEquals(0, DB::table('smart_stock_action_logs')->count());
        }
    }

    public static function invalidReceivingRepairSources(): array
    {
        return [
            'foreign product' => ['products', ['business_id' => 2]],
            'foreign receipt' => ['transactions', ['business_id' => 2]],
            'wrong location' => ['transactions', ['location_id' => 2]],
            'unreceived source' => ['transactions', ['status' => 'pending']],
            'future receipt' => ['transactions', ['transaction_date' => '2026-09-04 12:00:00']],
            'wrong product' => ['purchase_lines', ['product_id' => 999]],
            'wrong variation' => ['purchase_lines', ['variation_id' => 999]],
        ];
    }

    /** @dataProvider invalidReceivingRepairSources */
    public function test_force_repair_preserves_receiving_rows_when_source_evidence_is_invalid(string $table, array $changes): void
    {
        $this->transfer();
        $receipt = Transaction::where('type', 'purchase_transfer')->first()->purchase_lines()->first();
        $receipt->update(['quantity' => 1, 'lot_number' => 'KEEP-ORIGINAL-RECEIVING']);
        DB::table($table)->where('id', 1)->update($changes);
        $service = $this->forceAuthority();
        $reason = 'Preserve original receiving rows until invalid source evidence is verified.';
        $plan = $service->preview(1, 1, $reason);
        $this->assertEmpty($plan['transfer_repairs']);
        $this->assertFalse($plan['is_fully_verified']);
        $result = $service->apply(1, 1, $reason, $plan['token']);

        $this->assertEquals(1, $receipt->fresh()->quantity);
        $this->assertSame('KEEP-ORIGINAL-RECEIVING', $receipt->fresh()->lot_number);
        $this->assertFalse($result['is_fully_verified']);
    }

    public function test_unresolved_records_link_to_the_correction_form_only_for_transfer_documents(): void
    {
        $this->transfer();
        $transferId = Transaction::where('type', 'sell_transfer')->firstOrFail()->id;
        $full = $this->app->make(\App\Services\AuthorityFullRepairService::class);
        $links = $full->unresolvedActionLinks(1, [
            ['key' => 'transfer-'.$transferId, 'reference' => 'ST', 'reason' => 'reason text'],
            ['key' => 'line-42', 'reference' => 'LN', 'reason' => 'reason text'],
            ['key' => 'receipt-9999', 'reference' => 'RC', 'reason' => 'reason text'],
        ]);
        $this->assertArrayHasKey('transfer-'.$transferId, $links);
        $this->assertStringContainsString('transfer_id='.$transferId, $links['transfer-'.$transferId]);
        $this->assertArrayNotHasKey('receipt-9999', $links);
        $this->assertArrayNotHasKey('line-42', $links);
    }
    public function test_force_repair_reallocates_transfer_line_dated_after_the_transfer(): void
    {
        $this->transfer();
        $transfer = Transaction::where('type', 'sell_transfer')->firstOrFail();
        $sellLine = $transfer->sell_lines()->firstOrFail();

        $latePurchase = Transaction::create(['business_id' => 1, 'location_id' => 1, 'type' => 'purchase',
            'status' => 'received', 'transaction_date' => '2026-09-05 10:00:00']);
        $late = PurchaseLine::create(['transaction_id' => $latePurchase->id, 'product_id' => 1, 'variation_id' => 1,
            'quantity' => 5, 'lot_number' => 'LATE-SERIAL', 'purchase_price' => 100, 'purchase_price_inc_tax' => 100]);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', $sellLine->id)
            ->update(['purchase_line_id' => $late->id]);
        DB::table('transaction_sell_lines')->where('id', $sellLine->id)->update(['lot_no_line_id' => $late->id]);

        $service = $this->forceAuthority();
        $reason = 'Force reconcile receiving serials from verified transfer records.';
        $plan = $service->preview(1, 1, $reason);

        $this->assertNotEmpty($plan['reallocations']);
        $this->assertSame($late->id, $plan['reallocations'][0]['source_before']);
        $this->assertSame(1, $plan['reallocations'][0]['source_after']);
        $this->assertSame($late->id, DB::table('transaction_sell_lines_purchase_lines')
            ->where('sell_line_id', $sellLine->id)->value('purchase_line_id'));

        $result = $service->apply(1, 1, $reason, $plan['token']);
        $this->assertSame(1, DB::table('transaction_sell_lines_purchase_lines')
            ->where('sell_line_id', $sellLine->id)->value('purchase_line_id'));
        $this->assertSame(1, DB::table('transaction_sell_lines')->where('id', $sellLine->id)->value('lot_no_line_id'));
        $this->assertSame(1, DB::table('smart_stock_action_logs')->where('action_type', 'authority_force_repair')->count());
        $this->assertStringContainsString('re-pointed', $result['message']);
    }

}

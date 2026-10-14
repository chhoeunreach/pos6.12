<?php

namespace Tests\Feature;

use App\Product;
use App\Exceptions\PurchaseSellMismatch;
use App\PurchaseLine;
use App\Services\LotStockAuditService;
use App\Services\StockTransferStockService;
use App\Transaction;
use App\TransactionSellLine;
use App\VariationLocationDetails;
use App\Http\Controllers\StockTransferController;
use App\User;
use App\Utils\ModuleUtil;
use App\Events\StockTransferCreatedOrModified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SmartStockInventory\Http\Controllers\LotController;
use Tests\TestCase;

class LotSaleConsistencyTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'lot_consistency_test',
            'database.connections.lot_consistency_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->default(1);
            $table->integer('location_id')->default(12);
            $table->string('type')->default('sell');
            $table->string('status')->default('final');
            $table->string('transaction_date')->default('2026-09-01 12:00:00');
            $table->string('invoice_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->integer('transfer_parent_id')->nullable();
            $table->string('additional_notes')->nullable();
            $table->double('shipping_charges')->default(0);
            $table->double('final_total')->default(100);
            $table->double('total_before_tax')->default(100);
            $table->string('transfer_custom_field_1')->nullable();
            $table->string('transfer_custom_field_2')->nullable();
            $table->integer('return_parent_id')->nullable();
            $table->string('discount_type')->nullable();
            $table->double('discount_amount')->default(0);
            $table->integer('tax_id')->nullable();
            $table->double('tax_amount')->default(0);
            $table->timestamps();
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id')->default(589);
            $table->integer('variation_id')->default(2701);
            $table->integer('transaction_id')->default(2);
            $table->string('lot_number')->nullable();
            $table->double('quantity')->default(1);
            $table->double('quantity_sold')->default(0);
            $table->double('quantity_adjusted')->default(0);
            $table->double('quantity_returned')->default(0);
            $table->double('mfg_quantity_used')->default(0);
            $table->date('exp_date')->nullable();
            $table->date('mfg_date')->nullable();
            $table->integer('sub_unit_id')->nullable();
            $table->double('purchase_price')->default(100);
            $table->double('purchase_price_inc_tax')->default(100);
            $table->double('item_tax')->default(0);
            $table->double('line_total_tax')->default(0);
            $table->integer('tax_id')->nullable();
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id')->default(1);
            $table->integer('product_id')->default(589);
            $table->integer('variation_id')->default(2701);
            $table->integer('lot_no_line_id')->nullable();
            $table->double('quantity')->default(1);
            $table->double('quantity_returned')->default(0);
            $table->double('unit_price')->default(100);
            $table->double('unit_price_inc_tax')->default(100);
            $table->integer('sub_unit_id')->nullable();
            $table->integer('parent_sell_line_id')->nullable();
            $table->string('children_type')->nullable();
            $table->double('unit_price_before_discount')->default(100);
            $table->string('line_discount_type')->nullable();
            $table->double('line_discount_amount')->default(0);
            $table->double('item_tax')->default(0);
            $table->integer('tax_id')->nullable();
            $table->string('sell_line_note')->nullable();
            $table->integer('discount_id')->nullable();
            $table->integer('res_service_staff_id')->nullable();
            $table->string('res_line_order_status')->nullable();
            $table->integer('so_line_id')->nullable();
            $table->double('secondary_unit_quantity')->default(0);
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sell_line_id');
            $table->integer('purchase_line_id');
            $table->double('quantity')->default(1);
            $table->double('qty_returned')->default(0);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
            $table->integer('business_id')->default(1);
            $table->boolean('enable_stock')->default(true);
            $table->integer('unit_id')->nullable();
        });
        Schema::create('units', function (Blueprint $table) {
            $table->integer('id');
            $table->boolean('allow_decimal')->default(false);
            $table->softDeletes();
        });
        DB::table('units')->insert(['id' => 1]);
        Schema::create('variations', function (Blueprint $table) {
            $table->integer('id');
            $table->string('sub_sku');
            $table->integer('product_id')->default(589);
            $table->integer('product_variation_id')->default(1);
            $table->softDeletes();
        });
        foreach (['business', 'business_locations'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->integer('id');
                $table->string('name');
                $table->integer('business_id')->default(1);
            });
        }
        Schema::table('business', function (Blueprint $table) {
            $table->integer('currency_id')->default(1);
            $table->text('pos_settings')->nullable();
        });
        Schema::create('currencies', function (Blueprint $table) {
            $table->integer('id');
        });
        DB::table('business')->insert(['id' => 1, 'name' => 'Test business']);
        DB::table('currencies')->insert(['id' => 1]);
        DB::table('products')->insert(['id' => 589, 'name' => 'Serial phone']);
        DB::table('variations')->insert(['id' => 2701, 'sub_sku' => 'PHONE-1']);
        DB::table('transactions')->insert(['id' => 1]);
        DB::table('transactions')->insert(['id' => 2, 'type' => 'purchase', 'status' => 'received']);
        DB::table('purchase_lines')->insert([
            ['id' => 1, 'lot_number' => 'INVOICE-LOT'],
            ['id' => 2, 'lot_number' => 'OTHER-LOT'],
            ['id' => 3, 'lot_number' => 'INVOICE-LOT'],
        ]);
        DB::table('transaction_sell_lines')->insert(['id' => 1, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 2]);
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id');
            $table->integer('variation_id');
            $table->integer('product_variation_id');
            $table->integer('location_id');
            $table->double('qty_available');
            $table->timestamps();
        });
        Schema::create('warranties', function (Blueprint $table) {
            $table->integer('id');
        });
        Schema::create('sell_line_warranties', function (Blueprint $table) {
            $table->integer('sell_line_id');
            $table->integer('warranty_id');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('lot_consistency_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function historyLots()
    {
        $controller = $this->app->make(LotController::class);
        $method = new \ReflectionMethod($controller, 'lotSaleLinesQuery');
        $method->setAccessible(true);

        return $method->invoke($controller, 1)->pluck('pl.lot_number')->all();
    }

    public function test_history_matches_invoice_despite_reassigned_or_multiple_allocations(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 3]);
        $this->assertSame(['INVOICE-LOT'], $this->historyLots());
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $this->assertSame(['INVOICE-LOT'], $this->historyLots());
    }

    public function test_history_uses_allocations_when_no_lot_was_selected(): void
    {
        foreach ([null, 0] as $lotId) {
            DB::table('transaction_sell_lines')->update(['lot_no_line_id' => $lotId]);
            $this->assertSame(['OTHER-LOT'], $this->historyLots());
        }
    }

    public function test_history_excludes_drafts_transfers_and_other_businesses(): void
    {
        foreach ([['status' => 'draft'], ['type' => 'sell_transfer'], ['business_id' => 2]] as $attributes) {
            DB::table('transactions')->where('id', 1)->update($attributes);
            $this->assertSame([], $this->historyLots());
            DB::table('transactions')->where('id', 1)->update(['status' => 'final', 'type' => 'sell', 'business_id' => 1]);
        }
    }

    private function receiveLot(int $id): void
    {
        $purchase = PurchaseLine::findOrFail($id);
        $purchase->setRelation('product', new Product(['enable_stock' => 1]));
        $transaction = new Transaction(['business_id' => 1, 'location_id' => 12, 'status' => 'received']);
        $transaction->setRelation('purchase_lines', collect([$purchase]));
        $this->app->make(ProductUtil::class)->adjustStockOverSelling($transaction);
    }

    public function test_receipt_does_not_fill_oversold_sale_of_a_different_serial(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(2);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(0, PurchaseLine::find(2)->quantity_sold);
        $this->receiveLot(3);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->receiveLot(1);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_receipt_can_fill_sale_with_no_selected_lot(): void
    {
        DB::table('purchase_lines')->where('id', 2)->update(['lot_number' => null]);
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(2);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_named_lot_receipt_cannot_silently_fill_anonymous_sale(): void
    {
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(2);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(0, PurchaseLine::find(2)->quantity_sold);
    }

    public function test_receipt_cannot_reallocate_unfilled_transfer_even_for_the_same_selected_lot(): void
    {
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer']);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(1);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_receipt_fills_only_outstanding_units_and_preserves_return_totals(): void
    {
        DB::table('purchase_lines')->where('id', 2)->update(['lot_number' => null]);
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null, 'quantity' => 3, 'quantity_returned' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0, 'quantity' => 3, 'qty_returned' => 1]);
        $this->receiveLot(2);
        $rows = DB::table('transaction_sell_lines_purchase_lines');
        $this->assertEquals(3, $rows->sum('quantity'));
        $this->assertEquals(1, $rows->sum('qty_returned'));
        $this->assertEquals(1, PurchaseLine::find(2)->quantity_sold);
        $this->assertEquals(2, (clone $rows)->where('purchase_line_id', 0)->value('quantity'));
        DB::table('purchase_lines')->where('id', 3)->update(['lot_number' => null]);
        $this->receiveLot(3);
        $this->assertEquals(1, PurchaseLine::find(3)->quantity_sold);
        $this->assertEquals(1, $rows->sum('qty_returned'));
        $this->assertEquals(3, $rows->sum('quantity'));
    }

    public function test_fully_returned_oversale_does_not_consume_new_receipt(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0, 'qty_returned' => 1]);
        $this->receiveLot(1);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_receipt_does_not_fill_another_business_sale(): void
    {
        DB::table('transactions')->update(['business_id' => 2]);
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(2);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    private function mapLines($lines): void
    {
        $this->app->make(TransactionUtil::class)->mapPurchaseSell([
            'id' => 1, 'location_id' => 12, 'accounting_method' => 'fifo',
            'pos_settings' => ['allow_overselling' => true],
        ], $lines, 'purchase', false);
    }

    public function test_selected_lot_is_deducted_and_displayed_on_invoice(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $this->mapLines([TransactionSellLine::find(1)]);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $this->assertSame(['INVOICE-LOT'], TransactionSellLine::find(1)->invoice_lots->pluck('lot_number')->all());
    }

    public function test_selected_lot_cannot_oversell_and_partial_changes_roll_back(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->update(['quantity' => 2]);
        try {
            $this->mapLines([TransactionSellLine::find(1)]);
            $this->fail('Selected lot shortage must reject the sale.');
        } catch (PurchaseSellMismatch $exception) {
            $detail = $exception->responseData()['stock_error'];
            $this->assertEquals(1, $detail['recorded_available']);
            $this->assertEquals(1, $detail['history_available']);
            $this->assertEquals(2, $detail['requested_quantity']);
            $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
            $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        }
    }

    public function test_duplicate_selected_serial_rejects_entire_sale(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 1]);
        try {
            $this->mapLines(TransactionSellLine::orderBy('id')->get());
            $this->fail('Two sale lines cannot consume the same single unit.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
            $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        }
    }

    public function test_selected_lot_must_match_business_location_variation_and_received_status(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        foreach ([['business_id' => 2], ['location_id' => 99], ['status' => 'pending']] as $attributes) {
            DB::table('transactions')->where('id', 2)->update($attributes);
            try {
                $this->mapLines([TransactionSellLine::find(1)]);
                $this->fail('Unavailable selected lot must reject the sale.');
            } catch (PurchaseSellMismatch $exception) {
                $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
            }
            DB::table('transactions')->where('id', 2)->update(['business_id' => 1, 'location_id' => 12, 'status' => 'received']);
        }
        DB::table('purchase_lines')->where('id', 1)->update(['variation_id' => 99]);
        $this->expectException(PurchaseSellMismatch::class);
        $this->mapLines([TransactionSellLine::find(1)]);
    }

    public function test_lot_stock_cannot_oversell_without_an_explicit_selection(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null, 'quantity' => 4]);
        $this->expectException(PurchaseSellMismatch::class);
        $this->mapLines([TransactionSellLine::find(1)]);
    }

    public function test_invoice_displays_allocated_lots_when_none_was_selected(): void
    {
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        $this->assertSame(['OTHER-LOT'], TransactionSellLine::find(1)->invoice_lots->pluck('lot_number')->all());
    }

    public function test_return_restores_selected_lot_and_cannot_exceed_sale_quantity(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $this->mapLines([TransactionSellLine::find(1)]);
        $util = $this->app->make(TransactionUtil::class);
        $util->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 1, 0, false);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->value('qty_returned'));
        $this->expectException(PurchaseSellMismatch::class);
        $util->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 2, 1, false);
    }

    public function test_multi_batch_deletion_reverses_each_allocation_exactly_once(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('purchase_lines')->whereIn('id', [1, 2])->update(['quantity_sold' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 1],
        ]);
        $method = new \ReflectionMethod(TransactionUtil::class, 'mapDecrementPurchaseQuantity');
        $method->setAccessible(true);
        DB::transaction(fn () => $method->invoke($this->app->make(TransactionUtil::class), 1, 2));
        $this->assertEquals([0, 0], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
    }

    public function test_lot_availability_uses_invoice_selection_and_does_not_double_count_transfer_mapping(): void
    {
        $sql = $this->app->make(TransactionUtil::class)->lotQuantityAvailableSql('p');
        $available = fn () => DB::table('purchase_lines as p')->orderBy('p.id')->selectRaw($sql.' as available')->pluck('available')->all();
        $this->assertEquals([0, 1, 1], $available());
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer']);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 1]);
        $this->assertEquals([0, 1, 1], $available());
        DB::table('transactions')->where('id', 1)->update(['status' => 'draft']);
        $this->assertEquals([1, 1, 1], $available());
    }

    private function prepareProductStockDetailsSchema(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('single');
            $table->string('sku')->default('PHONE-1');
        });
        Schema::table('units', fn (Blueprint $table) => $table->string('short_name')->default('Pc'));
        Schema::table('variations', function (Blueprint $table) {
            $table->string('name')->default('Default');
            $table->double('sell_price_inc_tax')->default(100);
        });
        Schema::create('product_variations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });
        DB::table('product_variations')->insert(['id' => 1, 'name' => 'Default']);
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id');
            $table->integer('variation_id');
            $table->double('quantity');
        });
        DB::table('variation_location_details')->insert([
            'product_id' => 589, 'variation_id' => 2701, 'product_variation_id' => 1, 'location_id' => 12, 'qty_available' => 3,
        ]);
    }

    public function test_detector_fix_action_applies_product_stock_details_quantity_and_redirects_back(): void
    {
        $this->prepareProductStockDetailsSchema();
        $this->prepareDetectorFixLogSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('can')->with('stock_inventory.fix')->andReturn(true);
        $user->shouldReceive('permitted_locations')->with(1)->andReturn([12]);
        $this->actingAs($user);
        session(['user.business_id' => 1]);
        $request = Request::create('/smart-stock-inventory/mismatch/fix-auto', 'POST', [
            'product_id' => 589, 'variation_id' => 2701, 'location_id' => 12,
            'problem_type' => 'mismatch_stock', 'reason' => 'Reconcile quantity using Product Stock Details',
            'stock' => 999, // The fix must calculate on the server, ignoring a submitted quantity.
        ], [], [], ['HTTP_REFERER' => 'http://localhost/smart-stock-inventory/mismatch']);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        $telegram = \Mockery::mock(\Modules\SmartStockInventory\Services\TelegramAlertService::class);
        $telegram->shouldReceive('send')->once()->andThrow(new \RuntimeException('Notification unavailable'));
        $controller = new \Modules\SmartStockInventory\Http\Controllers\MismatchController(
            app(\App\Utils\Util::class), app(\Modules\SmartStockInventory\Services\StockFixService::class), $telegram
        );
        $response = $controller->fixAuto($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/smart-stock-inventory/mismatch', $response->getTargetUrl());
        $this->assertEquals(1, session('status.success'));
        $this->assertSame('Stock quantity corrected using Product Stock Details.', session('status.msg'));
        $this->assertEquals(2, DB::table('variation_location_details')->value('qty_available'));
        $this->assertEquals(1, DB::table('smart_stock_fix_logs')->count());
        $this->assertSame([], app(\Modules\SmartStockInventory\Services\StockFixService::class)->detectMismatch([
            'business_id' => 1, 'location_ids' => [12],
        ]));
    }

    public function test_detector_quantity_fix_updates_duplicate_rows_using_product_stock_details_and_logs_changes(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        session(['user.business_id' => 1]);
        $this->prepareDetectorFixLogSchema();
        DB::table('variation_location_details')->insert([
            'product_id' => 589, 'variation_id' => 2701, 'product_variation_id' => 1, 'location_id' => 12, 'qty_available' => 4,
        ]);
        $before = DB::table('purchase_lines')->get()->toJson();
        $log = app(\Modules\SmartStockInventory\Services\StockFixService::class)->fixMismatchStock(589, 2701, 12, 'Verified quantity mismatch');
        $this->assertEquals(7, $log->old_qty);
        $this->assertEquals(2, $log->new_qty);
        $this->assertEquals(2, DB::table('variation_location_details')->value('qty_available'));
        $this->assertEquals(1, DB::table('variation_location_details')->count());
        $this->assertEquals(1, DB::table('smart_stock_action_logs')->count());
        $this->assertSame($before, DB::table('purchase_lines')->get()->toJson());
    }

    public function test_detector_quantity_fix_rolls_back_if_audit_cannot_be_saved(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        session(['user.business_id' => 1]);
        $this->prepareDetectorFixLogSchema();
        Schema::drop('smart_stock_action_logs');
        try {
            app(\Modules\SmartStockInventory\Services\StockFixService::class)->fixMismatchStock(589, 2701, 12);
            $this->fail('Expected audit failure');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertEquals(3, DB::table('variation_location_details')->value('qty_available'));
            $this->assertEquals(0, DB::table('smart_stock_fix_logs')->count());
        }
    }

    private function prepareDetectorFixLogSchema(): void
    {
        Schema::create('smart_stock_fix_logs', function (Blueprint $table) {
            $table->increments('id');
            foreach (['business_id', 'location_id', 'product_id', 'variation_id', 'reference_id', 'is_rollback', 'rollbackable', 'created_by'] as $column) {
                $table->integer($column)->nullable();
            }
            foreach (['fix_type', 'problem_type', 'reason', 'risk_level', 'reference_type', 'before_payload', 'after_payload'] as $column) {
                $table->text($column)->nullable();
            }
            $table->double('old_qty')->nullable();
            $table->double('new_qty')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('smart_stock_action_logs', function (Blueprint $table) {
            $table->increments('id');
            foreach (['user_id', 'business_id', 'record_id', 'location_id', 'reference_id'] as $column) {
                $table->integer($column)->nullable();
            }
            foreach (['user_name', 'module_name', 'table_name', 'action_type', 'reference_type', 'old_data', 'new_data', 'reason', 'ip_address'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_detector_quantity_fix_calculation_matches_product_stock_details_with_transfers_and_returns(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        session(['user.business_id' => 1]);
        DB::table('transactions')->insert([
            ['id' => 20, 'type' => 'opening_stock', 'status' => 'received'],
            ['id' => 21, 'type' => 'purchase_transfer', 'status' => 'received'],
            ['id' => 22, 'type' => 'sell_transfer', 'status' => 'final'],
            ['id' => 23, 'type' => 'stock_adjustment', 'status' => 'final'],
            ['id' => 24, 'type' => 'production_purchase', 'status' => 'received'],
            ['id' => 25, 'type' => 'production_sell', 'status' => 'final'],
        ]);
        DB::table('purchase_lines')->insert([
            ['id' => 20, 'transaction_id' => 20, 'quantity' => 4],
            ['id' => 21, 'transaction_id' => 21, 'quantity' => 5],
            ['id' => 24, 'transaction_id' => 24, 'quantity' => 2],
        ]);
        DB::table('transaction_sell_lines')->insert([
            ['id' => 22, 'transaction_id' => 22, 'quantity' => 3],
            ['id' => 25, 'transaction_id' => 25, 'quantity' => 1],
        ]);
        DB::table('stock_adjustment_lines')->insert(['transaction_id' => 23, 'variation_id' => 2701, 'quantity' => 2]);
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_returned' => 1]);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity_returned' => 1]);
        $util = app(ProductUtil::class);
        $correct = (float) $util->getVariationStockMisMatch(1, 2701, 12)->first()->total_stock_calculated;
        $this->assertEquals(7, $correct);
        $preview = app(\Modules\SmartStockInventory\Services\StockFixService::class)->previewFix(589, 2701, 12, 'mismatch_stock');
        $this->assertEquals($correct, $preview['correct_calculated_qty']);
        $this->assertEquals(3, $preview['current_system_qty']);
        $rows = app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => [12]]);
        $location = collect($rows)->firstWhere('lot', 'All lots at location');
        $this->assertEquals($correct, $location['expected_qty']);
        $this->assertTrue($location['can_fix_quantity']);
        // Overselling balances remain negative rather than silently being reset to zero.
        DB::table('transaction_sell_lines')->where('id', 22)->update(['quantity' => 20]);
        $preview = app(\Modules\SmartStockInventory\Services\StockFixService::class)->previewFix(589, 2701, 12);
        $this->assertEquals(-10, $preview['correct_calculated_qty']);
    }

    public function test_mismatch_page_lists_only_product_stock_details_quantity_mismatches(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        $service = app(\Modules\SmartStockInventory\Services\StockFixService::class);
        $filters = ['business_id' => 1, 'location_ids' => [12]];
        $rows = $service->detectMismatch($filters);
        $this->assertCount(1, $rows);
        $this->assertSame('PHONE-1', $rows[0]['sku']);
        $this->assertEquals(3, $rows[0]['available_qty']);
        $this->assertEquals(2, $rows[0]['expected_qty']);
        $this->assertTrue($rows[0]['can_fix_quantity']);
        // Lot and allocation errors remain in the data but are no longer listed on this page.
        DB::table('variation_location_details')->update(['qty_available' => 2]);
        $this->assertSame([], $service->detectMismatch($filters));
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        // Use Product Stock Details' display comparison, including its rounding rule.
        DB::table('variation_location_details')->update(['qty_available' => 2.25]);
        $this->assertSame([], $service->detectMismatch($filters));
        DB::table('variation_location_details')->update(['qty_available' => -1]);
        $this->assertCount(1, $service->detectMismatch($filters));
        $this->assertSame([], $service->detectMismatch(['business_id' => 1, 'location_ids' => []]));
        $this->assertSame([], $service->detectMismatch(['business_id' => 2, 'location_ids' => [12]]));
        $this->assertSame([], $service->detectMismatch($filters + ['purchase_ref' => 'NO-MATCH']));
    }

    public function test_mismatch_page_hides_negative_stock_when_product_stock_details_balances(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity' => 5]);
        DB::table('variation_location_details')->update(['qty_available' => -2]);
        $this->assertSame([], app(\Modules\SmartStockInventory\Services\StockFixService::class)->detectMismatch([
            'business_id' => 1, 'location_ids' => [12],
        ]));
    }

    public function test_mismatch_detector_lists_sku_lots_quantities_and_positive_stock_errors_without_writes(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        $before = DB::table('purchase_lines')->get()->toJson();
        $rows = app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => [12]]);
        $allocation = collect($rows)->first(fn ($row) => str_contains($row['problem'], 'Stock allocation differs'));
        $this->assertNotNull($allocation);
        $this->assertSame('PHONE-1', $allocation['sku']);
        $this->assertStringContainsString('INVOICE-LOT', $allocation['lot']);
        $this->assertStringContainsString('OTHER-LOT', $allocation['allocated_lots']);
        $this->assertTrue($allocation['can_fix_quantity']);
        $this->assertTrue(collect($rows)->every(fn ($row) => $row['can_fix_quantity']));
        $this->assertEquals(1, $allocation['quantity']);
        $this->assertEquals(1, $allocation['expected_qty']);
        $this->assertNotEmpty(collect($rows)->filter(fn ($row) => str_contains($row['problem'], 'Recorded batch balance')));
        $this->assertSame($before, DB::table('purchase_lines')->get()->toJson());
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        DB::table('variation_location_details')->update(['qty_available' => 2]);
        $balanced = app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => [12]]);
        $this->assertFalse(collect($balanced)->first(fn ($row) => str_contains($row['problem'], 'Stock allocation differs'))['can_fix_quantity']);
        $this->assertSame([], app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => []]));
        $this->assertSame([], app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 2, 'location_ids' => [12]]));
    }

    public function test_mismatch_detector_includes_unlabelled_batches_and_more_than_500_errors(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        $batches = [];
        for ($id = 10; $id < 511; $id++) {
            $batches[] = ['id' => $id, 'quantity' => 1, 'quantity_sold' => 2, 'lot_number' => null];
        }
        DB::table('purchase_lines')->insert($batches);
        $rows = app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => [12]]);
        $this->assertCount(501, collect($rows)->filter(fn ($row) => str_contains($row['lot'] ?? '', 'No lot · Batch #')));
        $this->assertSame([], app(\App\Services\StockMismatchDetectorService::class)->detect([
            'business_id' => 1, 'location_ids' => [12], 'purchase_ref' => 'NO-MATCH',
        ]));
    }

    public function test_mismatch_detector_reports_fifo_missing_allocations(): void
    {
        $this->prepareProductStockDetailsSchema();
        DB::table('business_locations')->insert(['id' => 12, 'name' => 'Main warehouse']);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $rows = app(\App\Services\StockMismatchDetectorService::class)->detect(['business_id' => 1, 'location_ids' => [12]]);
        $fifo = collect($rows)->firstWhere('lot', 'Automatic / FIFO');
        $this->assertNotNull($fifo);
        $this->assertStringContainsString('Allocated quantity differs', $fifo['problem']);
        $this->assertEquals(0, $fifo['available_qty']);
        $this->assertEquals(1, $fifo['expected_qty']);
    }

    public function test_audit_reports_mismatch_without_changing_stock_or_allocations(): void
    {
        $report = $this->app->make(LotStockAuditService::class)->audit(1);
        $this->assertCount(1, $report['sale_line_issues']);
        $this->assertSame('INVOICE-LOT', $report['sale_line_issues'][0]['selected_lot']);
        $this->assertCount(1, $report['stock_issues']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_deleting_a_returned_allocation_does_not_restore_stock_twice(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->update(['quantity' => 2, 'qty_returned' => 1]);
        DB::table('purchase_lines')->where('id', 2)->update(['quantity_sold' => 1]);
        $method = new \ReflectionMethod(TransactionUtil::class, 'mapDecrementPurchaseQuantity');
        $method->setAccessible(true);
        DB::transaction(fn () => $method->invoke($this->app->make(TransactionUtil::class), 1, 2));
        $this->assertEquals(0, PurchaseLine::find(2)->quantity_sold);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
    }

    public function test_audit_detects_future_allocated_receipt_despite_an_earlier_selected_lot(): void
    {
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer']);
        DB::table('transactions')->insert(['id' => 3, 'type' => 'purchase_transfer', 'status' => 'received', 'transaction_date' => '2026-09-02 12:00:00']);
        DB::table('purchase_lines')->where('id', 2)->update(['transaction_id' => 3]);
        $report = $this->app->make(LotStockAuditService::class)->audit(1);
        $this->assertContains('Stock allocation uses a receipt dated after the transfer.', $report['sale_line_issues'][0]['reasons']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_transfer_edit_review_is_scoped_and_shows_selected_and_allocated_serials(): void
    {
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer', 'ref_no' => 'ST-REVIEW']);
        DB::table('transactions')->insert(['id' => 3, 'ref_no' => 'UNRELATED']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 3, 'lot_no_line_id' => 3]);
        $this->transferController();
        $report = $this->app->make(LotStockAuditService::class)->audit(1, null, 1);
        $this->assertEquals(1, $report['checked_selected_sale_lines']);
        $this->assertEquals(1, $report['transaction_filter']);
        $this->assertSame([], $this->app->make(LotStockAuditService::class)->audit(2, null, 1)['sale_line_issues']);
        $gate = \Mockery::mock(\Illuminate\Contracts\Auth\Access\Gate::class);
        $gate->shouldReceive('check')->andReturn(true);
        $this->app->instance(\Illuminate\Contracts\Auth\Access\Gate::class, $gate);
        $this->statusRequest('pending');
        $html = view('stock_transfer.partials.stock_review', ['stockReview' => $report, 'sell_transfer' => Transaction::find(1)])->render();
        $this->assertStringContainsString('ST-REVIEW', $html);
        $this->assertStringContainsString('id="auto_correct_stock"', $html);
        $this->assertStringContainsString('/stock-transfers/1/auto-correct', $html);
        $this->assertStringContainsString('correct-stock-line', $html);
        $this->assertStringContainsString('data-line-id="1"', $html);
        $this->assertStringNotContainsString('data-line-id="2"', $html);
        $this->assertStringContainsString('INVOICE-LOT', $html);
        $this->assertStringContainsString('OTHER-LOT', $html);
        $this->assertStringNotContainsString('UNRELATED', $html);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_transfer_edit_review_escapes_serials_and_reasons(): void
    {
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer']);
        DB::table('purchase_lines')->where('id', 1)->update(['lot_number' => '<script>alert(1)</script>']);
        $report = $this->app->make(LotStockAuditService::class)->audit(1, null, 1);
        $this->statusRequest('pending');
        $html = view('stock_transfer.partials.stock_review', ['stockReview' => $report, 'sell_transfer' => Transaction::find(1)])->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_lot_history_review_exposes_conflicting_deduction_without_changing_history(): void
    {
        $this->transferController();
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer', 'ref_no' => 'ST-CONFLICT']);
        DB::table('purchase_lines')->where('id', 2)->update(['quantity_sold' => 1]);
        $method = new \ReflectionMethod(LotController::class, 'lotStockReview');
        $method->setAccessible(true);
        $controller = $this->app->make(LotController::class);
        $report = $method->invoke($controller, 1, 'OTHER-LOT', 'all');
        $this->assertCount(1, $report['allocations']);
        $this->assertCount(1, $report['balances']);
        $this->assertSame('INVOICE-LOT', $report['allocations'][0]->selected_serial);
        $this->assertSame('OTHER-LOT', $report['allocations'][0]->allocated_serial);
        $this->assertEquals(0, $report['balances'][0]->recorded_available);
        $this->assertEquals(1, $report['balances'][0]->history_available);
        $this->assertStringContainsString('/stock-transfers/1/edit', $report['allocations'][0]->review_url);
        $this->statusRequest('pending');
        $html = view('smartstockinventory::lot.partials.stock_review', ['stockReview' => $report])->render();
        $this->assertStringContainsString('ST-CONFLICT', $html);
        $this->assertStringContainsString('not confirmed available stock', $html);
        $this->assertStringContainsString('Correct This Line', $html);
        $this->assertStringContainsString('?correct_line=1', $html);
        $this->assertCount(0, $method->invoke($controller, 1, 'OTHER-LOT', [13])['allocations']);
        $this->assertCount(0, $method->invoke($controller, 1, 'OTHER-LOT', 'all', 13)['balances']);
        $this->assertCount(0, $method->invoke($controller, 2, 'OTHER-LOT', 'all')['allocations']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_lot_history_review_does_not_link_records_without_review_permission(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturn(false);
        $this->actingAs($user);
        $method = new \ReflectionMethod(LotController::class, 'lotStockReview');
        $method->setAccessible(true);
        $report = $method->invoke($this->app->make(LotController::class), 1, 'OTHER-LOT', 'all');
        $this->assertNull($report['allocations'][0]->review_url);
        $this->assertNull($report['allocations'][0]->correction_url);
    }

    public function test_lot_history_super_admin_can_open_authorized_transfer_line_correction(): void
    {
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('can')->andReturnUsing(fn ($ability) => $ability === 'superadmin');
        $this->actingAs($user);
        $method = new \ReflectionMethod(LotController::class, 'lotStockReview');
        $method->setAccessible(true);

        $report = $method->invoke($this->app->make(LotController::class), 1, 'OTHER-LOT', 'all');

        $this->assertStringContainsString('/stock-transfers/1/edit?correct_line=1', $report['allocations'][0]->correction_url);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    private function counterRepairTransfer(): \App\Services\StockTransferReconciliationService
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 0]);

        return $this->app->make(\App\Services\StockTransferReconciliationService::class);
    }

    public function test_correction_plan_generation_is_read_only_and_lists_verified_counter_change(): void
    {
        $service = $this->counterRepairTransfer();
        $plan = $service->generate(1, 1);
        $this->assertSame('ready', $plan['status']);
        $this->assertCount(1, $plan['counter_repairs']);
        $this->assertEquals(0, $plan['counter_repairs'][0]['quantity_sold_before']);
        $this->assertEquals(1, $plan['counter_repairs'][0]['quantity_sold_after']);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([2, 1], $this->transferBalances());
    }

    public function test_verified_counter_repair_changes_no_serials_allocations_or_location_stock(): void
    {
        $service = $this->counterRepairTransfer();
        $before = DB::table('transaction_sell_lines_purchase_lines')->get()->toJson();
        $plan = $service->generate(1, 1);
        $repairs = $service->apply(1, 1, $plan['token']);
        $this->assertCount(1, $repairs);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertSame($before, DB::table('transaction_sell_lines_purchase_lines')->get()->toJson());
        $this->assertSame('INVOICE-LOT', Transaction::find(3)->purchase_lines()->value('lot_number'));
        $this->assertSame('consistent', $service->generate(1, 1)['status']);
        $this->expectException(PurchaseSellMismatch::class);
        $service->apply(1, 1, $plan['token']);
    }

    public function test_correction_plan_rejects_stale_evidence_without_writing_counters(): void
    {
        $service = $this->counterRepairTransfer();
        $plan = $service->generate(1, 1);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity' => 2]);
        try {
            $service->apply(1, 1, $plan['token']);
            $this->fail('A changed movement must invalidate the proposal.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('changed', $e->getMessage());
        }
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_correction_plan_blocks_wrong_serial_and_future_receipt_instead_of_guessing(): void
    {
        $service = $this->counterRepairTransfer();
        DB::table('transactions')->insert(['id' => 4, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-09-02 12:00:00']);
        DB::table('purchase_lines')->where('id', 2)->update(['transaction_id' => 4, 'quantity_sold' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 2]);
        $plan = $service->generate(1, 1);
        $this->assertSame('verification_required', $plan['status']);
        $this->assertSame([], $plan['counter_repairs']);
        $this->assertStringContainsString('after the transfer', implode(' ', $plan['blockers']));
        try {
            $service->apply(1, 1, $plan['token']);
            $this->fail('Ambiguous stock must not be auto-corrected.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('cannot be applied', $e->getMessage());
        }
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
    }

    public function test_correction_plan_blocks_orphaned_allocations(): void
    {
        $service = $this->counterRepairTransfer();
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 999, 'purchase_line_id' => 1]);
        $plan = $service->generate(1, 1);
        $this->assertSame('verification_required', $plan['status']);
        $this->assertStringContainsString('orphaned', implode(' ', $plan['blockers']));
    }

    public function test_correction_plan_endpoint_checks_location_access_and_returns_proposal(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn([12, 13]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $result = $controller->generateCorrectionPlan($request, 1)->getData(true);
        $this->assertSame('ready', $result['plan']['status']);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_correction_plan_endpoint_denies_hidden_related_stock_history(): void
    {
        $this->counterRepairTransfer();
        DB::table('transactions')->insert(['id' => 4, 'location_id' => 14, 'status' => 'draft']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn([12, 13]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->generateCorrectionPlan($request, 1);
    }

    public function test_correction_plan_endpoint_denies_unpermitted_receiving_location(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn([12]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->generateCorrectionPlan($request, 1);
    }

    public function test_correction_endpoint_denies_hidden_receiving_dependencies(): void
    {
        $this->counterRepairTransfer();
        DB::table('transactions')->insert(['id' => 4, 'location_id' => 14, 'status' => 'draft']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4,
            'lot_no_line_id' => DB::table('purchase_lines')->where('transaction_id', 3)->value('id')]);
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn([12, 13]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->manualCorrectionOptions($request, 1);
    }

    public function test_configured_super_admin_can_correct_without_staff_or_location_permissions(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->username = 'cashier-test';
        $user->shouldReceive('can')->andReturn(false);
        $user->shouldReceive('permitted_locations')->never();
        $this->actingAs($user);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $result = $controller->generateCorrectionPlan($request, 1)->getData(true);

        $this->assertSame('ready', $result['plan']['status']);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_super_admin_ability_grants_correction_access_without_staff_permissions(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => '']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->username = 'super-admin-test';
        $user->shouldReceive('can')->with('superadmin')->andReturn(true);
        $user->shouldReceive('can')->with('stock_transfer.update')->never();
        $user->shouldReceive('permitted_locations')->never();
        $this->actingAs($user);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => $user);

        $result = $controller->manualCorrectionOptions($request, 1)->getData(true);

        $this->assertSame(1, $result['success']);
        $this->assertNotEmpty($result['lines']);
    }

    public function test_super_admin_correction_access_remains_scoped_to_active_business(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        DB::table('transactions')->where('id', 1)->update(['business_id' => 2]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->generateCorrectionPlan($request, 1);
    }

    public function test_apply_correction_endpoint_requires_confirmation_and_logs_verified_changes(): void
    {
        $service = $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['token' => $service->generate(1, 1)['token'], 'confirmed' => 1]);
        $util = $this->app->make(TransactionUtil::class);
        $response = $controller->applyCorrectionPlan($request, 1);
        $util->shouldHaveReceived('activityLog')->once()->withArgs(fn ($transfer, $event, $before, $properties) => $event === 'stock_reconciled'
            && $transfer->id === 1 && count($properties['counter_repairs']) === 1);
        $this->assertEquals(1, $response->getData(true)['success']);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_apply_correction_endpoint_without_confirmation_changes_nothing(): void
    {
        $service = $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['token' => $service->generate(1, 1)['token']]);
        try {
            $controller->applyCorrectionPlan($request, 1);
            $this->fail('Confirmation is required.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirmed', $e->errors());
        }
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_auto_correction_repairs_proven_counters_logs_and_is_repeat_safe(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $result = $controller->autoCorrectStock($request, 1)->getData(true);

        $this->assertTrue($result['applied']);
        $this->assertTrue($result['complete']);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $after = $this->stockCorrectionSnapshot();
        $beforeTables = json_decode($before, true, 512, JSON_THROW_ON_ERROR);
        $afterTables = json_decode($after, true, 512, JSON_THROW_ON_ERROR);
        foreach ([1, 2, 3, 4] as $key) {
            $this->assertEquals($beforeTables[$key], $afterTables[$key]);
        }
        $repeat = $controller->autoCorrectStock($request, 1)->getData(true);
        $this->assertFalse($repeat['applied']);
        $this->assertTrue($repeat['complete']);
        $this->assertSame('consistent', $repeat['plan']['status']);
        $this->assertEquals($after, $this->stockCorrectionSnapshot());
        $this->app->make(TransactionUtil::class)->shouldHaveReceived('activityLog')->once()
            ->withArgs(fn ($transfer, $event, $previous, $properties) => $event === 'stock_reconciled'
                && $properties['mode'] === 'automatic' && count($properties['counter_repairs']) === 1);
    }

    public function test_auto_correction_does_not_guess_conflicting_serials(): void
    {
        $this->manualCorrectionFixture();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $result = $controller->autoCorrectStock($request, 1)->getData(true);

        $this->assertFalse($result['applied']);
        $this->assertFalse($result['complete']);
        $this->assertSame('verification_required', $result['plan']['status']);
        $this->assertNotEmpty($result['plan']['blockers']);
        $lineReview = collect($result['plan']['review_items'])->firstWhere('line_id', 1);
        $this->assertNotNull($lineReview);
        $this->assertStringContainsString('/stock-transfers/1/edit?correct_line=1', $lineReview['url']);
        $this->assertNotEmpty($lineReview['reasons']);
        $this->assertEquals($before, $this->stockCorrectionSnapshot());
        $this->app->make(TransactionUtil::class)->shouldNotHaveReceived('activityLog');
    }

    public function test_admin_auto_correction_aligns_selected_allocated_and_receiving_serials(): void
    {
        [, , $destinationId] = $this->manualCorrectionFixture();
        $controller = $this->transferController();
        $user = auth()->user();
        $user->business_id = 1;
        $user->username = 'KY0021';
        $user->shouldReceive('hasRole')->with('Admin#1')->andReturn(true);
        $user->shouldReceive('permitted_locations')->never();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => $user);
        $beforeBalances = $this->transferBalances();
        $beforeDate = Transaction::find(1)->transaction_date;

        $result = $controller->autoCorrectStock($request, 1)->getData(true);

        $this->assertTrue($result['complete']);
        $this->assertTrue($result['applied']);
        $this->assertEquals(2, TransactionSellLine::find(1)->lot_no_line_id);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertSame('OTHER-LOT', PurchaseLine::find($destinationId)->lot_number);
        $this->assertEquals([0, 1], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertEquals($beforeBalances, $this->transferBalances());
        $this->assertEquals($beforeDate, Transaction::find(1)->transaction_date);
        $this->assertSame('consistent', app(\App\Services\StockTransferReconciliationService::class)->generate(1, 1)['status']);
        $repeat = $controller->autoCorrectStock($request, 1)->getData(true);
        $this->assertTrue($repeat['complete']);
        $this->assertFalse($repeat['applied']);
    }

    private function relatedAllocationCorrectionFixture(): StockTransferController
    {
        $this->manualCorrectionFixture();
        DB::table('transactions')->insert([
            ['id' => 4, 'type' => 'sell_transfer', 'status' => 'final', 'location_id' => 12,
                'transfer_parent_id' => null, 'transaction_date' => '2026-08-31 12:00:00'],
            ['id' => 5, 'type' => 'purchase', 'status' => 'received', 'location_id' => 12,
                'transfer_parent_id' => null, 'transaction_date' => '2026-08-30 12:00:00'],
            ['id' => 6, 'type' => 'purchase_transfer', 'status' => 'received', 'location_id' => 13,
                'transfer_parent_id' => 4, 'transaction_date' => '2026-08-31 12:00:00'],
        ]);
        DB::table('purchase_lines')->where('id', 3)->update(['transaction_id' => 5]);
        DB::table('purchase_lines')->insert(['id' => 6, 'transaction_id' => 6, 'lot_number' => 'INVOICE-LOT']);
        DB::table('transaction_sell_lines')->insert(['id' => 66356, 'transaction_id' => 4, 'lot_no_line_id' => 3]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 66356, 'purchase_line_id' => 2]);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);

        return $controller;
    }

    public function test_admin_auto_correction_repairs_related_allocation_from_a_later_receipt(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $beforeTransactions = DB::table('transactions')->orderBy('id')->get()->toJson();
        $beforeBalances = $this->transferBalances();

        $response = $controller->autoCorrectStock($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['complete']);
        $this->assertEquals(3, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->value('purchase_line_id'));
        $this->assertEquals([0, 1, 1], PurchaseLine::whereIn('id', [1, 2, 3])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertSame($beforeTransactions, DB::table('transactions')->orderBy('id')->get()->toJson());
        $this->assertEquals($beforeBalances, $this->transferBalances());
        $this->assertSame([], app(\App\Services\LotStockAuditService::class)->audit(1, null, 4)['sale_line_issues']);
        $this->app->make(TransactionUtil::class)->shouldHaveReceived('activityLog')->once()
            ->withArgs(fn ($transfer, $event, $before, $properties) => count($properties['related_allocation_repairs']) === 1
                && $properties['related_allocation_repairs'][0]['sell_line_id'] === 66356);
    }

    public function test_related_allocation_correction_rolls_back_if_receiving_serial_disagrees(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('purchase_lines')->where('id', 6)->update(['lot_number' => 'UNVERIFIED-SERIAL']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->autoCorrectStock($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Related line #66356', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->app->make(TransactionUtil::class)->shouldNotHaveReceived('activityLog');
    }

    public function test_related_allocation_correction_rolls_back_if_selected_receipt_is_too_late(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('transactions')->where('id', 5)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->autoCorrectStock($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Related line #66356', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function relatedManualCorrectionRequest(): Request
    {
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 2, 'destination_id' => 4]],
            'evidence' => 'Selected receipt and receiving serial verified against delivery documents.', 'verified' => '1']);

        return $request;
    }

    public function test_admin_manual_preview_and_apply_include_related_allocation_repair(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        $request = $this->relatedManualCorrectionRequest();
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertCount(1, $plan['related_allocation_repairs']);
        $this->assertSame(66356, $plan['related_allocation_repairs'][0]['sell_line_id']);
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $applied = $controller->applyManualCorrection($request, 1);
        $this->assertSame(200, $applied->getStatusCode());
        $this->assertEquals(3, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->value('purchase_line_id'));
        $this->assertEquals([0, 1, 1], PurchaseLine::whereIn('id', [1, 2, 3])->orderBy('id')->pluck('quantity_sold')->all());
        $this->app->make(TransactionUtil::class)->shouldHaveReceived('activityLog')->once()
            ->withArgs(fn ($transfer, $event, $before, $properties) => $event === 'stock_transfer_corrected'
                && count($properties['related_allocation_repairs']) === 1);
    }

    public function test_admin_manual_apply_rejects_changed_related_receipt_after_preview(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        $request = $this->relatedManualCorrectionRequest();
        $plan = $controller->previewManualCorrection($request, 1)->getData(true)['plan'];
        DB::table('purchase_lines')->where('id', 3)->update(['quantity_sold' => 5]);
        $before = $this->stockCorrectionSnapshot();
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);

        $response = $controller->applyManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Preview the correction again', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->app->make(TransactionUtil::class)->shouldNotHaveReceived('activityLog');
    }

    public function test_staff_manual_preview_cannot_repair_related_documents(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        config(['constants.administrator_usernames' => '']);
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->relatedManualCorrectionRequest();
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Related line #66356', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function existingSourceShortageFixture(): StockTransferController
    {
        $this->counterRepairTransfer();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->update(['purchase_line_id' => 0]);
        DB::table('transactions')->insert([
            ['id' => 4, 'type' => 'sell_transfer', 'status' => 'final', 'location_id' => 12, 'transfer_parent_id' => null],
            ['id' => 5, 'type' => 'purchase_transfer', 'status' => 'received', 'location_id' => 13, 'transfer_parent_id' => 4],
        ]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);
        DB::table('purchase_lines')->insert(['id' => 5, 'transaction_id' => 5, 'lot_number' => 'INVOICE-LOT']);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);

        return $controller;
    }

    public function test_admin_can_repair_existing_allocation_without_worsening_source_shortage(): void
    {
        $controller = $this->existingSourceShortageFixture();
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $before = $this->stockCorrectionSnapshot();
        $balances = $this->transferBalances();
        $options = $controller->manualCorrectionOptions($request, 1)->getData(true);
        $candidate = collect($options['lines'][0]['recommendation']['candidates'])->firstWhere('source_id', 1);
        $this->assertTrue($candidate['selectable']);
        $this->assertFalse($options['lines'][0]['recommendation']['prefill']);

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertCount(1, $plan['remaining_stock_shortages']);
        $shortage = $plan['remaining_stock_shortages'][0];
        $this->assertEquals(-1, $shortage['available_before']);
        $this->assertEquals(-1, $shortage['available_after']);
        $this->assertCount(2, $shortage['movements']);
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $applied = $controller->applyManualCorrection($request, 1);
        $this->assertSame(200, $applied->getStatusCode());
        $this->assertStringContainsString('shortage remains', $applied->getData(true)['msg']);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('purchase_line_id'));
        $this->assertEquals(1, PurchaseLine::find(1)->quantity);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals($balances, $this->transferBalances());
    }

    public function test_admin_cannot_introduce_a_new_source_shortage_through_correction(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 0]);
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('negative stock', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_staff_cannot_confirm_a_source_shortage(): void
    {
        $controller = $this->existingSourceShortageFixture();
        config(['constants.administrator_usernames' => '']);
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('negative stock', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function usedReceivingBatchFixture(): StockTransferController
    {
        $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 3)->update(['transaction_date' => '2026-09-02 12:00:00']);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'sell', 'status' => 'final', 'location_id' => 13,
            'transaction_date' => '2026-09-03 12:00:00']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 4]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 4]);
        DB::table('purchase_lines')->where('id', 4)->update(['quantity_sold' => 1]);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);

        return $controller;
    }

    public function test_admin_repairs_source_allocation_without_modifying_used_receiving_records(): void
    {
        $controller = $this->usedReceivingBatchFixture();
        $request = $this->relatedManualCorrectionRequest();
        $before = $this->stockCorrectionSnapshot();
        $receipt = json_encode(DB::table('purchase_lines')->where('id', 4)->first());
        $transactions = DB::table('transactions')->orderBy('id')->get()->toJson();
        $sale = json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 2)->first());
        $options = $controller->manualCorrectionOptions($request, 1)->getData(true);
        $candidate = collect($options['lines'][0]['recommendation']['candidates'])->firstWhere('source_id', 2);
        $this->assertTrue($candidate['selectable']);
        $this->assertStringContainsString('Only its source allocation', implode(' ', $candidate['issues']));

        $preview = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $preview->getStatusCode());
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $plan = $preview->getData(true)['plan'];
        $this->assertNull($plan['date_change']);
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $this->assertSame(200, $controller->applyManualCorrection($request, 1)->getStatusCode());
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('purchase_line_id'));
        $this->assertSame($receipt, json_encode(DB::table('purchase_lines')->where('id', 4)->first()));
        $this->assertSame($transactions, DB::table('transactions')->orderBy('id')->get()->toJson());
        $this->assertSame($sale, json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 2)->first()));
    }

    public function test_admin_preserves_used_receiving_metadata_when_source_dates_differ(): void
    {
        $controller = $this->usedReceivingBatchFixture();
        DB::table('purchase_lines')->where('id', 2)->update(['exp_date' => '2027-09-01']);
        $request = $this->relatedManualCorrectionRequest();
        $before = $this->stockCorrectionSnapshot();
        $receiving = json_encode(DB::table('purchase_lines')->where('id', 4)->first());
        $candidate = collect($controller->manualCorrectionOptions($request, 1)->getData(true)['lines'][0]['recommendation']['candidates'])->firstWhere('source_id', 2);
        $this->assertTrue($candidate['selectable']);

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertTrue($plan['changes'][0]['receiving_batch_preserved']);
        $this->assertNull($plan['changes'][0]['receiving_exp_date_after']);
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $this->assertSame(200, $controller->applyManualCorrection($request, 1)->getStatusCode());
        $this->assertSame($receiving, json_encode(DB::table('purchase_lines')->where('id', 4)->first()));
    }

    public function test_admin_repairs_zero_stock_allocation_while_preserving_used_receiving_batch(): void
    {
        $controller = $this->existingSourceShortageFixture();
        DB::table('transactions')->insert(['id' => 6, 'type' => 'sell', 'status' => 'final', 'location_id' => 13]);
        DB::table('transaction_sell_lines')->insert(['id' => 3, 'transaction_id' => 6, 'lot_no_line_id' => 4]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 3, 'purchase_line_id' => 4]);
        DB::table('purchase_lines')->where('id', 4)->update(['quantity_sold' => 1]);
        DB::table('purchase_lines')->where('id', 1)->update(['mfg_date' => '2026-08-01']);
        $receiving = json_encode(DB::table('purchase_lines')->where('id', 4)->first());
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $options = $controller->manualCorrectionOptions($request, 1)->getData(true);
        $this->assertEquals(0, collect($options['lines'][0]['sources'])->firstWhere('id', 1)['available']);
        $candidate = collect($options['lines'][0]['recommendation']['candidates'])->firstWhere('source_id', 1);
        $this->assertTrue($candidate['selectable']);
        $this->assertNull($candidate['unavailable_reason']);

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertTrue($plan['changes'][0]['receiving_batch_preserved']);
        $this->assertEquals(-1, $plan['remaining_stock_shortages'][0]['available_after']);
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $this->assertSame(200, $controller->applyManualCorrection($request, 1)->getStatusCode());
        $this->assertSame($receiving, json_encode(DB::table('purchase_lines')->where('id', 4)->first()));
    }

    public function test_used_receiving_candidate_reports_late_source_receipt_specifically(): void
    {
        $controller = $this->usedReceivingBatchFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $request = $this->relatedManualCorrectionRequest();
        $options = $controller->manualCorrectionOptions($request, 1)->getData(true);
        $candidate = collect($options['lines'][0]['recommendation']['candidates'])->firstWhere('source_id', 2);

        $this->assertFalse($candidate['selectable']);
        $this->assertStringContainsString('Source receipt is later', $candidate['unavailable_reason']);
    }

    public function test_admin_cannot_change_delivery_dates_of_a_used_receiving_batch(): void
    {
        $controller = $this->usedReceivingBatchFixture();
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['transaction_date' => '2026-09-03 12:00:00', 'date_verified' => '1']);
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('dates cannot change', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function relatedFifoFixture(string $kind = 'sell', string $status = 'final'): StockTransferController
    {
        $this->counterRepairTransfer();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 2]);
        DB::table('transactions')->insert(['id' => 4, 'type' => $kind, 'status' => $status, 'location_id' => 12,
            'ref_no' => 'ST-FIFO', 'transaction_date' => '2026-09-02 12:00:00']);
        DB::table('transaction_sell_lines')->insert(['id' => 42966, 'transaction_id' => 4, 'lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 42966, 'purchase_line_id' => 1]);
        if ($kind === 'sell_transfer') {
            DB::table('transactions')->insert(['id' => 5, 'type' => 'purchase_transfer', 'status' => 'received',
                'location_id' => 13, 'transfer_parent_id' => 4]);
            DB::table('purchase_lines')->insert(['id' => 5, 'transaction_id' => 5, 'lot_number' => 'INVOICE-LOT']);
        }
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);

        return $controller;
    }

    public function test_valid_related_fifo_sale_does_not_block_plan_or_manual_correction(): void
    {
        $controller = $this->relatedFifoFixture();
        $service = app(\App\Services\StockTransferReconciliationService::class);
        $this->assertSame('ready', $service->generate(1, 1)['status']);
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $fifo = json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 42966)->first());
        $before = $this->stockCorrectionSnapshot();

        $preview = $controller->previewManualCorrection($request, 1);

        $this->assertSame(200, $preview->getStatusCode());
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $plan = $preview->getData(true)['plan'];
        $request->merge(['token' => $plan['token'], 'confirmed' => '1']);
        $this->assertSame(200, $controller->applyManualCorrection($request, 1)->getStatusCode());
        $this->assertSame($fifo, json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 42966)->first()));
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertSame('consistent', $service->generate(1, 1)['status']);
    }

    public function test_valid_related_fifo_transfer_is_verified_against_receiving_serials(): void
    {
        $controller = $this->relatedFifoFixture('sell_transfer');
        $service = app(\App\Services\StockTransferReconciliationService::class);
        $this->assertSame('ready', $service->generate(1, 1)['status']);
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $this->assertSame(200, $controller->previewManualCorrection($request, 1)->getStatusCode());

        DB::table('purchase_lines')->where('id', 5)->update(['lot_number' => 'WRONG-RECEIVING-SERIAL']);
        $before = $this->stockCorrectionSnapshot();
        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('FIFO source allocations', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_related_draft_allocation_has_an_actionable_original_document_error(): void
    {
        $controller = $this->relatedFifoFixture('sell_transfer', 'draft');
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $detail = $response->getData(true)['stock_error'];
        $this->assertSame('related_allocation', $detail['kind']);
        $this->assertSame(42966, $detail['sell_line_id']);
        $this->assertSame('ST-FIFO', $detail['reference']);
        $this->assertStringContainsString('not completed', $detail['reason']);
        $this->assertStringContainsString('/stock-transfers/4/edit?correct_line=42966', $detail['url']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_incomplete_related_fifo_quantity_still_blocks_correction(): void
    {
        $controller = $this->relatedFifoFixture();
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 42966)->update(['quantity' => 0.5]);
        $request = $this->relatedManualCorrectionRequest();
        $request->merge(['corrections' => [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => 4]]]);
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->previewManualCorrection($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('quantities do not match', $response->getData(true)['stock_error']['reason']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function emptySourceTransfer(bool $overselling): array
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('business')->where('id', 1)->update(['pos_settings' => json_encode(['allow_overselling' => $overselling])]);
        DB::table('purchase_lines')->whereIn('id', [2, 3])->delete();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        DB::table('variation_location_details')->where('location_id', 12)->update(['qty_available' => 0]);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'sell', 'status' => 'final']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);

        return [$sell, $purchase];
    }

    public function test_transfer_overselling_setting_allows_empty_selected_receipt_and_records_negative_stock(): void
    {
        [$sell, $purchase] = $this->emptySourceTransfer(true);
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');

        $this->assertSame('final', Transaction::find(1)->status);
        $this->assertEquals([-1, 1], $this->transferBalances());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('purchase_line_id'));
        $this->assertSame('INVOICE-LOT', $purchase->purchase_lines()->first()->lot_number);
        $plan = app(\App\Services\StockTransferReconciliationService::class)->generate(1, 1);
        $this->assertTrue($plan['allow_overselling']);
        $this->assertSame('consistent', $plan['status']);
    }

    public function test_disabled_overselling_rejects_empty_transfer_stock_and_rolls_back(): void
    {
        [$sell, $purchase] = $this->emptySourceTransfer(false);
        $before = $this->stockCorrectionSnapshot();
        try {
            $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
            $this->fail('Disabled overselling must block a source shortage.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_transfer_overselling_does_not_override_selected_receipt_dates(): void
    {
        [$sell, $purchase] = $this->emptySourceTransfer(true);
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-03 12:00:00']);
        $before = $this->stockCorrectionSnapshot();
        try {
            $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
            $this->fail('Overselling must not substitute a receipt from after delivery.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertSame($before, $this->stockCorrectionSnapshot());
            $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->count());
        }
    }

    public function test_bulk_transfer_overselling_records_unallocated_quantity_and_can_be_reversed(): void
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('business')->where('id', 1)->update(['pos_settings' => '{"allow_overselling":1}']);
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => null, 'quantity' => 5]);
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');

        $this->assertEquals([-2, 5], $this->transferBalances());
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->where('purchase_line_id', 0)->sum('quantity'));
        $this->assertEquals(5, $purchase->purchase_lines()->sum('quantity'));
        $this->assertSame('consistent', app(\App\Services\StockTransferReconciliationService::class)->generate(1, 1)['status']);
        $service->reverse(Transaction::find(1), Transaction::find(3));
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->count());
    }

    public function test_overselling_setting_allows_new_negative_balance_in_verified_serial_correction(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('business')->where('id', 1)->update(['pos_settings' => '{"allow_overselling":true}']);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'sell', 'status' => 'final']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);
        $before = $this->stockCorrectionSnapshot();

        $plan = $service->previewManual(1, 1, $corrections, 'Serial and delivery records checked.');

        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertTrue($plan['allow_overselling']);
        $this->assertEquals(-1, $plan['remaining_stock_shortages'][0]['available_after']);
        $service->applyManual(1, 1, $corrections, $plan['evidence'], $plan['token']);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity);
        $this->assertSame('consistent', $service->generate(1, 1)['status']);
    }

    public function test_changing_overselling_setting_invalidates_a_serial_correction_preview(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('business')->where('id', 1)->update(['pos_settings' => '{"allow_overselling":true}']);
        $plan = $service->previewManual(1, 1, $corrections, 'Serial and delivery records checked.');
        DB::table('business')->where('id', 1)->update(['pos_settings' => '{"allow_overselling":false}']);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->applyManual(1, 1, $corrections, $plan['evidence'], $plan['token']);
            $this->fail('A settings change must require a new preview.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertStringContainsString('Preview the correction again', $exception->getMessage());
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_transfer_lot_choices_include_negative_stock_only_when_overselling_is_enabled(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity' => 2]);
        $util = app(TransactionUtil::class);
        $this->assertFalse($util->getLotNumbersFromVariation(2701, 1, 12, true)->contains('purchase_line_id', 1));
        $this->assertTrue($util->getLotNumbersFromVariation(2701, 1, 12, false, true)->contains('purchase_line_id', 1));
    }

    public function test_admin_auto_correction_fills_unallocated_serial_and_receiving_serial(): void
    {
        [, , $destinationId] = $this->manualCorrectionFixture();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        DB::table('purchase_lines')->where('id', 2)->update(['quantity_sold' => 0]);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $result = $controller->autoCorrectStock($request, 1)->getData(true);

        $this->assertTrue($result['complete']);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertSame('INVOICE-LOT', PurchaseLine::find($destinationId)->lot_number);
        $this->assertEquals([1, 0], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
    }

    public function test_admin_auto_correction_repairs_multiple_serials_together(): void
    {
        $this->manualCorrectionFixture();
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 3]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 0]);
        DB::table('purchase_lines')->insert(['id' => 5, 'transaction_id' => 3, 'lot_number' => 'INVOICE-LOT']);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $result = $controller->autoCorrectStock($request, 1)->getData(true);

        $this->assertTrue($result['complete']);
        $this->assertCount(2, $result['changes']);
        $this->assertEquals([2, 3], DB::table('transaction_sell_lines_purchase_lines')->orderBy('sell_line_id')->pluck('purchase_line_id')->all());
        $this->assertEquals([0, 1, 1], PurchaseLine::whereIn('id', [1, 2, 3])->orderBy('id')->pluck('quantity_sold')->all());
    }

    public function test_configured_administrator_gets_all_gate_permissions_with_trimmed_username(): void
    {
        config(['constants.administrator_usernames' => ' other-user, KY0021 ']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('checkPermissionTo')->andReturn(false);
        $user->username = 'ky0021';
        $gate = \Illuminate\Support\Facades\Gate::forUser($user);

        foreach (['superadmin', 'stock_transfer.update', 'stock_transfer.delete', 'stock_inventory.view', 'manage_modules'] as $ability) {
            $this->assertTrue($gate->allows($ability));
        }
    }

    public function test_admin_auto_correction_rolls_back_instead_of_inventing_a_receipt_date(): void
    {
        $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->autoCorrectStock($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('arrived after', $response->getData(true)['msg']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_auto_correction_denies_unpermitted_locations(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn([12]);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->autoCorrectStock($request, 1);
    }

    public function test_shortage_blocker_links_to_the_affected_lot_without_resetting_stock(): void
    {
        $service = $this->counterRepairTransfer();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 0]);
        $before = $this->stockCorrectionSnapshot();

        $plan = $service->generate(1, 1);

        $this->assertSame('verification_required', $plan['status']);
        $batch = collect($plan['review_items'])->first(fn ($item) => str_starts_with($item['reference'], 'Batch #1 /'));
        $this->assertNotNull($batch);
        $this->assertStringContainsString('INVOICE-LOT', $batch['url']);
        $this->assertStringContainsString('History available: -1', $batch['reasons'][0]);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_auto_correction_rolls_back_if_audit_logging_fails(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $util = \Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('activityLog')->andThrow(new \RuntimeException('Audit storage unavailable'));
        $controller = new StockTransferController($this->app->make(ProductUtil::class), $util,
            $this->app->make(ModuleUtil::class), $this->app->make(StockTransferStockService::class));
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        try {
            $controller->autoCorrectStock($request, 1);
            $this->fail('An audit failure must abort automatic correction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit storage unavailable', $exception->getMessage());
        }
        $this->assertEquals($before, $this->stockCorrectionSnapshot());
    }

    public function test_auto_correction_does_not_report_complete_when_final_verification_fails(): void
    {
        $this->counterRepairTransfer();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $service = \Mockery::mock(\App\Services\StockTransferReconciliationService::class);
        $service->shouldReceive('generate')->with(1, 1)->twice()
            ->andReturn(['status' => 'ready', 'token' => str_repeat('a', 64)], ['status' => 'verification_required']);
        $service->shouldReceive('apply')->once()->andReturnUsing(function () {
            DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
            return [['purchase_line_id' => 1]];
        });
        $this->app->instance(\App\Services\StockTransferReconciliationService::class, $service);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $before = $this->stockCorrectionSnapshot();

        $response = $controller->autoCorrectStock($request, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('No automatic correction was saved', $response->getData(true)['msg']);
        $this->assertEquals($before, $this->stockCorrectionSnapshot());
        $this->app->make(TransactionUtil::class)->shouldNotHaveReceived('activityLog');
    }

    private function manualCorrectionFixture(): array
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $destination = $purchase->purchase_lines()->first();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => 2]);
        DB::table('purchase_lines')->where('id', 2)->update(['quantity_sold' => 1]);
        DB::table('purchase_lines')->where('id', $destination->id)->update(['lot_number' => 'OTHER-LOT']);

        return [$this->app->make(\App\Services\StockTransferReconciliationService::class),
            [['sell_line_id' => 1, 'source_id' => 1, 'destination_id' => $destination->id]], $destination->id];
    }

    private function stockCorrectionSnapshot(): string
    {
        return json_encode([DB::table('purchase_lines')->orderBy('id')->get(),
            DB::table('transactions')->orderBy('id')->get(),
            DB::table('transaction_sell_lines')->orderBy('id')->get(),
            DB::table('transaction_sell_lines_purchase_lines')->orderBy('id')->get(),
            DB::table('variation_location_details')->orderBy('id')->get()], JSON_THROW_ON_ERROR);
    }

    public function test_detector_can_open_only_the_requested_related_line(): void
    {
        $this->relatedAllocationCorrectionFixture();
        $this->prepareProductStockDetailsSchema();
        DB::table('transaction_sell_lines')->where('id', 66356)->update(['id' => 66327]);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->update(['sell_line_id' => 66327]);
        auth()->user()->shouldReceive('permitted_locations')->with(1)->andReturn([12, 13]);
        $request = Request::create('/smart-stock-inventory/mismatch', 'GET', ['view' => 'stock_errors', 'line_id' => 66327]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $controller = new \Modules\SmartStockInventory\Http\Controllers\MismatchController(
            app(\App\Utils\Util::class), app(\Modules\SmartStockInventory\Services\StockFixService::class),
            \Mockery::mock(\Modules\SmartStockInventory\Services\TelegramAlertService::class)
        );
        $data = $controller->index($request)->getData();
        $this->assertSame('stock_errors', $data['filters']['view']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame(66327, $data['rows'][0]['sell_line_id']);
        $this->assertStringContainsString('/stock-transfers/4/fix-deducted-serial', $data['rows'][0]['deducted_serial_fix_url']);
        $this->assertStringContainsString('correct_line=66327', $data['rows'][0]['url']);
        $request->merge(['line_id' => 999999]);
        $this->assertSame([], $controller->index($request)->getData()['rows']);
    }

    public function test_admin_deducted_serial_fix_preserves_existing_related_source_shortage(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('transactions')->insert(['id' => 7, 'type' => 'sell', 'status' => 'final', 'location_id' => 12, 'transaction_date' => '2026-08-31 13:00:00']);
        DB::table('transaction_sell_lines')->insert(['id' => 9, 'transaction_id' => 7, 'lot_no_line_id' => 3]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 9, 'purchase_line_id' => 3]);
        $before = app(\App\Services\LotStockAuditService::class)->audit(1);
        $shortage = collect($before['stock_issues'])->firstWhere('id', 3);
        $this->assertEquals(-1, $shortage->expected_available);
        $dates = DB::table('transactions')->orderBy('id')->get()->toJson();
        $receiving = DB::table('purchase_lines')->whereIn('id', [4, 6])->orderBy('id')->get()->toJson();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $plan = $response->getData(true)['plan'];
        $this->assertEquals(3, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->value('purchase_line_id'));
        $remaining = collect($plan['remaining_stock_shortages'])->firstWhere('purchase_line_id', 3);
        $this->assertEquals(-1, $remaining['available_before']);
        $this->assertEquals(-1, $remaining['available_after']);
        $this->assertSame($dates, DB::table('transactions')->orderBy('id')->get()->toJson());
        $this->assertSame($receiving, DB::table('purchase_lines')->whereIn('id', [4, 6])->orderBy('id')->get()->toJson());
        $this->assertEquals(1, PurchaseLine::find(3)->quantity);
    }

    public function test_related_deduction_checks_its_matching_receiving_serial_without_changing_other_serials(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('purchase_lines')->insert(['id' => 8, 'transaction_id' => 5, 'lot_number' => 'OTHER-UNRECEIVED-SERIAL']);
        DB::table('transaction_sell_lines')->insert(['id' => 66328, 'transaction_id' => 4, 'lot_no_line_id' => 8]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 66328, 'purchase_line_id' => 8]);
        $audit = app(\App\Services\LotStockAuditService::class);
        $before = $audit->audit(1, null, 4)['transfer_issues'];
        $this->assertCount(1, $before);
        $unrelated = json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66328)->first());
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $this->assertEquals(3, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->value('purchase_line_id'));
        $this->assertSame($unrelated, json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66328)->first()));
        $this->assertSame(json_encode($before), json_encode($audit->audit(1, null, 4)['transfer_issues']));
    }

    public function test_unrepairable_related_deduction_returns_specific_cause_and_direct_line_link(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('transactions')->where('id', 5)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $before = $this->stockCorrectionSnapshot();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(422, $response->getStatusCode());
        $detail = $response->getData(true)['stock_error'];
        $this->assertStringContainsString('selected source receipt #3', $detail['reason']);
        $this->assertStringContainsString('2026-09-02', $detail['reason']);
        $this->assertStringContainsString('2026-08-31', $detail['reason']);
        $this->assertStringContainsString('line_id=66356', $detail['url']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_fix_deducted_serial_repairs_supported_related_line_66327_without_changing_receiving_records(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('transaction_sell_lines')->where('id', 66356)->update(['id' => 66327]);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66356)->update(['sell_line_id' => 66327]);
        $receiving = DB::table('purchase_lines')->whereIn('id', [4, 6])->orderBy('id')->get()->toJson();
        $dates = DB::table('transactions')->orderBy('id')->get()->toJson();
        $balances = DB::table('variation_location_details')->orderBy('id')->get()->toJson();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertEquals(66327, $plan['related_allocation_repairs'][0]['sell_line_id']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('purchase_line_id'));
        $this->assertEquals(3, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 66327)->value('purchase_line_id'));
        $this->assertSame($receiving, DB::table('purchase_lines')->whereIn('id', [4, 6])->orderBy('id')->get()->toJson());
        $this->assertSame($dates, DB::table('transactions')->orderBy('id')->get()->toJson());
        $this->assertSame($balances, DB::table('variation_location_details')->orderBy('id')->get()->toJson());
        $this->assertSame([], app(\App\Services\LotStockAuditService::class)->audit(1, null, 4)['sale_line_issues']);
    }

    public function test_fix_deducted_serial_rolls_back_when_related_receiving_serial_is_unverified(): void
    {
        $controller = $this->relatedAllocationCorrectionFixture();
        DB::table('purchase_lines')->where('id', 6)->update(['lot_number' => 'UNVERIFIED-SERIAL']);
        $before = $this->stockCorrectionSnapshot();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        app(TransactionUtil::class)->shouldNotHaveReceived('activityLog');
    }

    public function test_staff_fix_deducted_serial_cannot_repair_related_allocations(): void
    {
        $this->relatedAllocationCorrectionFixture();
        $before = $this->stockCorrectionSnapshot();
        try {
            app(\App\Services\StockTransferReconciliationService::class)->fixDeductedSerial(1, 1, 1, false);
            $this->fail('Expected related allocation blocker');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('Related line #66356', $e->getMessage());
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_fix_deducted_serial_aligns_allocation_to_matching_selected_and_receiving_serials(): void
    {
        [$service, , $destinationId] = $this->manualCorrectionFixture();
        $dates = DB::table('transactions')->orderBy('id')->pluck('transaction_date')->all();
        $plan = $service->fixDeductedSerial(1, 1, 1);
        $this->assertEquals(2, TransactionSellLine::find(1)->lot_no_line_id);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertSame('OTHER-LOT', PurchaseLine::find($destinationId)->lot_number);
        $this->assertEquals([0, 1], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertSame($dates, DB::table('transactions')->orderBy('id')->pluck('transaction_date')->all());
        $this->assertNull($plan['date_change']);
        $this->assertCount(1, $plan['changes']);
        $this->assertSame([], $service->generate(1, 1)['blockers']);
    }

    public function test_fix_deducted_serial_rejects_conflicting_receiving_serial_without_changes(): void
    {
        [$service, , $destinationId] = $this->manualCorrectionFixture();
        DB::table('purchase_lines')->where('id', $destinationId)->update(['lot_number' => 'UNVERIFIED-SERIAL']);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->fixDeductedSerial(1, 1, 1);
            $this->fail('Expected receiving serial conflict');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('Verified Serial Correction', $e->getMessage());
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_admin_fix_deducted_serial_preserves_used_receiving_batch_and_later_movements(): void
    {
        $controller = $this->usedReceivingBatchFixture();
        DB::table('purchase_lines')->where('id', 2)->update(['exp_date' => '2027-09-01', 'mfg_date' => '2026-08-01']);
        $receiving = json_encode(DB::table('purchase_lines')->where('id', 4)->first());
        $dates = DB::table('transactions')->orderBy('id')->get()->toJson();
        $laterLine = json_encode(DB::table('transaction_sell_lines')->where('id', 2)->first());
        $laterAllocation = json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 2)->first());
        $balances = DB::table('variation_location_details')->orderBy('id')->get()->toJson();
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $response = $controller->fixDeductedSerial($request, 1);
        $this->assertEquals(200, $response->getStatusCode());
        $plan = $response->getData(true)['plan'];
        $this->assertTrue($plan['changes'][0]['receiving_batch_preserved']);
        $this->assertNull($plan['date_change']);
        $this->assertSame([], $plan['related_allocation_repairs']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->value('purchase_line_id'));
        $this->assertSame($receiving, json_encode(DB::table('purchase_lines')->where('id', 4)->first()));
        $this->assertSame($dates, DB::table('transactions')->orderBy('id')->get()->toJson());
        $this->assertSame($laterLine, json_encode(DB::table('transaction_sell_lines')->where('id', 2)->first()));
        $this->assertSame($laterAllocation, json_encode(DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 2)->first()));
        $this->assertSame($balances, DB::table('variation_location_details')->orderBy('id')->get()->toJson());
    }

    public function test_staff_fix_deducted_serial_cannot_change_a_referenced_receiving_batch(): void
    {
        $this->usedReceivingBatchFixture();
        $before = $this->stockCorrectionSnapshot();
        try {
            app(\App\Services\StockTransferReconciliationService::class)->fixDeductedSerial(1, 1, 1, false);
            $this->fail('Expected administrator allocation repair requirement');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('administrator allocation repair', $e->getMessage());
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_fix_deducted_serial_cannot_use_a_receipt_from_after_the_transfer(): void
    {
        [$service] = $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->fixDeductedSerial(1, 1, 1);
            $this->fail('Expected receipt date conflict');
        } catch (PurchaseSellMismatch $e) {
            $this->assertSame($before, $this->stockCorrectionSnapshot());
        }
    }

    public function test_fix_deducted_serial_endpoint_applies_and_audits_the_correction(): void
    {
        $this->manualCorrectionFixture();
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['sell_line_id' => 1]);
        $result = $controller->fixDeductedSerial($request, 1)->getData(true);
        $this->assertEquals(1, $result['success']);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        app(TransactionUtil::class)->shouldHaveReceived('activityLog')->once()
            ->withArgs(fn ($transfer, $event, $before, $plan) => $event === 'stock_transfer_corrected'
                && str_contains($plan['evidence'], 'matching recorded selected and receiving serials'));
    }

    public function test_manual_preview_rolls_back_and_apply_corrects_all_three_records_and_counters(): void
    {
        [$service, $corrections, $destinationId] = $this->manualCorrectionFixture();
        $before = $this->stockCorrectionSnapshot();
        $plan = $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertSame('INVOICE-LOT', $plan['changes'][0]['receiving_serial_after']);
        $service->applyManual(1, 1, $corrections, $plan['evidence'], $plan['token']);
        $this->assertEquals(1, TransactionSellLine::find(1)->lot_no_line_id);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
        $this->assertSame('INVOICE-LOT', PurchaseLine::find($destinationId)->lot_number);
        $this->assertEquals([1, 0], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertSame([], $service->generate(1, 1)['blockers']);
    }

    public function test_manual_correction_rejects_changed_choices_or_stale_stock(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        $evidence = 'Delivery document and physical serial verified.';
        $plan = $service->previewManual(1, 1, $corrections, $evidence);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->applyManual(1, 1, $corrections, 'Different evidence after reviewing the preview.', $plan['token']);
            $this->fail('Changed intent must invalidate the preview.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('changed', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_blocks_future_receipt_without_verified_date(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $this->assertSame('2026-09-02 12:00:00', $service->manualOptions(1, 1)[0]['sources'][0]->receipt_date);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
            $this->fail('Future receipts must never correct earlier transfers.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('arrived after', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_cannot_relabel_a_receiving_batch_referenced_by_a_draft(): void
    {
        [$service, $corrections, $destinationId] = $this->manualCorrectionFixture();
        DB::table('transactions')->insert(['id' => 4, 'status' => 'draft', 'location_id' => 13]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => $destinationId]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
            $this->fail('A referenced receiving batch must be protected.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('later movement', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_cannot_create_negative_source_stock(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transactions')->insert(['id' => 4]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
            $this->fail('A correction cannot invent source stock.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('negative stock', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_rejects_batches_from_another_business(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['business_id' => 2]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
            $this->fail('Cross-business sources must be rejected.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('another business', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_apply_endpoint_logs_evidence_and_changes_atomically(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $evidence = 'Delivery document and physical serial verified.';
        $request->merge(['corrections' => $corrections, 'evidence' => $evidence, 'verified' => 1]);
        $plan = $controller->previewManualCorrection($request, 1)->getData(true)['plan'];
        $request->merge(['token' => $plan['token'], 'confirmed' => 1]);
        $this->assertEquals(1, $controller->applyManualCorrection($request, 1)->getData(true)['success']);
        $this->app->make(TransactionUtil::class)->shouldHaveReceived('activityLog')->once()
            ->withArgs(fn ($transfer, $event, $before, $properties) => $event === 'stock_transfer_corrected' && $properties['evidence'] === $evidence);
    }

    public function test_manual_verified_date_preview_rolls_back_and_apply_updates_both_dates(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $before = $this->stockCorrectionSnapshot();
        $evidence = 'Verified delivery document confirms serial and September 3 delivery.';
        $plan = $service->previewManual(1, 1, $corrections, $evidence, '2026-09-03 12:00:00');
        $this->assertSame($before, $this->stockCorrectionSnapshot());
        $this->assertSame('2026-09-03 12:00:00', $plan['date_change']['transfer_after']);
        $service->applyManual(1, 1, $corrections, $evidence, $plan['token'], '2026-09-03 12:00:00');
        $this->assertEquals(['2026-09-03 12:00:00', '2026-09-03 12:00:00'], DB::table('transactions')->whereIn('id', [1, 3])->orderBy('id')->pluck('transaction_date')->all());
        $this->assertEquals([2, 1], $this->transferBalances());
    }

    public function test_manual_date_change_protects_unmodified_receiving_batches_too(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('purchase_lines')->insert(['id' => 5, 'transaction_id' => 3, 'lot_number' => 'OTHER-RECEIVED']);
        DB::table('transactions')->insert(['id' => 4, 'location_id' => 13, 'type' => 'sell_transfer', 'status' => 'draft', 'ref_no' => 'ST-DEPENDENT']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 5]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Verified delivery document and September 3 delivery.', '2026-09-03 12:00:00');
            $this->fail('Changing dates must protect all receiving references.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('dates cannot change', $e->getMessage());
            $details = $e->responseData()['stock_error'];
            $this->assertSame('transfer_date_dependencies', $details['kind']);
            $this->assertSame('ST-DEPENDENT', $details['dependent_movements'][0]['reference']);
            $this->assertSame('draft', $details['dependent_movements'][0]['status']);
            $this->assertSame(['OTHER-RECEIVED'], $details['dependent_movements'][0]['serials']);
            $this->assertCount(4, $details['steps']);
            $this->assertStringContainsString('/stock-transfers/4/edit', $details['links'][0]['url']);
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_preview_endpoint_requires_separate_date_verification(): void
    {
        [, $corrections] = $this->manualCorrectionFixture();
        $controller = $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $request->merge(['corrections' => $corrections, 'evidence' => 'Delivery document and physical serial verified.', 'verified' => 1,
            'transaction_date' => '2026-09-03 12:00:00']);
        $before = $this->stockCorrectionSnapshot();
        try {
            $controller->previewManualCorrection($request, 1);
            $this->fail('A corrected date needs separate confirmation.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('date_verified', $e->errors());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_rejects_returned_transfer(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity_returned' => 1]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Delivery document and physical serial verified.');
            $this->fail('Returned movements need a separate reconciliation.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('returns', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_correction_rolls_back_if_audit_log_fails(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        $util = \Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('activityLog')->andThrow(new \RuntimeException('Audit log unavailable'));
        $controller = new StockTransferController($this->app->make(ProductUtil::class), $util,
            $this->app->make(ModuleUtil::class), $this->app->make(StockTransferStockService::class));
        $request = $this->statusRequest('completed');
        $request->setUserResolver(fn () => auth()->user());
        $evidence = 'Delivery document and physical serial verified.';
        $plan = $service->previewManual(1, 1, $corrections, $evidence);
        $request->merge(['corrections' => $corrections, 'evidence' => $evidence, 'verified' => 1, 'token' => $plan['token'], 'confirmed' => 1]);
        $before = $this->stockCorrectionSnapshot();
        try {
            $controller->applyManualCorrection($request, 1);
            $this->fail('No stock correction may be saved without its audit log.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit log unavailable', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_recommendation_prefills_only_unique_agreeing_records_without_writing_stock(): void
    {
        $service = $this->counterRepairTransfer();
        DB::table('purchase_lines')->where('id', 3)->delete();
        $before = $this->stockCorrectionSnapshot();
        $row = $service->manualOptions(1, 1)[0];
        $this->assertTrue($row['recommendation']['prefill']);
        $this->assertSame('records_agree', $row['recommendation']['status']);
        $this->assertEquals(1, $row['recommendation']['source_id']);
        $this->assertEquals(4, $row['recommendation']['destination_id']);
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_recommendation_explains_conflicting_serials_and_does_not_choose_for_user(): void
    {
        [$service] = $this->manualCorrectionFixture();
        $recommendation = $service->manualOptions(1, 1)[0]['recommendation'];
        $this->assertFalse($recommendation['prefill']);
        $this->assertNull($recommendation['source_id']);
        $this->assertCount(2, $recommendation['candidates']);
        $this->assertTrue($recommendation['candidates'][0]['selectable']);
        $this->assertTrue($recommendation['candidates'][1]['selectable']);
        $this->assertStringContainsString('Receiving serial differs', implode(' ', $recommendation['candidates'][0]['issues']));
        $this->assertStringContainsString('Allocation is missing or disagrees', implode(' ', $recommendation['candidates'][1]['issues']));
    }

    public function test_recommendation_requires_verified_date_for_future_receipts(): void
    {
        [$service] = $this->manualCorrectionFixture();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        $recommendation = $service->manualOptions(1, 1)[0]['recommendation'];
        $this->assertFalse($recommendation['prefill']);
        $this->assertNull($recommendation['source_id']);
        foreach ($recommendation['candidates'] as $candidate) {
            $this->assertStringContainsString('verified delivery date', implode(' ', $candidate['issues']));
        }
    }

    public function test_recommendation_disables_candidates_with_referenced_receiving_batches(): void
    {
        [$service, , $destinationId] = $this->manualCorrectionFixture();
        DB::table('transactions')->insert(['id' => 4, 'location_id' => 13, 'status' => 'draft']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => $destinationId]);
        $recommendation = $service->manualOptions(1, 1)[0]['recommendation'];
        $this->assertFalse($recommendation['prefill']);
        foreach ($recommendation['candidates'] as $candidate) {
            $this->assertFalse($candidate['selectable']);
            $this->assertStringContainsString('used or referenced', implode(' ', $candidate['issues']));
        }
    }

    public function test_recommendation_never_picks_an_unrelated_available_phone(): void
    {
        [$service] = $this->manualCorrectionFixture();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $recommendation = $service->manualOptions(1, 1)[0]['recommendation'];
        $this->assertFalse($recommendation['prefill']);
        $this->assertSame([], $recommendation['candidates']);
    }

    public function test_recommendation_does_not_assign_one_receiving_batch_to_two_lines(): void
    {
        $service = $this->counterRepairTransfer();
        DB::table('purchase_lines')->where('id', 3)->delete();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 2]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);
        foreach ($service->manualOptions(1, 1) as $row) {
            $this->assertFalse($row['recommendation']['prefill']);
            $this->assertStringContainsString('More than one transfer line', $row['recommendation']['message']);
        }
    }

    public function test_manual_one_line_correction_preserves_unrelated_bad_lines_and_shortages(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('purchase_lines')->insert(['id' => 5, 'lot_number' => 'UNRELATED-SERIAL', 'quantity' => 1, 'quantity_sold' => 1]);
        DB::table('purchase_lines')->insert(['id' => 6, 'transaction_id' => 3, 'lot_number' => 'UNRELATED-WRONG', 'quantity' => 2]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 5, 'quantity' => 2]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 0, 'quantity' => 2]);
        $unrelated = fn () => json_encode([DB::table('purchase_lines')->whereIn('id', [5, 6])->orderBy('id')->get(),
            DB::table('transaction_sell_lines')->where('id', 2)->first(), DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 2)->get()]);
        $before = $unrelated();
        $plan = $service->previewManual(1, 1, $corrections, 'Physical serial and original delivery document verified.');
        $this->assertCount(1, $plan['changes']);
        $this->assertCount(1, $plan['remaining_line_issues']);
        $this->assertEquals(2, $plan['remaining_line_issues'][0]['sell_line_id']);
        $service->applyManual(1, 1, $corrections, $plan['evidence'], $plan['token']);
        $this->assertSame($before, $unrelated());
        $this->assertEquals(1, TransactionSellLine::find(1)->lot_no_line_id);
        $this->assertEquals([2, 1], $this->transferBalances());
    }

    public function test_manual_one_line_correction_cannot_steal_another_lines_receiving_batch(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('purchase_lines')->insert(['id' => 5, 'lot_number' => 'OTHER-VALID', 'quantity_sold' => 1]);
        DB::table('purchase_lines')->insert(['id' => 6, 'transaction_id' => 3, 'lot_number' => 'OTHER-VALID']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 5]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 5]);
        $corrections[0]['destination_id'] = 6;
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Physical serial and original delivery document verified.');
            $this->fail('A line cannot take another line\'s receiving stock.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('worsen another line', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_one_line_date_correction_cannot_invalidate_unchanged_source_receipts(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transactions')->whereIn('id', [1, 3])->update(['transaction_date' => '2026-09-03 12:00:00']);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'purchase', 'status' => 'received', 'transaction_date' => '2026-09-02 12:00:00']);
        DB::table('purchase_lines')->insert(['id' => 5, 'transaction_id' => 4, 'lot_number' => 'OTHER-VALID', 'quantity_sold' => 1]);
        DB::table('purchase_lines')->insert(['id' => 6, 'transaction_id' => 3, 'lot_number' => 'OTHER-VALID']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 5]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 5]);
        $before = $this->stockCorrectionSnapshot();
        $this->assertSame('2026-09-02 12:00:00', $service->manualOptions(1, 1)[0]['unchanged_lines_date_floor']);
        try {
            $service->previewManual(1, 1, $corrections, 'Physical serial and original delivery document verified.', '2026-09-01 12:00:00');
            $this->fail('A date correction cannot invalidate another line.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('unchanged line', $e->getMessage());
            $this->assertStringContainsString('batch #5', $e->getMessage());
            $this->assertStringContainsString('2026-09-02 12:00:00', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    public function test_manual_one_line_correction_cannot_turn_receiving_surplus_into_shortage(): void
    {
        [$service, $corrections] = $this->manualCorrectionFixture();
        DB::table('transaction_sell_lines')->where('id', 1)->update(['quantity' => 2]);
        DB::table('transaction_sell_lines_purchase_lines')->where('sell_line_id', 1)->update(['quantity' => 2]);
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 2]);
        DB::table('purchase_lines')->where('id', 4)->update(['quantity' => 2]);
        DB::table('purchase_lines')->insert(['id' => 5, 'lot_number' => 'OTHER-VALID', 'quantity_sold' => 1]);
        DB::table('purchase_lines')->insert(['id' => 6, 'transaction_id' => 3, 'lot_number' => 'OTHER-VALID', 'quantity' => 2]);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'lot_no_line_id' => 5]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 5]);
        $corrections[0]['destination_id'] = 6;
        $before = $this->stockCorrectionSnapshot();
        try {
            $service->previewManual(1, 1, $corrections, 'Physical serial and original delivery document verified.');
            $this->fail('Equal-sized shortages and surpluses are not interchangeable.');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('worsen another line', $e->getMessage());
        }
        $this->assertSame($before, $this->stockCorrectionSnapshot());
    }

    private function transferPair(): array
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('business_locations')->insert([
            ['id' => 12, 'name' => 'Source'], ['id' => 13, 'name' => 'Destination'],
        ]);
        DB::table('transactions')->where('id', 1)->update(['type' => 'sell_transfer', 'status' => 'pending']);
        DB::table('transactions')->insert([
            'id' => 3, 'type' => 'purchase_transfer', 'status' => 'pending', 'location_id' => 13, 'transfer_parent_id' => 1,
        ]);
        DB::table('variation_location_details')->insert([
            ['product_id' => 589, 'variation_id' => 2701, 'product_variation_id' => 1, 'location_id' => 12, 'qty_available' => 3],
            ['product_id' => 589, 'variation_id' => 2701, 'product_variation_id' => 1, 'location_id' => 13, 'qty_available' => 0],
        ]);

        return [Transaction::find(1), Transaction::find(3)];
    }

    private function transferBalances(): array
    {
        return VariationLocationDetails::orderBy('location_id')->pluck('qty_available')->all();
    }

    public function test_transfer_cannot_deduct_a_receipt_from_after_its_transaction_date(): void
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('transactions')->where('id', 2)->update(['transaction_date' => '2026-09-02 12:00:00']);
        try {
            $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
            $this->fail('Future stock must not complete an earlier transfer.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertStringContainsString('dated before', $exception->responseData()['stock_error']['reason']);
        }
        $this->assertSame('pending', Transaction::find(1)->status);
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
    }

    public function test_audit_reports_transfer_receiving_serial_mismatch_without_repairing_it(): void
    {
        $this->transferPair();
        DB::table('transactions')->where('id', 1)->update(['status' => 'final']);
        DB::table('transactions')->where('id', 3)->update(['status' => 'received']);
        DB::table('purchase_lines')->insert(['id' => 4, 'transaction_id' => 3, 'lot_number' => 'WRONG-RECEIVED']);
        $report = $this->app->make(LotStockAuditService::class)->audit(1);
        $this->assertCount(1, $report['transfer_issues']);
        $this->assertSame('INVOICE-LOT', $report['transfer_issues'][0]['selected_serial']);
        $this->assertSame('WRONG-RECEIVED', $report['transfer_issues'][0]['receiving_lines'][0]->lot_number);
        $this->assertSame('WRONG-RECEIVED', PurchaseLine::find(4)->lot_number);
    }

    public function test_completed_transfer_moves_stock_and_preserves_destination_serial(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertSame('INVOICE-LOT', $purchase->purchase_lines()->first()->lot_number);
        $this->assertSame('final', $sell->fresh()->status);
        $this->assertSame('received', $purchase->fresh()->status);
    }

    public function test_reversing_completed_transfer_restores_both_locations_and_lot(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        $service->reverse($sell, $purchase);
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertSame('pending', $sell->fresh()->status);
    }

    public function test_editing_completed_transfer_applies_new_quantity_once(): void
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 3]);
        DB::table('variation_location_details')->where('location_id', 12)->update(['qty_available' => 5]);
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        DB::transaction(function () use ($service, $sell, $purchase) {
            $service->reverse($sell, $purchase);
            TransactionSellLine::find(1)->update(['quantity' => 2]);
            $service->apply($sell, $purchase, 'fifo');
        });
        $this->assertEquals([3, 2], $this->transferBalances());
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(2, $purchase->purchase_lines()->sum('quantity'));
    }

    public function test_failed_transfer_edit_rolls_back_original_stock_and_allocations(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        try {
            DB::transaction(function () use ($service, $sell, $purchase) {
                $service->reverse($sell, $purchase);
                TransactionSellLine::find(1)->update(['quantity' => 2]);
                $service->apply($sell, $purchase, 'fifo');
            });
            $this->fail('A failed transfer edit must roll back.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals([2, 1], $this->transferBalances());
            $this->assertEquals(1, TransactionSellLine::find(1)->quantity);
            $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
            $this->assertSame('final', $sell->fresh()->status);
        }
    }

    public function test_transfer_with_consumed_destination_cannot_be_reversed(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        $purchase->purchase_lines()->first()->update(['quantity_adjusted' => 1]);
        try {
            $service->reverse($sell, $purchase);
            $this->fail('Consumed destination stock must prevent reversal.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals([2, 1], $this->transferBalances());
            $this->assertSame('final', $sell->fresh()->status);
        }
    }

    public function test_bulk_transfer_preserves_multiple_serials_of_same_sku(): void
    {
        [$sell, $purchase] = $this->transferPair();
        TransactionSellLine::find(1)->update(['lot_no_line_id' => null, 'quantity' => 2]);
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $this->assertSame(['INVOICE-LOT', 'OTHER-LOT'], $purchase->purchase_lines()->orderBy('id')->pluck('lot_number')->all());
        $this->assertEquals([1, 2], $this->transferBalances());
        $this->assertSame([], $this->app->make(LotStockAuditService::class)->audit(1)['transfer_issues']);
    }

    public function test_retransfer_uses_received_serial_and_updates_both_locations(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        $received = $purchase->purchase_lines()->first();
        DB::table('transactions')->insert([
            'id' => 4, 'type' => 'sell_transfer', 'status' => 'pending', 'location_id' => 13,
        ]);
        DB::table('transactions')->insert([
            'id' => 5, 'type' => 'purchase_transfer', 'status' => 'pending', 'location_id' => 12, 'transfer_parent_id' => 4,
        ]);
        TransactionSellLine::create([
            'transaction_id' => 4, 'product_id' => 589, 'variation_id' => 2701, 'lot_no_line_id' => $received->id, 'quantity' => 1,
        ]);
        $service->apply(Transaction::find(4), Transaction::find(5), 'fifo');
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertSame('INVOICE-LOT', Transaction::find(5)->purchase_lines()->first()->lot_number);
        $this->assertEquals(1, $received->fresh()->quantity_sold);
        $this->expectException(PurchaseSellMismatch::class);
        $service->reverse($sell, $purchase);
    }

    public function test_repeating_transfer_completion_cannot_deduct_stock_twice(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        try {
            $service->apply($sell, $purchase, 'fifo');
            $this->fail('Already allocated transfer must not deduct stock again.');
        } catch (PurchaseSellMismatch $exception) {
            $this->assertEquals([2, 1], $this->transferBalances());
            $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->count());
        }
    }

    public function test_multi_batch_return_reversal_skips_batches_without_returns(): void
    {
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null, 'quantity' => 2]);
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1, 'qty_returned' => 0],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 1, 'qty_returned' => 1],
        ]);
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        $this->app->make(TransactionUtil::class)->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 0, 1, false);
        $this->assertEquals([1, 1], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->sum('qty_returned'));
    }

    private function transferController(): StockTransferController
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->username = 'cashier-test';
        $user->shouldReceive('can')->andReturnUsing(fn ($ability) => $ability !== 'superadmin');
        $this->actingAs($user);
        session()->put('user', ['business_id' => 1, 'id' => 1]);
        $util = \Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('activityLog')->andReturnNull();
        $util->shouldReceive('canBeEdited')->andReturn(true);
        $this->app->instance(TransactionUtil::class, $util);
        $module = \Mockery::mock(ModuleUtil::class)->makePartial();
        $module->shouldReceive('isSubscribed')->andReturn(true);
        $controller = \Mockery::mock(StockTransferController::class, [
            $this->app->make(ProductUtil::class), $util, $module,
            $this->app->make(StockTransferStockService::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('sendStockTransferNotification')->andReturnNull();
        Event::fake([StockTransferCreatedOrModified::class]);

        return $controller;
    }

    private function statusRequest(string $status): Request
    {
        $request = Request::create('/stock-transfers/update-status/1', 'POST', ['status' => $status]);
        $request->setLaravelSession($this->app['session.store']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->app->instance('request', $request);

        return $request;
    }

    public function test_failed_transfer_links_to_its_specific_detector_issue_when_product_totals_balance(): void
    {
        $this->transferPair();
        $this->prepareProductStockDetailsSchema();
        DB::table('variation_location_details')->where('id', DB::table('variation_location_details')->max('id'))->delete();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        $controller = $this->transferController();
        $result = $controller->updateStatus($this->statusRequest('completed'), 1);
        $this->assertEquals(0, $result['success']);
        $this->assertStringContainsString('/smart-stock-inventory/mismatch?', $result['mismatch_url']);
        $this->assertStringContainsString('transfer_id=1', $result['mismatch_url']);
        $this->assertEquals(1, $result['stock_error']['source_line_id']);
        $lastFailure = session('stock_transfer_errors.1.1');
        $this->assertNotNull($lastFailure);
        $filters = ['business_id' => 1, 'location_ids' => [12, 13], 'transfer_id' => 1];
        $detector = app(\App\Services\StockMismatchDetectorService::class);
        $this->assertSame([], $detector->detectQuantityMismatches($filters));
        $rows = $detector->detectTransferIssues($filters, $lastFailure);
        $this->assertStringContainsString('Last stock action error:', $rows[0]['problem']);
        $this->assertSame('PHONE-1', $rows[0]['sku']);
        $this->assertSame('INVOICE-LOT', $rows[0]['lot']);
        $this->assertStringContainsString('/stock-transfers/update-status/1', $rows[0]['completion_url']);
        $this->assertStringContainsString('/stock-transfers/1/edit', $rows[0]['url']);
        $this->assertNotEmpty(collect($rows)->filter(fn ($row) => str_contains($row['problem'], 'Recorded batch balance')));
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $rolledBackFailure = $lastFailure;
        $rolledBackFailure['stock_error']['source_line_id'] = 999999;
        $this->assertStringContainsString('Last stock action error:', $detector->detectTransferIssues($filters, $rolledBackFailure)[0]['problem']);
        $deleteFailure = $lastFailure;
        $deleteFailure['operation'] = 'delete';
        $this->assertNull($detector->detectTransferIssues($filters, $deleteFailure)[0]['completion_url']);
    }

    public function test_focused_transfer_detector_respects_permitted_locations(): void
    {
        $this->transferPair();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\App\Services\StockMismatchDetectorService::class)->detectTransferIssues([
            'business_id' => 1, 'location_ids' => [13], 'transfer_id' => 1,
        ], ['msg' => 'Private source error']);
    }

    public function test_admin_completion_rebuilds_only_verified_stale_source_counters(): void
    {
        $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        $controller = $this->transferController();
        session(['stock_transfer_errors.1.1' => ['msg' => 'Previous stock error']]);
        config(['constants.administrator_usernames' => 'cashier-test']);
        $result = $controller->updateStatus($this->statusRequest('completed'), 1);
        $this->assertEquals(1, $result['success']);
        $this->assertNull(session('stock_transfer_errors.1.1'));
        $this->assertSame('final', Transaction::find(1)->status);
        $this->assertSame('received', Transaction::find(3)->status);
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals(1, $controller->updateStatus($this->statusRequest('completed'), 1)['success']);
        $this->assertEquals([2, 1], $this->transferBalances());
    }

    public function test_admin_completion_does_not_rebuild_counters_with_unverified_related_allocations(): void
    {
        $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 2]);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'sell', 'status' => 'final']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 1]);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);
        $result = $controller->updateStatus($this->statusRequest('completed'), 1);
        $this->assertEquals(0, $result['success']);
        $this->assertSame('pending', Transaction::find(1)->status);
        $this->assertEquals(2, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
    }

    public function test_verified_counter_repairs_roll_back_when_transfer_completion_fails(): void
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        DB::table('variation_location_details')->where('location_id', 12)->update(['qty_available' => 0]);
        $util = \Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('activityLog')->andReturnNull();
        $service = new StockTransferStockService($util, app(ProductUtil::class));
        try {
            $service->apply($sell, $purchase, 'fifo', true);
            $this->fail('Expected source location shortage');
        } catch (PurchaseSellMismatch $e) {
            $this->assertStringContainsString('source location', $e->getMessage());
            $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
            $this->assertSame('pending', Transaction::find(1)->status);
            $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
            $this->assertEquals([0, 0], $this->transferBalances());
        }
    }

    public function test_transfer_mismatch_returns_serial_details_and_review_links_without_changing_stock(): void
    {
        $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity_sold' => 1]);
        DB::table('transactions')->insert(['id' => 4, 'type' => 'sell_transfer', 'status' => 'final', 'ref_no' => 'ST-CONFLICT']);
        DB::table('transaction_sell_lines')->insert(['id' => 2, 'transaction_id' => 4, 'lot_no_line_id' => 2]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 2, 'purchase_line_id' => 1]);
        $controller = $this->transferController();
        config(['constants.administrator_usernames' => 'cashier-test']);

        $result = $controller->updateStatus($this->statusRequest('completed'), 1);

        $this->assertEquals(0, $result['success']);
        $detail = $result['stock_error'];
        $this->assertSame('INVOICE-LOT', $detail['serial']);
        $this->assertEquals(1, $detail['requested_quantity']);
        $this->assertEquals(0, $detail['recorded_available']);
        $this->assertEquals(1, $detail['history_available']);
        $this->assertEquals(0, $detail['system_available']);
        $this->assertEquals(1, $detail['calculation']['recorded_consumption']);
        $this->assertEquals(0, $detail['calculation']['history_consumption']);
        $this->assertEquals(1, $detail['calculation']['difference']);
        $this->assertStringContainsString('Reconcile', $detail['reason']);
        $this->assertStringContainsString('OTHER-LOT', $detail['related_allocations'][0]);
        $this->assertStringContainsString('/lot/history/INVOICE-LOT', $detail['links'][0]['url']);
        $this->assertStringContainsString('/stock-transfers/4/edit', $detail['links'][1]['url']);
        $this->assertSame('pending', Transaction::find(1)->status);
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, DB::table('transaction_sell_lines_purchase_lines')->count());
    }

    public function test_status_endpoint_completion_is_idempotent_and_demotion_restores_stock(): void
    {
        $this->transferPair();
        $controller = $this->transferController();
        $this->assertEquals(1, $controller->updateStatus($this->statusRequest('completed'), 1)['success']);
        $this->assertEquals(1, $controller->updateStatus($this->statusRequest('completed'), 1)['success']);
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertEquals(1, $controller->updateStatus($this->statusRequest('pending'), 1)['success']);
        $this->assertEquals([3, 0], $this->transferBalances());
    }

    public function test_delete_endpoint_restores_stock_and_removes_both_transaction_sides(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $controller = $this->transferController();
        $this->statusRequest('completed');
        $this->assertEquals(1, $controller->destroy(1)['success']);
        $this->assertEquals([3, 0], $this->transferBalances());
        $this->assertNull(Transaction::find(1));
        $this->assertNull(Transaction::find(3));
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_fully_returned_sale_reference_still_protects_transfer_history(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $service = $this->app->make(StockTransferStockService::class);
        $service->apply($sell, $purchase, 'fifo');
        $destination = $purchase->purchase_lines()->first();
        DB::table('transactions')->insert(['id' => 4, 'location_id' => 13]);
        TransactionSellLine::create([
            'transaction_id' => 4, 'product_id' => 589, 'variation_id' => 2701,
            'lot_no_line_id' => $destination->id, 'quantity' => 1, 'quantity_returned' => 1,
        ]);
        $this->expectException(PurchaseSellMismatch::class);
        $service->reverse($sell, $purchase);
    }

    public function test_fifo_transfer_history_includes_each_allocated_serial(): void
    {
        [$sell, $purchase] = $this->transferPair();
        TransactionSellLine::find(1)->update(['lot_no_line_id' => null, 'quantity' => 2]);
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $controller = $this->app->make(LotController::class);
        $method = new \ReflectionMethod($controller, 'lotMovementLinesQuery');
        $method->setAccessible(true);
        $this->assertSame(['INVOICE-LOT', 'OTHER-LOT'], $method->invoke($controller, 1, ['sell_transfer'])
            ->orderBy('pl.id')->pluck('pl.lot_number')->all());
    }

    private function editRequest(array $products): Request
    {
        $request = Request::create('/stock-transfers/1', 'PUT', [
            'status' => 'completed', 'transaction_date' => '2026-09-01 12:00:00',
            'shipping_charges' => 0, 'final_total' => 100, 'additional_notes' => 'Edited transfer',
            'products' => $products,
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);

        return $request;
    }

    private function editProduct(int $quantity = 1, ?int $lineId = 1, int $lotId = 1): array
    {
        $product = [
            'product_id' => 589, 'variation_id' => 2701, 'quantity' => $quantity,
            'unit_price' => 100, 'product_unit_id' => 1, 'base_unit_multiplier' => 1,
            'lot_no_line_id' => $lotId,
        ];
        if ($lineId !== null) {
            $product['transaction_sell_lines_id'] = $lineId;
        }

        return $product;
    }

    public function test_edit_endpoint_updates_completed_quantity_and_destination_stock(): void
    {
        [$sell, $purchase] = $this->transferPair();
        DB::table('purchase_lines')->where('id', 1)->update(['quantity' => 3]);
        DB::table('variation_location_details')->where('location_id', 12)->update(['qty_available' => 5]);
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $controller = $this->transferController();
        $controller->update($this->editRequest([$this->editProduct(2)]), 1);
        $this->assertEquals(1, session('status.success'), session('status.msg'));
        $this->assertEquals([3, 2], $this->transferBalances());
        $this->assertEquals(2, $purchase->purchase_lines()->sum('quantity'));
    }

    public function test_edit_endpoint_replaces_all_rows_without_retaining_old_serial(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $controller = $this->transferController();
        $controller->update($this->editRequest([$this->editProduct(1, null, 2)]), 1);
        $this->assertEquals(1, session('status.success'), session('status.msg'));
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertEquals(1, $sell->sell_lines()->count());
        $this->assertSame('OTHER-LOT', $purchase->purchase_lines()->first()->lot_number);
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, PurchaseLine::find(2)->quantity_sold);
    }

    public function test_metadata_edit_does_not_move_consumed_stock(): void
    {
        [$sell, $purchase] = $this->transferPair();
        $this->app->make(StockTransferStockService::class)->apply($sell, $purchase, 'fifo');
        $received = $purchase->purchase_lines()->first();
        $received->update(['quantity_sold' => 1]);
        $controller = $this->transferController();
        $controller->update($this->editRequest([$this->editProduct()]), 1);
        $this->assertEquals(1, session('status.success'), session('status.msg'));
        $this->assertEquals([2, 1], $this->transferBalances());
        $this->assertEquals($received->id, $purchase->purchase_lines()->first()->id);
        $this->assertSame('Edited transfer', $sell->fresh()->additional_notes);
    }

    public function test_receipts_and_deductions_update_one_location_balance(): void
    {
        $util = $this->app->make(ProductUtil::class);
        $util->updateProductQuantity(12, 589, 2701, 3, 0, null, false);
        $util->updateProductQuantity(12, 589, 2701, 2, 0, null, false);
        $util->decreaseProductQuantity(589, 2701, 12, 1);
        $this->assertEquals(1, VariationLocationDetails::count());
        $this->assertEquals(4, VariationLocationDetails::first()->qty_available);
    }

    public function test_reducing_partially_returned_sale_preserves_all_returned_allocations(): void
    {
        DB::table('transaction_sell_lines')->update(['quantity' => 8, 'quantity_returned' => 4]);
        DB::table('transaction_sell_lines_purchase_lines')->update([
            'purchase_line_id' => 1, 'quantity' => 10, 'qty_returned' => 4,
        ]);
        PurchaseLine::find(1)->update(['quantity' => 10, 'quantity_sold' => 6]);
        $util = $this->app->make(TransactionUtil::class);
        DB::transaction(fn () => $util->adjustMappingPurchaseSell('final', Transaction::find(1), [
            'id' => 1, 'location_id' => 12, 'accounting_method' => 'fifo', 'pos_settings' => [],
        ]));
        $this->assertEquals(4, PurchaseLine::find(1)->quantity_sold);
        $mapping = DB::table('transaction_sell_lines_purchase_lines')->first();
        $this->assertEquals(8, $mapping->quantity);
        $this->assertEquals(4, $mapping->qty_returned);
        $util->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 0, 4, false);
        $this->assertEquals(8, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_non_stock_product_return_needs_no_purchase_mapping(): void
    {
        DB::table('products')->update(['enable_stock' => false]);
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $this->app->make(TransactionUtil::class)->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 1, 0, false);
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals(0, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_return_edit_uses_net_delta_when_previously_returned_stock_was_consumed(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        TransactionSellLine::find(1)->update(['quantity' => 10, 'quantity_returned' => 4]);
        PurchaseLine::find(1)->update(['quantity' => 10, 'quantity_sold' => 10]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 10, 'qty_returned' => 4]);
        DB::table('transactions')->insert(['id' => 3, 'type' => 'sell_return', 'return_parent_id' => 1]);
        TransactionSellLine::create([
            'transaction_id' => 3, 'product_id' => 589, 'variation_id' => 2701,
            'parent_sell_line_id' => 1, 'quantity' => 4, 'lot_no_line_id' => 1,
        ]);
        DB::table('variation_location_details')->insert([
            'product_id' => 589, 'variation_id' => 2701, 'product_variation_id' => 1,
            'location_id' => 12, 'qty_available' => 0,
        ]);
        $util = \Mockery::mock(TransactionUtil::class)->makePartial();
        $util->shouldReceive('updatePaymentStatus')->andReturn('paid');
        $util->shouldReceive('activityLog')->andReturnNull();
        $input = [
            'invoice_no' => 'RETURN-EDIT', 'transaction_date' => '2026-09-02 13:00:00',
            'products' => [['sell_line_id' => 1, 'quantity' => 4, 'unit_price_inc_tax' => 100]],
        ];
        DB::transaction(fn () => $util->updateSellReturn(3, $input, 1, false));
        $this->assertEquals(4, TransactionSellLine::find(1)->quantity_returned);
        $this->assertEquals(10, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(0, VariationLocationDetails::first()->qty_available);
        $input['products'][0]['quantity'] = 5;
        DB::transaction(fn () => $util->updateSellReturn(3, $input, 1, false));
        $this->assertEquals(5, TransactionSellLine::find(1)->quantity_returned);
        $this->assertEquals(9, PurchaseLine::find(1)->quantity_sold);
        $this->assertEquals(1, VariationLocationDetails::first()->qty_available);
    }

    public function test_whole_unit_product_cannot_sell_a_fraction(): void
    {
        DB::table('products')->update(['unit_id' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        TransactionSellLine::find(1)->update(['quantity' => 0.5]);
        $this->expectException(PurchaseSellMismatch::class);
        $this->mapLines([TransactionSellLine::find(1)]);
    }

    public function test_incomplete_transfer_with_existing_allocations_cannot_be_deleted(): void
    {
        $this->transferPair();
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 1, 'purchase_line_id' => 1]);
        PurchaseLine::find(1)->update(['quantity_sold' => 1]);
        $controller = $this->transferController();
        $this->statusRequest('pending');
        $this->assertEquals(0, $controller->destroy(1)['success']);
        $this->assertNotNull(Transaction::find(1));
        $this->assertEquals(1, PurchaseLine::find(1)->quantity_sold);
    }

    public function test_deleted_sale_reverses_fully_returned_batches_as_one_line(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 1, 'qty_returned' => 1],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 1, 'qty_returned' => 1],
        ]);
        TransactionSellLine::find(1)->delete();
        DB::transaction(fn () => $this->app->make(TransactionUtil::class)->adjustMappingPurchaseSell(
            'final', Transaction::find(1), ['id' => 1, 'location_id' => 12], [1]
        ));
        $this->assertEquals(0, DB::table('transaction_sell_lines_purchase_lines')->count());
        $this->assertEquals([0, 0], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
    }

    public function test_small_fractional_returns_are_applied_to_all_batches(): void
    {
        TransactionSellLine::find(1)->update(['lot_no_line_id' => null, 'quantity' => 0.0002]);
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 0.0001],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 0.0001],
        ]);
        DB::table('purchase_lines')->whereIn('id', [1, 2])->update(['quantity_sold' => 0.0001]);
        $this->app->make(TransactionUtil::class)->updateQuantitySoldFromSellLine(TransactionSellLine::find(1), 0.0002, 0, false);
        $this->assertEqualsWithDelta(0.0002, DB::table('transaction_sell_lines_purchase_lines')->sum('qty_returned'), 0.0000001);
        $this->assertEquals([0, 0], PurchaseLine::whereIn('id', [1, 2])->orderBy('id')->pluck('quantity_sold')->all());
    }

    public function test_lot_history_summary_excludes_pending_receipts_and_respects_location_access(): void
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->delete();
        DB::table('transactions')->insert(['id' => 3, 'type' => 'purchase_transfer', 'status' => 'pending', 'location_id' => 13]);
        DB::table('purchase_lines')->insert(['id' => 4, 'transaction_id' => 3, 'lot_number' => 'INVOICE-LOT']);
        $controller = $this->app->make(LotController::class);
        $method = new \ReflectionMethod($controller, 'lotStockSummary');
        $method->setAccessible(true);
        $this->assertEquals(2, $method->invoke($controller, 1, 'INVOICE-LOT', 'all')[0]);
        $this->assertEquals(0, $method->invoke($controller, 1, 'INVOICE-LOT', [13])[0]);
        $this->assertEquals(0, $method->invoke($controller, 1, 'INVOICE-LOT', 'all', 13)[0]);
        DB::table('transactions')->where('id', 3)->update(['status' => 'received']);
        $this->assertEquals(3, $method->invoke($controller, 1, 'INVOICE-LOT', 'all')[0]);
    }

    private function preparedHistoryRows(?string $type = null, ?string $start = null, ?string $end = null)
    {
        $rows = collect([
            (object) ['movement_date' => '2026-09-01 12:00:00', 'movement_type' => 'purchase', 'qty_in' => 1, 'qty_out' => 0],
            (object) ['movement_date' => '2026-09-02 12:00:00', 'movement_type' => 'transfer', 'qty_in' => 1, 'qty_out' => 1],
            (object) ['movement_date' => '2026-09-03 12:00:00', 'movement_type' => 'sell', 'qty_in' => 0, 'qty_out' => 1],
        ]);
        $method = new \ReflectionMethod(LotController::class, 'prepareLotHistoryRows');
        $method->setAccessible(true);

        return $method->invoke($this->app->make(LotController::class), $rows, $type, $start, $end);
    }

    public function test_lot_history_quantity_change_and_new_quantity_follow_each_movement(): void
    {
        $rows = $this->preparedHistoryRows();
        $this->assertEquals([-1, 0, 1], $rows->pluck('quantity_change')->all());
        $this->assertEquals([0, 1, 1], $rows->pluck('balance_qty')->all());
    }

    public function test_lot_history_date_and_type_filters_preserve_previous_quantity(): void
    {
        $rows = $this->preparedHistoryRows('transfer', '2026-09-02', '2026-09-02');
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows->first()->balance_qty);
        $this->assertEquals(0, $rows->first()->quantity_change);
        $this->assertEquals(0, $this->preparedHistoryRows('sell')->first()->balance_qty);
    }

    public function test_lot_history_ajax_returns_quantity_columns_and_excludes_pending_stock(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('created_by')->nullable();
            $table->integer('contact_id')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('single');
        });
        Schema::table('variations', function (Blueprint $table) {
            $table->string('name')->default('DUMMY');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->string('short_name')->nullable();
        });
        Schema::table('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->integer('stock_adjustment_line_id')->nullable();
        });
        Schema::create('contacts', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->nullable();
            $table->string('supplier_business_name')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->integer('id');
            $table->string('surname')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('transaction_id');
        });
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values));
        DB::connection()->getPdo()->sqliteCreateFunction('IF', fn ($condition, $yes, $no) => $condition ? $yes : $no);
        DB::table('purchase_lines')->where('id', 3)->delete();
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        DB::table('transaction_sell_lines')->delete();
        DB::table('transactions')->insert(['id' => 3, 'type' => 'purchase_transfer', 'status' => 'pending', 'location_id' => 13]);
        DB::table('purchase_lines')->insert(['id' => 4, 'transaction_id' => 3, 'lot_number' => 'INVOICE-LOT']);
        $this->transferController();
        auth()->user()->shouldReceive('permitted_locations')->andReturn('all');
        session()->put('business', ['date_format' => 'Y-m-d', 'time_format' => 24, 'quantity_precision' => 2]);
        session()->put('currency', ['decimal_separator' => '.', 'thousand_separator' => ',']);
        $request = Request::create('/smart-stock-inventory/lot/history/INVOICE-LOT', 'GET', [
            'draw' => 1, 'start' => 0, 'length' => 25, 'movement_type' => 'all',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->app->instance('request', $request);
        $response = $this->app->make(LotController::class)->history($request, 'INVOICE-LOT')->getData(true);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertEquals(1, $response['current_stock']);
        $this->assertCount(1, $response['data']);
        $this->assertStringContainsString('+1.00', strip_tags($response['data'][0]['quantity_change']));
        $this->assertStringContainsString('data-orig-value="1"', $response['data'][0]['qty_in']);
        $this->assertStringContainsString('data-orig-value="1"', $response['data'][0]['balance_qty']);
    }
}

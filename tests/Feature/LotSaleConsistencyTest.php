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
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        DB::table('transaction_sell_lines_purchase_lines')->update(['purchase_line_id' => 0]);
        $this->receiveLot(2);
        $this->assertEquals(2, DB::table('transaction_sell_lines_purchase_lines')->value('purchase_line_id'));
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
        $user->shouldReceive('can')->andReturn(true);
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
}

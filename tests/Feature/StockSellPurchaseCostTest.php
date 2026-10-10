<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SmartStockInventory\Http\Controllers\StockReportController;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class StockSellPurchaseCostTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'stock_cost_test',
            'database.connections.stock_cost_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('transaction_id')->nullable();
            $table->integer('variation_id')->default(1);
            $table->double('purchase_price_inc_tax')->nullable();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('business_id')->default(1);
            $table->integer('location_id')->default(1);
            $table->string('type');
            $table->string('status')->default('received');
            $table->string('transaction_date');
        });
        Schema::create('variations', function (Blueprint $table) {
            $table->integer('id');
            $table->string('sub_sku');
            $table->double('dpp_inc_tax')->nullable();
            $table->double('default_purchase_price')->nullable();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->integer('id');
            $table->integer('transaction_id')->default(10);
            $table->integer('variation_id')->default(1);
            $table->double('quantity');
            $table->integer('lot_no_line_id')->nullable();
        });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->integer('sell_line_id');
            $table->integer('purchase_line_id');
            $table->double('quantity');
            $table->double('qty_returned')->default(0);
        });

        DB::table('purchase_lines')->insert([
            ['id' => 1, 'transaction_id' => 1, 'purchase_price_inc_tax' => 100],
            ['id' => 2, 'transaction_id' => 2, 'purchase_price_inc_tax' => 80],
        ]);
        DB::table('transactions')->insert([
            ['id' => 1, 'type' => 'purchase', 'transaction_date' => '2026-10-01'],
            ['id' => 2, 'type' => 'purchase', 'transaction_date' => '2026-10-02'],
            ['id' => 10, 'type' => 'sell', 'transaction_date' => '2026-10-03'],
        ]);
        DB::table('variations')->insert(['id' => 1, 'sub_sku' => 'SKU-1', 'dpp_inc_tax' => 70, 'default_purchase_price' => 60]);
        DB::table('transaction_sell_lines')->insert(['id' => 1, 'quantity' => 3]);
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            ['sell_line_id' => 1, 'purchase_line_id' => 1, 'quantity' => 2],
            ['sell_line_id' => 1, 'purchase_line_id' => 2, 'quantity' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('stock_cost_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    private function reportCost()
    {
        $controller = $this->app->make(StockReportController::class);
        $queryMethod = new \ReflectionMethod($controller, 'linkedPurchaseCosts');
        $queryMethod->setAccessible(true);
        $expressionsMethod = new \ReflectionMethod($controller, 'purchaseCostExpressions');
        $expressionsMethod->setAccessible(true);
        [$price, $total] = $expressionsMethod->invoke($controller);

        return DB::table('transaction_sell_lines')
            ->join('transactions as t', 'transaction_sell_lines.transaction_id', '=', 't.id')
            ->join('variations as v', 'transaction_sell_lines.variation_id', '=', 'v.id')
            ->leftJoinSub($queryMethod->invoke($controller), 'pc', function ($join) {
                $join->on('pc.sell_line_id', '=', 'transaction_sell_lines.id');
            })
            ->leftJoin('purchase_lines as lot_pl', 'transaction_sell_lines.lot_no_line_id', '=', 'lot_pl.id')
            ->selectRaw($price.' as purchase_price, '.$total.' as purchase_total')
            ->where('transaction_sell_lines.id', 1)
            ->first();
    }

    public function test_corrected_batch_cost_updates_weighted_price_and_total()
    {
        $before = $this->reportCost();
        $this->assertEqualsWithDelta(280 / 3, $before->purchase_price, 0.00001);
        $this->assertEquals(280, $before->purchase_total);

        DB::table('purchase_lines')->where('id', 1)->update(['purchase_price_inc_tax' => 90]);
        $after = $this->reportCost();
        $this->assertEqualsWithDelta(260 / 3, $after->purchase_price, 0.00001);
        $this->assertEquals(260, $after->purchase_total);

        DB::table('purchase_lines')->insert(['id' => 3, 'purchase_price_inc_tax' => 50]);
        $this->assertEquals(260, $this->reportCost()->purchase_total);
    }

    public function test_returns_do_not_reduce_cost_of_the_original_sale()
    {
        DB::table('transaction_sell_lines_purchase_lines')->update(['qty_returned' => 1]);
        $this->assertEquals(280, $this->reportCost()->purchase_total);
    }

    public function test_zero_cost_is_a_valid_cost()
    {
        DB::table('purchase_lines')->update(['purchase_price_inc_tax' => 0]);
        $cost = $this->reportCost();
        $this->assertEquals(0, $cost->purchase_price);
        $this->assertEquals(0, $cost->purchase_total);
        $this->assertNotNull($cost->purchase_price);
    }

    public function test_unlinked_sale_uses_latest_purchase_for_its_sku_and_updates_after_correction()
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        $cost = $this->reportCost();
        $this->assertEquals(80, $cost->purchase_price);
        $this->assertEquals(240, $cost->purchase_total);
        DB::table('purchase_lines')->where('id', 2)->update(['purchase_price_inc_tax' => 85]);
        $this->assertEquals(255, $this->reportCost()->purchase_total);
    }

    public function test_explicit_lot_takes_priority_over_mapping_and_latest_sku_cost()
    {
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => 1]);
        $this->assertEquals(300, $this->reportCost()->purchase_total);
        DB::table('purchase_lines')->where('id', 1)->update(['purchase_price_inc_tax' => 90]);
        $this->assertEquals(270, $this->reportCost()->purchase_total);
    }

    public function test_partial_mapping_uses_lot_then_sku_fallback()
    {
        DB::table('transaction_sell_lines_purchase_lines')->where('purchase_line_id', 2)->delete();
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => 1]);
        $this->assertEquals(300, $this->reportCost()->purchase_total);
        DB::table('transaction_sell_lines')->update(['lot_no_line_id' => null]);
        $this->assertEquals(240, $this->reportCost()->purchase_total);
    }

    public function test_deleted_or_missing_batch_cost_falls_back_to_available_sku_cost()
    {
        DB::table('purchase_lines')->where('id', 2)->update(['purchase_price_inc_tax' => null]);
        $this->assertEquals(300, $this->reportCost()->purchase_total);
        DB::table('purchase_lines')->where('id', 2)->delete();
        $this->assertEquals(300, $this->reportCost()->purchase_total);
    }

    public function test_sku_fallback_does_not_use_another_business_location_variation_or_pending_purchase()
    {
        DB::table('transaction_sell_lines_purchase_lines')->delete();
        foreach ([['business_id' => 2], ['location_id' => 2], ['status' => 'pending'], ['type' => 'purchase_order']] as $index => $attributes) {
            $id = 20 + $index;
            DB::table('transactions')->insert($attributes + ['id' => $id, 'type' => 'purchase', 'transaction_date' => '2026-10-10']);
            DB::table('purchase_lines')->insert(['id' => $id, 'transaction_id' => $id, 'purchase_price_inc_tax' => 999]);
        }
        DB::table('purchase_lines')->insert(['id' => 30, 'transaction_id' => 2, 'variation_id' => 2, 'purchase_price_inc_tax' => 999]);
        $this->assertEquals(80, $this->reportCost()->purchase_price);
    }

    public function test_sku_default_is_used_when_no_purchase_price_is_available()
    {
        DB::table('purchase_lines')->delete();
        $this->assertEquals(70, $this->reportCost()->purchase_price);
        DB::table('variations')->update(['dpp_inc_tax' => null]);
        $this->assertEquals(60, $this->reportCost()->purchase_price);
        DB::table('variations')->update(['default_purchase_price' => null]);
        $this->assertNull($this->reportCost()->purchase_price);
    }

    public function test_purchase_activity_displays_old_and_corrected_cost_including_zero()
    {
        $this->app['request']->headers->set('X-Requested-With', 'XMLHttpRequest');
        session()->put('business', ['currency_symbol_placement' => 'before', 'currency_precision' => 2]);
        session()->put('currency', ['symbol' => '$', 'decimal_separator' => '.', 'thousand_separator' => ',']);
        $activity = new Activity();
        $activity->properties = [
            'old' => ['purchase_costs' => [['id' => 1, 'lot_number' => 'LOT <1>', 'purchase_price_inc_tax' => 100]]],
            'attributes' => ['purchase_costs' => [['id' => 1, 'lot_number' => 'LOT <1>', 'purchase_price_inc_tax' => 0]]],
        ];

        $html = view('sale_pos.partials.activity_row', compact('activity'))->render();
        $this->assertStringContainsString('Purchase Cost (Incl. Tax)', $html);
        $this->assertStringContainsString('LOT &lt;1&gt;', $html);
        $this->assertStringContainsString('$ 100.00', $html);
        $this->assertStringContainsString('$ 0.00', $html);
    }
}

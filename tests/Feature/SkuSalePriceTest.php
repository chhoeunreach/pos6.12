<?php

namespace Tests\Feature;

use App\Events\SellCreatedOrModified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\SmartStockInventory\Services\SkuSalePriceService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SkuSalePriceTest extends TestCase
{
    private $original;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original = config('database.default');
        config(['database.default' => 'sku_sale_test', 'database.connections.sku_sale_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Event::fake([SellCreatedOrModified::class]);
        session(['business.transaction_edit_days' => null]);
        Schema::create('transactions', function (Blueprint $t) {
            $t->integer('id'); $t->integer('business_id')->default(1); $t->integer('location_id')->default(1); $t->integer('created_by')->default(7); $t->integer('commission_agent')->nullable();
            $t->string('type')->default('sell'); $t->string('status')->default('final'); $t->string('sub_type')->nullable(); $t->string('zatca_status')->nullable();
            $t->string('transaction_date')->default('2026-11-11'); $t->string('invoice_no')->default('INV-1'); $t->integer('return_parent_id')->nullable();
            $t->double('total_before_tax')->default(250); $t->double('tax_amount')->default(11); $t->double('final_total')->default(243);
            $t->string('discount_type')->nullable()->default('percentage'); $t->double('discount_amount')->default(10); $t->integer('tax_id')->nullable();
            $t->double('rp_redeemed_amount')->default(5); $t->string('packing_charge_type')->nullable()->default('percent'); $t->double('packing_charge')->default(2);
            $t->double('round_off_amount')->default(0); $t->string('payment_status')->default('paid'); $t->timestamps();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $t) {
            $t->integer('id'); $t->integer('transaction_id')->default(1); $t->integer('variation_id')->default(7); $t->integer('parent_sell_line_id')->nullable();
            $t->double('unit_price_before_discount')->default(100); $t->double('unit_price')->default(90); $t->double('unit_price_inc_tax')->default(99);
            $t->string('line_discount_type')->nullable()->default('percentage'); $t->double('line_discount_amount')->default(10); $t->double('item_tax')->default(9);
            $t->integer('tax_id')->nullable(); $t->double('quantity')->default(2); $t->double('quantity_returned')->default(0); $t->integer('lot_no_line_id')->default(77); $t->timestamps();
        });
        Schema::create('transaction_payments', function (Blueprint $t) { $t->integer('transaction_id'); $t->double('amount'); $t->boolean('is_return')->default(false); });
        Schema::create('business', function (Blueprint $t) { $t->integer('id'); $t->text('pos_settings')->nullable(); });
        Schema::create('smart_stock_action_logs', function (Blueprint $t) {
            $t->increments('id');
            foreach (['user_id', 'business_id', 'location_id', 'record_id', 'reference_id'] as $column) { $t->integer($column)->nullable(); }
            foreach (['user_name', 'module_name', 'table_name', 'action_type', 'reference_type', 'old_data', 'new_data', 'reason', 'ip_address'] as $column) { $t->text($column)->nullable(); }
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('variation_location_details', function (Blueprint $t) { $t->integer('variation_id'); $t->double('qty_available'); });
        DB::table('transactions')->insert(['id' => 1]);
        DB::table('transaction_sell_lines')->insert(['id' => 11]);
        DB::table('transaction_sell_lines')->insert(['id' => 12, 'variation_id' => 8, 'quantity' => 1, 'unit_price_before_discount' => 52, 'unit_price' => 52, 'unit_price_inc_tax' => 52, 'item_tax' => 0, 'line_discount_amount' => 0]);
        DB::table('transaction_payments')->insert(['transaction_id' => 1, 'amount' => 250]);
        DB::table('business')->insert(['id' => 1, 'pos_settings' => json_encode(['amount_rounding_method' => .05])]);
        DB::table('variation_location_details')->insert(['variation_id' => 7, 'qty_available' => 5]);
        $this->user = new class {
            public $id = 7;
            public $username = 'Staff';
            public $permissions = ['stock_inventory.view', 'sell.update', 'sell.view'];
            public function can($permission) { return in_array($permission, $this->permissions, true); }
        };
    }

    protected function tearDown(): void
    {
        DB::purge('sku_sale_test'); config(['database.default' => $this->original]);
        parent::tearDown();
    }

    private function data(float $price): array
    {
        $service = app(SkuSalePriceService::class);
        [$sale, $lines] = $service->context(1, 7, 1, [1], $this->user);
        return ['cost_version' => $service->version($sale, $lines), 'prices' => [['id' => 11, 'unit_price' => $price]]];
    }

    public function test_sale_unit_price_update_preserves_discounts_taxes_quantities_and_other_skus(): void
    {
        $service = app(SkuSalePriceService::class); $data = $this->data(150);
        $service->update(1, 7, 1, [1], $this->user, $data, '127.0.0.1');
        $line = DB::table('transaction_sell_lines')->where('id', 11)->first();
        $this->assertEquals(150, $line->unit_price_before_discount);
        $this->assertEquals(135, $line->unit_price);
        $this->assertEquals(13.5, $line->item_tax);
        $this->assertEquals(148.5, $line->unit_price_inc_tax);
        $this->assertEquals(10, $line->line_discount_amount);
        $this->assertEquals(2, $line->quantity); $this->assertEquals(77, $line->lot_no_line_id);
        $this->assertEquals(52, DB::table('transaction_sell_lines')->where('id', 12)->value('unit_price_inc_tax'));
        $sale = DB::table('transactions')->where('id', 1)->first();
        $this->assertEquals(349, $sale->total_before_tax);
        $this->assertEqualsWithDelta(15.455, $sale->tax_amount, .00001);
        $this->assertEqualsWithDelta(338.55, $sale->final_total, .00001);
        $this->assertEqualsWithDelta(.015, $sale->round_off_amount, .00001);
        $this->assertSame('partial', $sale->payment_status);
        $this->assertEquals(5, DB::table('variation_location_details')->value('qty_available'));
        $this->assertSame(1, DB::table('smart_stock_action_logs')->count());
        Event::assertDispatched(SellCreatedOrModified::class);
        try { $service->update(1, 7, 1, [1], $this->user, $data, '127.0.0.1'); $this->fail('Expected stale price rejection'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $this->assertSame(1, DB::table('smart_stock_action_logs')->count());
    }

    public function test_sale_unit_prices_are_attached_only_to_sales_of_the_current_sku_and_locations(): void
    {
        DB::table('transactions')->insert(['id' => 2, 'type' => 'production_sell']);
        DB::table('transaction_sell_lines')->insert(['id' => 21, 'transaction_id' => 2]);
        $rows = app(SkuSalePriceService::class)->attach(collect([['transaction_id' => 1], ['transaction_id' => 2]]), 1, 7, [1]);
        $this->assertCount(1, $rows[0]['sale_prices']);
        $this->assertEquals(100, $rows[0]['sale_prices'][0]['unit_price']);
        $this->assertSame([], $rows[1]['sale_prices']);
        $rows = app(SkuSalePriceService::class)->attach(collect([['transaction_id' => 1]]), 1, 7, [2]);
        $this->assertSame([], $rows[0]['sale_prices']);
    }

    public function test_sale_price_scope_rejects_other_business_location_sku_and_missing_permission(): void
    {
        $service = app(SkuSalePriceService::class);
        foreach ([[2, 7, [1]], [1, 7, [2]], [1, 99, [1]]] as [$business, $variation, $locations]) {
            try { $service->context($business, $variation, 1, $locations, $this->user); $this->fail('Expected scope rejection'); }
            catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        }
        $this->user->permissions = ['stock_inventory.view'];
        try { $service->context(1, 7, 1, [1], $this->user); $this->fail('Expected permission rejection'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_sale_price_editor_rejects_returned_sales_and_foreign_line_ids(): void
    {
        $service = app(SkuSalePriceService::class); $data = $this->data(150);
        $data['prices'][0]['id'] = 12;
        try { $service->update(1, 7, 1, [1], $this->user, $data, '127.0.0.1'); $this->fail('Expected wrong line rejection'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        DB::table('transactions')->insert(['id' => 2, 'type' => 'sell_return', 'return_parent_id' => 1]);
        try { $service->context(1, 7, 1, [1], $this->user); $this->fail('Expected returned sale rejection'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->assertSame(0, DB::table('smart_stock_action_logs')->count());
    }

    public function test_price_below_fixed_discount_is_rejected_without_changing_sale(): void
    {
        DB::table('transaction_sell_lines')->where('id', 11)->update(['line_discount_type' => 'fixed', 'line_discount_amount' => 20]);
        $service = app(SkuSalePriceService::class);
        try { $service->update(1, 7, 1, [1], $this->user, $this->data(10), '127.0.0.1'); $this->fail('Expected discount rejection'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->assertEquals(100, DB::table('transaction_sell_lines')->where('id', 11)->value('unit_price_before_discount'));
        $this->assertEquals(243, DB::table('transactions')->where('id', 1)->value('final_total'));
    }
    public function test_sale_price_editor_honors_ownership_and_invoice_editing_locks(): void
    {
        $service = app(SkuSalePriceService::class);
        $this->user->permissions = ['stock_inventory.view', 'sell.update', 'view_own_sell_only'];
        DB::table('transactions')->where('id', 1)->update(['created_by' => 8]);
        try { $service->context(1, 7, 1, [1], $this->user); $this->fail('Expected ownership rejection'); }
        catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        DB::table('transactions')->where('id', 1)->update(['created_by' => 7, 'zatca_status' => 'success']);
        try { $service->context(1, 7, 1, [1], $this->user); $this->fail('Expected synced invoice rejection'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        DB::table('transactions')->where('id', 1)->update(['zatca_status' => null, 'transaction_date' => today()->subDays(10)->toDateString()]);
        session(['business.transaction_edit_days' => 1]);
        try { $service->context(1, 7, 1, [1], $this->user); $this->fail('Expected editing period rejection'); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
    }

}

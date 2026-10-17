<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepairTransferReceiptsCommandTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'repair_transfer_test',
            'database.connections.repair_transfer_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);

        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->default(1);
            $table->integer('location_id')->default(1);
            $table->string('type');
            $table->string('status');
            $table->string('ref_no')->nullable();
            $table->integer('transfer_parent_id')->nullable();
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id');
            $table->integer('product_id')->default(10);
            $table->integer('variation_id');
            $table->double('quantity')->default(0);
            $table->integer('lot_no_line_id')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('transaction_id');
            $table->integer('product_id')->default(10);
            $table->integer('variation_id');
            $table->string('lot_number')->nullable();
            $table->double('quantity')->default(0);
            $table->double('quantity_sold')->default(0);
            $table->double('quantity_returned')->default(0);
            $table->double('quantity_adjusted')->default(0);
            $table->double('mfg_quantity_used')->default(0);
            $table->timestamps();
        });
        Schema::create('transaction_sell_lines_purchase_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sell_line_id');
            $table->integer('purchase_line_id');
            $table->double('quantity')->default(0);
            $table->double('qty_returned')->default(0);
            $table->timestamps();
        });
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id');
            $table->integer('variation_id');
            $table->integer('location_id');
            $table->double('qty_available');
            $table->timestamps();
        });
        Schema::create('smart_stock_action_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->integer('business_id')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('action_type')->nullable();
            $table->string('reference_type')->nullable();
            $table->integer('reference_id')->nullable();
            $table->text('old_data')->nullable();
            $table->text('new_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // Source transfer sent 2 (two lines) from location 1.
        DB::table('transactions')->insert([
            'id' => 100, 'business_id' => 1, 'location_id' => 1, 'type' => 'sell_transfer', 'status' => 'final', 'ref_no' => 'ST-TEST-1',
        ]);
        DB::table('transaction_sell_lines')->insert([
            ['transaction_id' => 100, 'variation_id' => 500, 'quantity' => 1],
            ['transaction_id' => 100, 'variation_id' => 500, 'quantity' => 1],
        ]);

        // Destination received 3: a blank line holding 2 plus a proper lot line.
        DB::table('transactions')->insert([
            'id' => 200, 'business_id' => 1, 'location_id' => 2, 'type' => 'purchase_transfer', 'status' => 'received', 'transfer_parent_id' => 100,
        ]);
        DB::table('purchase_lines')->insert([
            ['id' => 201, 'transaction_id' => 200, 'variation_id' => 500, 'lot_number' => '', 'quantity' => 2],
            ['id' => 202, 'transaction_id' => 200, 'variation_id' => 500, 'lot_number' => 'LOT-A', 'quantity' => 1],
        ]);

        DB::table('variation_location_details')->insert([
            'product_id' => 10, 'variation_id' => 500, 'location_id' => 2, 'qty_available' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('repair_transfer_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    public function test_dry_run_reports_mismatch_without_changing_data(): void
    {
        $exit = Artisan::call('stock:repair-transfer-receipts');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('FIXABLE', $output);
        $this->assertSame(2.0, (float) DB::table('purchase_lines')->where('id', 201)->value('quantity'));
        $this->assertSame(5.0, (float) DB::table('variation_location_details')->where('variation_id', 500)->value('qty_available'));
        $this->assertSame(0, DB::table('smart_stock_action_logs')->count());
    }

    public function test_apply_corrects_receiving_lot_and_balance_then_becomes_idempotent(): void
    {
        Artisan::call('stock:repair-transfer-receipts', ['--apply' => true, '--force' => true]);

        $this->assertSame(1.0, (float) DB::table('purchase_lines')->where('id', 201)->value('quantity'));
        $this->assertSame(1.0, (float) DB::table('purchase_lines')->where('id', 202)->value('quantity'));
        $this->assertSame(4.0, (float) DB::table('variation_location_details')->where('variation_id', 500)->value('qty_available'));

        $received = (float) DB::table('purchase_lines')->where('transaction_id', 200)->where('variation_id', 500)->sum('quantity');
        $sent = (float) DB::table('transaction_sell_lines')->where('transaction_id', 100)->where('variation_id', 500)->sum('quantity');
        $this->assertSame($sent, $received);

        $log = DB::table('smart_stock_action_logs')->first();
        $this->assertNotNull($log);
        $this->assertSame('transfer_receiving_lot_correction', $log->action_type);
        $this->assertSame(100, (int) $log->reference_id);

        Artisan::call('stock:repair-transfer-receipts');
        $this->assertStringContainsString('No receiving-lot mismatches found', Artisan::output());
    }

    public function test_transfer_filter_targets_a_single_lot(): void
    {
        Artisan::call('stock:repair-transfer-receipts', ['--apply' => true, '--force' => true, '--transfer' => 'ST-TEST-1']);

        $this->assertSame(1.0, (float) DB::table('purchase_lines')->where('id', 201)->value('quantity'));
        $this->assertSame(1, DB::table('smart_stock_action_logs')->count());
    }

    public function test_does_not_reduce_a_line_that_is_consumed_by_mappings(): void
    {
        // Line 202's unit is fully consumed by a downstream sale mapping.
        DB::table('transaction_sell_lines')->insert(['id' => 999, 'transaction_id' => 300, 'variation_id' => 500, 'quantity' => 1]);
        DB::table('transaction_sell_lines_purchase_lines')->insert(['sell_line_id' => 999, 'purchase_line_id' => 202, 'quantity' => 1]);
        DB::table('purchase_lines')->where('id', 202)->update(['quantity_sold' => 1]);

        Artisan::call('stock:repair-transfer-receipts', ['--apply' => true, '--force' => true]);

        // Excess of 1 must come from the blank line 201, never from the consumed lot 202.
        $this->assertSame(1.0, (float) DB::table('purchase_lines')->where('id', 201)->value('quantity'));
        $this->assertSame(1.0, (float) DB::table('purchase_lines')->where('id', 202)->value('quantity'));
    }
}

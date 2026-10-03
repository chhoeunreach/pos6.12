<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HrSellManagement\Http\Controllers\HrSellController;
use Tests\TestCase;

class HrSellListFilterTest extends TestCase
{
    public function test_multiple_sell_types_include_aliases_and_preserve_phone_and_pagination_filters()
    {
        $original = config('database.connections.hr');
        config(['database.connections.hr' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('hr');

        try {
            Schema::connection('hr')->create('users', function (Blueprint $table) {
                $table->integer('id');
                $table->string('name');
                $table->string('username');
            });
            Schema::connection('hr')->create('sell_out_reports', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->nullable();
                foreach (['invoice_no', 'customer_phone', 'customer_name', 'seller_name', 'branch_name', 'service_type', 'created_at'] as $column) {
                    $table->string($column);
                }
                $table->decimal('total_amount');
            });

            foreach (['sell', 'Sell', 'repair', 'buy_in'] as $type) {
                DB::connection('hr')->table('sell_out_reports')->insert([
                    'invoice_no' => $type, 'customer_phone' => '012345678', 'customer_name' => 'Customer',
                    'seller_name' => 'Staff', 'branch_name' => 'Branch', 'service_type' => $type,
                    'created_at' => '2026-10-01 08:00:00', 'total_amount' => 10,
                ]);
            }

            $controller = $this->app->make(HrSellController::class);
            $method = new \ReflectionMethod($controller, 'posHrSellListData');
            $method->setAccessible(true);
            $request = Request::create('/hr-sell/sales', 'GET', ['sell_type' => ['sell', 'repair']]);
            [$rows] = $method->invoke($controller, $request);

            $this->assertSame(3, $rows->total());
            $this->assertSame(['Sell', 'repair', 'sell'], $rows->pluck('service_type')->sort()->values()->all());
            $this->assertSame('012345678', $rows->first()->customer_phone);
            parse_str(parse_url($rows->url(2), PHP_URL_QUERY), $pageQuery);
            $this->assertSame(['sell', 'repair'], $pageQuery['sell_type']);

            [$singleTypeRows] = $method->invoke($controller, Request::create('/hr-sell/sales', 'GET', ['sell_type' => 'sell']));
            $this->assertSame(2, $singleTypeRows->total());
            [$allRows] = $method->invoke($controller, Request::create('/hr-sell/sales'));
            $this->assertSame(4, $allRows->total());
        } finally {
            DB::purge('hr');
            config(['database.connections.hr' => $original]);
        }
    }
}

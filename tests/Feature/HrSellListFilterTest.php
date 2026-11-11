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
            Schema::connection('hr')->create('sell_out_report_lines', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('sell_out_report_id');
                foreach (['product_name', 'serial_number', 'imei', 'imei2', 'primary_identifier'] as $column) {
                    $table->string($column)->nullable();
                }
            });
            DB::connection('hr')->table('users')->insert(['id' => 7, 'name' => 'Employee Name', 'username' => 'EMP007']);

            foreach (['sell', 'Sell', 'repair', 'buy_in'] as $type) {
                DB::connection('hr')->table('sell_out_reports')->insert([
                    'invoice_no' => $type, 'customer_phone' => '012345678', 'customer_name' => 'Customer', 'user_id' => $type === 'sell' ? 7 : null,
                    'seller_name' => 'Staff', 'branch_name' => 'Branch', 'service_type' => $type,
                    'created_at' => '2026-10-01 08:00:00', 'total_amount' => 10,
                ]);
            }
            DB::connection('hr')->table('sell_out_report_lines')->insert([
                ['sell_out_report_id' => 1, 'product_name' => 'Phone', 'serial_number' => 'SN001', 'imei' => 'IMEI001', 'imei2' => null, 'primary_identifier' => 'SN001'],
                ['sell_out_report_id' => 1, 'product_name' => 'Charger', 'serial_number' => null, 'imei' => null, 'imei2' => null, 'primary_identifier' => null],
                ['sell_out_report_id' => 4, 'product_name' => 'Other sale product', 'serial_number' => 'OTHER', 'imei' => null, 'imei2' => null, 'primary_identifier' => null],
            ]);

            $controller = $this->app->make(HrSellController::class);
            $method = new \ReflectionMethod($controller, 'posHrSellListData');
            $method->setAccessible(true);
            $request = Request::create('/hr-sell/sales', 'GET', ['sell_type' => ['sell', 'repair']]);
            [$rows, , $sellTypes] = $method->invoke($controller, $request);

            $this->assertSame('Sellsim / លក់ស៊ីម', $sellTypes->get('sellsim'));
            $this->assertSame(3, $rows->total());
            $this->assertSame(['Sell', 'repair', 'sell'], $rows->pluck('service_type')->sort()->values()->all());
            $this->assertSame('012345678', $rows->first()->customer_phone);
            $sale = $rows->firstWhere('invoice_no', 'sell');
            $this->assertSame('Employee Name', $sale->staff_name);
            $this->assertSame('EMP007', $sale->staff_code);
            $this->assertSame(['Phone', 'Charger'], $sale->product_lines->pluck('product_name')->all());
            $this->assertSame(['SN001 / IMEI001', ''], $sale->product_lines->pluck('serial_identifier')->all());
            $this->assertSame('Staff', $rows->firstWhere('invoice_no', 'repair')->staff_name);
            $this->assertNull($rows->firstWhere('invoice_no', 'repair')->staff_code);
            $this->assertTrue($rows->firstWhere('invoice_no', 'repair')->product_lines->isEmpty());
            parse_str(parse_url($rows->url(2), PHP_URL_QUERY), $pageQuery);
            $this->assertSame(['sell', 'repair'], $pageQuery['sell_type']);

            [$singleTypeRows] = $method->invoke($controller, Request::create('/hr-sell/sales', 'GET', ['sell_type' => 'sell']));
            $this->assertSame(2, $singleTypeRows->total());
            [$allRows] = $method->invoke($controller, Request::create('/hr-sell/sales'));
            $this->assertSame(4, $allRows->total());

            foreach (['Sellsim', 'លក់ស៊ីម', 'Sellsim / លក់ស៊ីម'] as $type) {
                DB::connection('hr')->table('sell_out_reports')->insert([
                    'invoice_no' => $type, 'customer_phone' => '012345678', 'customer_name' => 'Customer',
                    'seller_name' => 'Staff', 'branch_name' => 'Branch', 'service_type' => $type,
                    'created_at' => '2026-10-01 08:00:00', 'total_amount' => 10,
                ]);
            }

            [$simRows, , $sellTypes] = $method->invoke($controller, Request::create('/hr-sell/sales', 'GET', ['sell_type' => ['sellsim']]));
            $this->assertSame(3, $simRows->total());
            $this->assertSame(['Sellsim / លក់ស៊ីម'], $simRows->pluck('service_type_label')->unique()->values()->all());
            $this->assertSame('Sellsim / លក់ស៊ីម', $sellTypes->get('sellsim'));
        } finally {
            DB::purge('hr');
            config(['database.connections.hr' => $original]);
        }
    }
}

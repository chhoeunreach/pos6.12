<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HrSellManagement\Http\Controllers\ReportController;
use Tests\TestCase;

class HrSellOfficeTimeTest extends TestCase
{
    /** @dataProvider schedules */
    public function test_commission_time_work_uses_employee_office_schedule($opening, $closing, $period, $minutes, $hours, $timeWork)
    {
        $originalConnection = config('database.connections.hr');
        config(['database.connections.hr' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('hr');

        try {
            Schema::connection('hr')->create('users', function (Blueprint $table) {
                $table->integer('id');
                $table->integer('office_time_id');
            });
            Schema::connection('hr')->create('office_times', function (Blueprint $table) {
                $table->integer('id');
                $table->string('opening_time');
                $table->string('closing_time');
            });
            DB::connection('hr')->table('users')->insert(['id' => 7, 'office_time_id' => 3]);
            DB::connection('hr')->table('office_times')->insert([
                'id' => 3, 'opening_time' => $opening, 'closing_time' => $closing,
            ]);

            $row = (object) [
                'user_id' => 7,
                'seller_key' => 'employee7',
                'sale_period' => $period === 'monthly' ? '2026-09' : '2026-09-01',
                'office_minutes' => 0,
                'sell_raw_total' => 75,
            ];
            $columns = [[
                'key' => 'sell', 'has_commission' => true, 'commission_rate' => 0.25,
                'sell_qty_thresholds' => ['full_time' => 100, 'part_time' => 50],
            ]];
            $method = new \ReflectionMethod(ReportController::class, 'prepareCommissionRows');
            $method->setAccessible(true);
            $result = $method->invoke(new ReportController(), collect([$row]), new Request(), $columns, $period)->first();

            $this->assertSame($minutes, $result->office_minutes);
            $this->assertSame($hours, $result->total_hour_day);
            $this->assertSame($timeWork, $result->time_work);
            $this->assertSame($timeWork === 'Full time' ? 0.0 : 75.0, (float) $result->sell_total);
        } finally {
            DB::purge('hr');
            config(['database.connections.hr' => $originalConnection]);
        }
    }

    public static function schedules(): array
    {
        return [
            'twelve hours monthly' => ['08:00:00', '20:00:00', 'monthly', 720, '12 hour', 'Full time'],
            'twelve hours AM PM' => ['8:00 AM', '8:00 PM', 'daily', 720, '12 hour', 'Full time'],
            'exactly eight hours' => ['08:00:00', '16:00:00', 'daily', 480, '8 hour', 'Full time'],
            'below eight hours' => ['08:00:00', '15:59:00', 'monthly', 479, '7 hour 59 min', 'Part-time'],
            'overnight eight hours' => ['20:00:00', '04:00:00', 'daily', 480, '8 hour', 'Full time'],
        ];
    }
}

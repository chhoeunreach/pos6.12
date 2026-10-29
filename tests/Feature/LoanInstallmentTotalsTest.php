<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Tests\TestCase;

class LoanInstallmentTotalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.installment_totals_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::connection('installment_totals_test')->create('loans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status');
            foreach (['item_price', 'down_payment', 'principal_amount', 'total_amount', 'paid_amount', 'balance_amount'] as $column) {
                $table->double($column);
            }
        });
        DB::connection('installment_totals_test')->table('loans')->insert([
            ['id' => 1, 'status' => 'active', 'item_price' => 150, 'down_payment' => 50, 'principal_amount' => 100, 'total_amount' => 120, 'paid_amount' => 20, 'balance_amount' => 100],
            ['id' => 2, 'status' => 'active', 'item_price' => 230.50, 'down_payment' => 30, 'principal_amount' => 200.50, 'total_amount' => 250.50, 'paid_amount' => 150, 'balance_amount' => 100.50],
            ['id' => 3, 'status' => 'completed', 'item_price' => 900, 'down_payment' => 100, 'principal_amount' => 800, 'total_amount' => 1000, 'paid_amount' => 1000, 'balance_amount' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('installment_totals_test');
        parent::tearDown();
    }

    private function controller()
    {
        return new class extends LoanInstallmentListController {
            public function totals($query): array { return $this->installmentListTotals($query); }
        };
    }

    public function test_footer_totals_include_all_filtered_rows_across_pages(): void
    {
        $query = DB::connection('installment_totals_test')->table('loans')
            ->where('status', 'active')->orderByRaw('id + ? DESC', [0])->offset(1)->limit(1);
        $totals = $this->controller()->totals($query);
        $this->assertSame([
            'item_price' => 380.5, 'down_payment' => 80.0, 'principal_amount' => 300.5,
            'total_amount' => 370.5, 'paid_amount' => 170.0, 'balance_amount' => 200.5,
        ], $totals);
        $this->assertCount(1, $query->get());
        $this->assertSame(1, $query->first()->id);
    }

    public function test_search_and_empty_results_update_totals(): void
    {
        $query = DB::connection('installment_totals_test')->table('loans')->where('id', 2);
        $this->assertEquals(230.50, $this->controller()->totals($query)['item_price']);
        $this->assertEquals(150, $this->controller()->totals($query)['paid_amount']);
        $empty = $this->controller()->totals($query->where('status', 'pending'));
        $this->assertCount(6, $empty);
        $this->assertSame([0.0], array_values(array_unique($empty)));
    }
}

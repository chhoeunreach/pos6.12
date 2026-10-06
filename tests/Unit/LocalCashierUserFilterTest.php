<?php

namespace Tests\Unit;

use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class LocalCashierUserFilterTest extends TestCase
{
    private function controller(): LocalCashierReportController
    {
        return (new ReflectionClass(LocalCashierReportController::class))->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, ...$arguments)
    {
        return (new ReflectionMethod(LocalCashierReportController::class, $method))
            ->invoke($this->controller(), ...$arguments);
    }

    public function test_same_user_id_in_other_database_is_not_selected(): void
    {
        $accessoryId = $this->invoke('moduleCashierFilterId', 7, 'accessory');
        $serviceId = $this->invoke('moduleCashierFilterId', 7, 'service');

        $this->assertNotSame($accessoryId, $serviceId);
        $this->assertSame([], $this->invoke('moduleCashierIds', [7], 'accessory'));
        $this->assertSame([], $this->invoke('moduleCashierIds', [7], 'service'));
        $this->assertSame([7], $this->invoke('moduleCashierIds', [7, $accessoryId, $serviceId], 'accessory'));
        $this->assertSame([7], $this->invoke('moduleCashierIds', [7, $accessoryId, $serviceId], 'service'));
    }

    public function test_selecting_pos_cashier_does_not_query_unrelated_module_cashier(): void
    {
        foreach (['accessory', 'service'] as $source) {
            $filters = [
                'user_ids' => [7, 8],
                'location_ids' => [$this->invoke('moduleLocationFilterId', 1, $source)],
            ];
            $this->assertSame(['rows' => [], 'total' => 0], $this->invoke(
                'getModuleSaleDetailRows', 'unused', $source, $filters, [], null
            ));
        }
    }

    public function test_summary_keeps_same_numbered_cashiers_separate_and_preserves_filter_ids(): void
    {
        $users = [['id' => 7, 'name' => 'POS cashier', 'amount' => 100.0, 'qty' => 1.0]];
        $locations = $groups = $brands = [];
        $moduleRows = [];
        foreach (['accessory', 'service'] as $source) {
            $moduleRows[] = [
                'module_prefix' => $source,
                'transaction_id' => 1,
                'cashier_id' => $this->invoke('moduleCashierFilterId', 7, $source),
                'cashier_name' => $source . ' cashier',
                'location_id' => 1,
                'line_total' => 25.0,
                'quantity' => 2,
            ];
        }

        $method = new ReflectionMethod(LocalCashierReportController::class, 'mergeModuleSummaryRows');
        $method->invokeArgs($this->controller(), [
            $moduleRows, ['qty_type' => 'invoice_count'], &$users, &$locations, &$groups, &$brands,
        ]);

        $this->assertCount(3, $users);
        $byId = array_column($users, null, 'id');
        $this->assertSame(100.0, $byId[7]['amount']);
        $this->assertSame('accessory cashier', $byId[-14]['name']);
        $this->assertSame('service cashier', $byId[-15]['name']);
        $this->assertSame(25.0, $byId[-14]['amount']);
        $this->assertSame(25.0, $byId[-15]['amount']);
    }

    public function test_module_sales_totals_match_visible_module_rows(): void
    {
        $totals = $this->invoke('moduleSaleTotals', [
            [
                'row_type' => 'sale',
                'transaction_id' => 1,
                'line_total' => 55.0,
                'paid' => 55.0,
                'due' => 0.0,
                'quantity' => 2.0,
                'payments' => ['cash' => 55.0],
            ],
            [
                'row_type' => 'sale',
                'transaction_id' => 2,
                'line_total' => 40.0,
                'paid' => 20.0,
                'due' => 20.0,
                'quantity' => 3.0,
                'payments' => ['card' => 20.0],
            ],
        ], ['cash', 'card']);

        $this->assertSame(95.0, $totals['total']);
        $this->assertSame(75.0, $totals['paid']);
        $this->assertSame(20.0, $totals['due']);
        $this->assertSame(55.0, $totals['payments']['cash']);
        $this->assertSame(20.0, $totals['payments']['card']);
    }
}

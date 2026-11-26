<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class LocalCashierLocationFilterTest extends TestCase
{
    private function invoke(string $method, ...$arguments)
    {
        $controller = (new ReflectionClass(LocalCashierReportController::class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($controller, ...$arguments);
    }

    public function test_same_database_id_does_not_select_other_module_locations(): void
    {
        $accessory = $this->invoke('moduleLocationFilterId', 7, 'accessory');
        $service = $this->invoke('moduleLocationFilterId', 7, 'service');

        $this->assertNotSame($accessory, $service);
        $this->assertSame([], $this->invoke('moduleLocationIds', [7], 'accessory'));
        $this->assertSame([], $this->invoke('moduleLocationIds', [7], 'service'));
        $this->assertSame([7], $this->invoke('moduleLocationIds', [$accessory], 'accessory'));
        $this->assertSame([], $this->invoke('moduleLocationIds', [$accessory], 'service'));
        $this->assertSame([7], $this->invoke('moduleLocationIds', [$service], 'service'));
        $this->assertSame([], $this->invoke('moduleLocationIds', [$service], 'accessory'));
    }

    public function test_multiple_selected_locations_are_decoded_only_for_their_source(): void
    {
        $selected = [7, -14, -15, -18, -23];
        $this->assertSame([7, 9], $this->invoke('moduleLocationIds', $selected, 'accessory'));
        $this->assertSame([7, 11], $this->invoke('moduleLocationIds', $selected, 'service'));
    }

    public function test_unselected_module_queries_return_no_rows_without_accessing_database(): void
    {
        $filters = ['location_ids' => [7]];
        foreach (['accessory', 'service'] as $source) {
            $this->assertSame(['rows' => [], 'total' => 0], $this->invoke(
                'getModuleSaleDetailRows', 'unused', $source, $filters, [], null
            ));
        }
    }

    public function test_explicit_unavailable_location_does_not_fall_back_to_all_locations(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['location_ids' => [999]];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [7], [7, -14, -15]);
        $this->assertSame([], $filters['location_ids']);
    }

    public function test_deselecting_all_locations_does_not_restore_default_locations(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['location_filter_applied' => '1'];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [7, 8], [7, 8, -14, -15]);
        $this->assertSame([], $filters['location_ids']);
    }

    public function test_one_selected_location_replaces_multiple_default_locations(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['location_filter_applied' => '1', 'location_ids' => ['8']];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [7, 8, -14], [7, 8, -14, -15]);
        $this->assertSame([8], $filters['location_ids']);
    }

    public function test_selected_module_location_is_preserved_after_search_without_selecting_main_location(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['location_filter_applied' => '1', 'location_ids' => ['-14']];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [7, 8, -14, -15], [7, 8, -14, -15]);
        $this->assertSame([-14], $filters['location_ids']);
        $this->assertSame([7], $this->invoke('moduleLocationIds', $filters['location_ids'], 'accessory'));
        $this->assertSame([], $this->invoke('moduleLocationIds', $filters['location_ids'], 'service'));
    }

    public function test_locations_no_longer_active_are_removed_without_restoring_previous_selection(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['location_filter_applied' => '1', 'location_ids' => ['7', '8']];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [9], [8, 9]);
        $this->assertSame([8], $filters['location_ids']);
    }

    public function test_new_selection_does_not_include_previous_locations_five_six_or_seven(): void
    {
        foreach ([[4], [1, 2, 3, 4]] as $selectedIds) {
            $request = new class($selectedIds) extends Request {
                public function __construct(private array $selectedIds)
                {
                    parent::__construct();
                }

                public function validate(array $rules): array
                {
                    return ['location_filter_applied' => '1', 'location_ids' => $this->selectedIds];
                }
            };
            $filters = $this->invoke('validatedFilters', $request, [5, 6, 7], [1, 2, 3, 4, 5, 6, 7]);
            $this->assertSame($selectedIds, $filters['location_ids']);
        }
    }

    public function test_branch_dashboard_style_mode_is_accepted(): void
    {
        $request = new class extends Request {
            public function validate(array $rules): array
            {
                return ['style_mode' => 'branch_dashboard'];
            }
        };
        $filters = $this->invoke('validatedFilters', $request, [1, -2], [1, -2]);
        $this->assertSame('branch_dashboard', $filters['style_mode']);
        $this->assertSame([1, -2], $filters['location_ids']);
    }

    public function test_branch_dashboards_group_main_accessory_service_and_expenses_by_prefix_without_combining(): void
    {
        foreach ([
            'កម្ពុជាក្រោម',
            'កម្ពុជាក្រោម-Accessory',
            "កម្ពុជាក្រោម\u{200B} - Service (Service)",
            'កម្ពុជាក្រោម-Expense (Accessory)',
        ] as $variant) {
            $identity = $this->invoke('extractBranchIdentity', $variant);
            $this->assertSame('កម្ពុជាក្រោម', $identity['name']);
        }

        $locationRows = [
            [
                'location_id' => 1,
                'location_name' => 'កម្ពុជាក្រោម',
                'total' => 500.0,
                'paid' => 450.0,
                'due' => 50.0,
                'payments' => ['cash' => 300.0, 'custom_pay_1' => 150.0],
                'customer_groups' => [
                    'normal' => ['name' => 'លក់', 'sort' => 1, 'qty_total' => 2, 'total' => 500.0, 'paid' => 450.0, 'due' => 50.0, 'payments' => ['cash' => 300.0, 'custom_pay_1' => 150.0]],
                ],
            ],
            [
                'location_id' => 2,
                'location_name' => 'ស្ទឹងមានជ័យ',
                'total' => 200.0,
                'paid' => 200.0,
                'due' => 0.0,
                'payments' => ['cash' => 200.0, 'custom_pay_1' => 0.0],
                'customer_groups' => [
                    'normal' => ['name' => 'លក់', 'sort' => 1, 'qty_total' => 1, 'total' => 200.0, 'paid' => 200.0, 'due' => 0.0, 'payments' => ['cash' => 200.0, 'custom_pay_1' => 0.0]],
                ],
            ],
        ];
        $accessoryRows = [[
            'location_id' => 5,
            'location_name' => 'កម្ពុជាក្រោម-Accessory',
            'transaction_id' => 91,
            'quantity' => 3,
            'line_total' => 120.0,
            'paid' => 120.0,
            'due' => 0.0,
            'payments' => ['cash' => 20.0, 'custom_pay_1' => 100.0],
        ]];
        $serviceRows = [[
            'location_id' => 6,
            'location_name' => 'កម្ពុជាក្រោម-Service',
            'transaction_id' => 92,
            'quantity' => 1,
            'line_total' => 80.0,
            'paid' => 80.0,
            'due' => 0.0,
            'payments' => ['cash' => 80.0, 'custom_pay_1' => 0.0],
        ]];

        $branches = $this->invoke(
            'buildBranchDashboards',
            $locationRows,
            $accessoryRows,
            $serviceRows,
            [1 => 70.0, 2 => 10.0],
            [1 => ['cash' => 50.0, 'custom_pay_1' => 20.0], 2 => ['cash' => 10.0]],
            [1 => 15.0],
            ['cash', 'custom_pay_1'],
            ['qty_type' => 'sold_quantity'],
            [1 => 'កម្ពុជាក្រោម', 2 => 'ស្ទឹងមានជ័យ']
        );

        $byKey = collect($branches)->keyBy('branch_name');
        $this->assertCount(2, $byKey);

        $kampong = $byKey['កម្ពុជាក្រោម'];
        $this->assertEquals(700.0, $kampong['total']); // 500 + 120 + 80
        $this->assertEquals(650.0, $kampong['paid']); // 450 + 120 + 80
        $this->assertEquals(70.0, $kampong['expenses']);
        $this->assertEquals(565.0, $kampong['actual_income']); // 650 - 70 - 15
        $this->assertEquals(350.0, $kampong['actual_income_payments']['cash']); // (300+20+80) - 50
        $this->assertEquals(230.0, $kampong['actual_income_payments']['custom_pay_1']); // (150+100) - 20
        $this->assertCount(3, $kampong['rows']); // Main POS + Accessory + Service inside កម្ពុជាក្រោម

        $smc = $byKey['ស្ទឹងមានជ័យ'];
        $this->assertEquals(200.0, $smc['total']);
        $this->assertEquals(10.0, $smc['expenses']);
        $this->assertEquals(190.0, $smc['actual_income']);
        $this->assertEquals(190.0, $smc['actual_income_payments']['cash']);
    }
}

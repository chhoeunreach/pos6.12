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
}

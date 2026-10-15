<?php

namespace App\Console\Commands;

use App\Business;
use App\Services\StockAuthorityReconciliationService;
use Illuminate\Console\Command;

class FixStockAuthorityMismatches extends Command
{
    protected $signature = 'stock:authority-fix-mismatches {business_id=1} {--reason= : Reason for authority fix}';

    protected $description = 'Fix stock allocation mismatches and regenerate clean data for Stock Inventory Authority';

    public function handle(StockAuthorityReconciliationService $service): int
    {
        $businessId = (int) $this->argument('business_id');
        if ($businessId <= 0 || ! Business::where('id', $businessId)->exists()) {
            $this->error('Select an existing business ID.');
            return self::FAILURE;
        }

        $this->info("Scanning and reconciling stock authority mismatches for Business #{$businessId}...");

        $reason = $this->option('reason') ?: 'CLI Stock Inventory Authority Full Mismatch Fix';
        $result = $service->reconcileAll($businessId, $reason, 1, '127.0.0.1');

        $this->info($result['message']);
        $this->table(
            ['Metric', 'Before Fix', 'After Fix'],
            [
                ['Sale Line Issues', $result['before']['sale_line_issues'], $result['after']['sale_line_issues']],
                ['Stock Issues', $result['before']['stock_issues'], $result['after']['stock_issues']],
                ['Transfer Issues', $result['before']['transfer_issues'], $result['after']['transfer_issues']],
            ]
        );

        $this->info("Over-allocated lines cleaned: " . $result['over_allocated_fixed']);
        $this->info("Missing selected lots repaired: " . $result['missing_selected_fixed']);
        $this->info("Allocations realigned to selected lots: " . $result['realigned_allocations_fixed']);
        $this->info("Purchase lines quantity_sold recalculated: " . $result['purchase_lines_recalculated']);
        $this->info("Variation location stock details recalculated: " . $result['variations_recalculated']);
        $this->info("Line #2328 status: " . ($result['line_2328_clean'] ? "CLEAN" : "HAS ISSUES"));

        return self::SUCCESS;
    }
}

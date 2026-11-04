<?php

namespace Modules\DailyFinancialManagement\Console;

use Illuminate\Console\Command;
use Modules\DailyFinancialManagement\Services\DailyClosingService;

class DailyFinancialClosingCommand extends Command
{
    protected $signature = 'financial:daily-closing {--date= : YYYY-MM-DD closing date} {--branch-id= : Branch Location ID} {--business-id=1 : Business ID}';
    protected $description = 'Generate and compute daily financial closing balances for a branch';

    public function handle(DailyClosingService $closingService): int
    {
        $date = $this->option('date') ?: date('Y-m-d');
        $branchId = $this->option('branch-id');
        $businessId = (int) $this->option('business-id');

        $this->info("Running Daily Financial Closing calculation for Date: {$date}, Branch: " . ($branchId ?: 'All') . "...");

        $result = $closingService->generateClosingSummary($businessId, $date, $branchId);

        if ($result['success']) {
            $this->info("Daily closing generated successfully!");
            return 0;
        }

        $this->error("Failed to generate daily closing: " . ($result['error'] ?? 'Unknown error'));
        return 1;
    }
}


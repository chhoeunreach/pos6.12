<?php

namespace Modules\DailyFinancialManagement\Console;

use Illuminate\Console\Command;
use Modules\DailyFinancialManagement\Services\PosFinancialSyncService;

class SyncPosFinancialDataCommand extends Command
{
    protected $signature = 'financial:sync-pos {--start-date= : YYYY-MM-DD start date} {--end-date= : YYYY-MM-DD end date} {--business-id=1 : Business ID} {--force : Force re-sync}';
    protected $description = 'Synchronize POS sales, returns, payments and loan installment collections into Daily Financial Management';

    public function handle(PosFinancialSyncService $syncService): int
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $businessId = (int) $this->option('business-id');
        $force = (bool) $this->option('force');

        $this->info("Starting POS Financial Synchronization for Business ID: {$businessId}...");

        $result = $syncService->syncPosAndLoans($businessId, $startDate, $endDate, $force);

        if ($result['success']) {
            $this->info("Synchronization completed successfully!");
            $this->table(['Metric', 'Count'], [
                ['Sales Synced', $result['sales_count'] ?? 0],
                ['Returns Synced', $result['returns_count'] ?? 0],
                ['Payments Synced', $result['payments_count'] ?? 0],
                ['Loan Payments Synced', $result['loan_payments_count'] ?? 0],
                ['Skipped (Existing)', $result['skipped_count'] ?? 0],
            ]);
            return 0;
        }

        $this->error("Synchronization failed: " . ($result['error'] ?? 'Unknown error'));
        return 1;
    }
}


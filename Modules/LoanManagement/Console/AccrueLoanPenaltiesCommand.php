<?php

namespace Modules\LoanManagement\Console;

use Illuminate\Console\Command;
use Modules\LoanManagement\Services\LoanPenaltyAccrualService;

class AccrueLoanPenaltiesCommand extends Command
{
    protected $signature = 'loan-management:accrue-penalties {--dry-run : Simulate accrual without updating the database}';

    protected $description = 'Accrue daily overdue penalties and update Days Past Due (DPD) buckets for all active loans.';

    public function handle(LoanPenaltyAccrualService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Simulating daily penalty accrual...' : 'Running daily penalty accrual and DPD calculation...');

        $result = $service->accrueDaily($dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Loans Checked', $result['loans_checked']],
                ['Loans Updated', $result['loans_updated']],
                ['Overdue Schedules Found', $result['schedules_overdue']],
                ['New Penalties Accrued Count', $result['penalties_added_count']],
                ['Total Penalties Accrued ($)', number_format($result['total_penalties_accrued'], 2)],
                ['Mode', $dryRun ? 'DRY-RUN (Simulated)' : 'APPLIED TO DATABASE'],
            ]
        );

        $this->info('Daily penalty accrual completed successfully.');

        return self::SUCCESS;
    }
}

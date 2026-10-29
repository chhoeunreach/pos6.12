<?php

namespace Modules\LoanManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UpgradeLoanFeaturesCommand extends Command
{
    protected $signature = 'loan-management:upgrade-features {--pretend : Show SQL without changing data}';

    protected $description = 'Install additive quotation, payment-method and penalty schema updates on mysql_loan.';

    public function handle(): int
    {
        $files = [
            '2026_10_02_000001_create_loan_quotations_tables.php',
            '2026_10_04_000001_create_first_setup_reference_tables.php',
            '2026_10_07_000001_add_daily_penalty_rate_to_loans.php',
            '2026_10_29_000001_ensure_loan_ptp_fields.php',
        ];
        $paths = array_map(fn ($file) => module_path('LoanManagement', 'Database/migrations/'.$file), $files);
        $status = Artisan::call('migrate', [
            '--database' => 'mysql_loan', '--path' => $paths, '--realpath' => true,
            '--force' => true, '--pretend' => (bool) $this->option('pretend'),
        ]);
        $this->output->write(Artisan::output());

        return $status;
    }
}

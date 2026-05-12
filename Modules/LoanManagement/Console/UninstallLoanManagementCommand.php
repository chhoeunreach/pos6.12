<?php

namespace Modules\LoanManagement\Console;

use App\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class UninstallLoanManagementCommand extends Command
{
    protected $signature = 'loan-management:uninstall {--force} {--drop-tables}';

    protected $description = 'Uninstall LoanManagement module safely (mysql_loan only)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Use --force to uninstall.');

            return self::FAILURE;
        }

        try {
            if ($this->option('drop-tables')) {
                $this->dropLoanTables();
            }

            foreach ((array) config('loanmanagement.permissions', []) as $perm) {
                Permission::where('name', $perm)->where('guard_name', 'web')->delete();
            }
            System::removeProperty('loanmanagement_version');

            Artisan::call('optimize:clear');
            $this->info('LoanManagement uninstalled. Main Ultimate POS database was not modified.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Uninstall failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function dropLoanTables(): void
    {
        DB::connection('mysql_loan')->statement('SET FOREIGN_KEY_CHECKS=0');
        $dbName = DB::connection('mysql_loan')->getDatabaseName();
        $rows = DB::connection('mysql_loan')->select('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE "loan\_%"', [$dbName]);

        foreach ($rows as $row) {
            $table = $row->TABLE_NAME ?? null;
            if (! empty($table)) {
                DB::connection('mysql_loan')->statement('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        }

        DB::connection('mysql_loan')->statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('Dropped loan_* tables from mysql_loan.');
    }
}

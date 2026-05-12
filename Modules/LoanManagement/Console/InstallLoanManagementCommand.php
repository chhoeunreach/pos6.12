<?php

namespace Modules\LoanManagement\Console;

use App\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class InstallLoanManagementCommand extends Command
{
    protected $signature = 'loan-management:install';

    protected $description = 'Install LoanManagement module (mysql_loan only)';

    public function handle(): int
    {
        try {
            $this->info('LoanManagement install started...');
            $this->ensureLoanDatabaseExists();
            $this->checkLoanConnection();

            $this->info('Running migrations on mysql_loan...');
            Artisan::call('migrate', [
                '--database' => 'mysql_loan',
                '--path' => 'Modules/LoanManagement/Database/Migrations',
                '--realpath' => false,
                '--force' => true,
            ]);
            $this->line(Artisan::output());

            $this->info('Running seeders...');
            Artisan::call('db:seed', [
                '--database' => 'mysql_loan',
                '--class' => 'Modules\\LoanManagement\\Database\\Seeders\\LoanManagementDatabaseSeeder',
                '--force' => true,
            ]);
            $this->line(Artisan::output());

            $this->info('Publishing LoanManagement config...');
            Artisan::call('vendor:publish', [
                '--provider' => 'Modules\\LoanManagement\\Providers\\LoanManagementServiceProvider',
                '--tag' => 'config',
                '--force' => true,
            ]);
            $this->line(Artisan::output());

            $this->registerPermissions();
            $this->enableModuleInStatuses();
            System::setProperty('loanmanagement_version', (string) config('loanmanagement.version', '1.0.0'));

            $this->info('Clearing application caches...');
            foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
                Artisan::call($cmd);
            }

            $this->info('Sidebar menu integration is registered via LoanManagement DataController.');
            $this->info('LoanManagement installed successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Install failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureLoanDatabaseExists(): void
    {
        $cfg = Config::get('database.connections.mysql_loan');
        if (empty($cfg)) {
            throw new \RuntimeException('mysql_loan connection is not configured.');
        }

        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int) ($cfg['port'] ?? 3306);
        $database = $cfg['database'] ?? 'loan_management';
        $username = $cfg['username'] ?? 'root';
        $password = $cfg['password'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';

        $dsn = "mysql:host={$host};port={$port}";
        $pdo = new \PDO($dsn, $username, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $safeDb = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    private function checkLoanConnection(): void
    {
        DB::connection('mysql_loan')->getPdo();
        DB::connection('mysql_loan')->select('SELECT 1');
    }

    private function registerPermissions(): void
    {
        foreach ((array) config('loanmanagement.permissions', []) as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->info('Permissions registered.');
    }

    private function enableModuleInStatuses(): void
    {
        $path = base_path('modules_statuses.json');
        if (! file_exists($path)) {
            return;
        }

        $raw = file_get_contents($path);
        $json = json_decode((string) $raw, true);
        if (! is_array($json)) {
            return;
        }

        $json['LoanManagement'] = true;
        file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

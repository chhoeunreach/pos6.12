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
            $this->showExpectedDatabaseConfig();
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
            $this->showCustomerAuthDriver();
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
        $required = [
            'loan_management.view',
            'loan_management.create',
            'loan_management.edit',
            'loan_management.delete',
            'loan_management.approve',
            'loan_management.payment',
            'loan_management.report',
            'loan_management.customers.view',
            'loan_management.customers.create',
            'loan_management.chat.view',
            'loan_management.chat.reply',
            'loan_management.customer_gps.manage',
        ];
        $configured = (array) config('loanmanagement.permissions', []);
        $permissions = array_values(array_unique(array_merge($required, $configured)));

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->info('Permissions registered.');
    }

    private function showExpectedDatabaseConfig(): void
    {
        $this->line('Expected DB connection: mysql_loan');
        $this->line('Config path: config/database.php > connections.mysql_loan');
        $this->line('Target database: ' . (string) config('database.connections.mysql_loan.database', 'loan_management'));
    }

    private function showCustomerAuthDriver(): void
    {
        $configured = (string) config('loanmanagement.customer_api_driver', 'auto');
        $resolved = $configured === 'auto'
            ? (class_exists(\Laravel\Sanctum\Sanctum::class) ? 'sanctum' : 'passport')
            : $configured;

        $this->info(sprintf(
            'Customer guard [%s] uses [%s] driver with provider [%s].',
            (string) config('loanmanagement.customer_api_guard', 'customer_loan_api'),
            $resolved,
            (string) config('loanmanagement.customer_api_provider', 'loan_customers')
        ));
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

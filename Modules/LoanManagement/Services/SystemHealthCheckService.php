<?php

namespace Modules\LoanManagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthCheckService
{
    /**
     * Run all server and database checks and return a structured report.
     */
    public static function check(): array
    {
        $phpChecks = self::checkPhpAndExtensions();
        $storageChecks = self::checkStoragePermissions();
        $envChecks = self::checkEnvironment();
        $dbChecks = self::checkDatabaseConnections();
        $schemaChecks = self::checkDatabaseSchema();
        $seedChecks = self::checkSeedData();
        $serverInfo = self::getServerInfo();

        $allChecks = array_merge(
            $phpChecks['items'],
            $storageChecks['items'],
            $envChecks['items'],
            $dbChecks['items'],
            $schemaChecks['items'],
            $seedChecks['items']
        );

        $errorCount = 0;
        $warningCount = 0;
        $passedCount = 0;
        $alerts = [];

        foreach ($allChecks as $check) {
            if ($check['status'] === 'fail') {
                $errorCount++;
                $alerts[] = [
                    'severity' => 'danger',
                    'category' => $check['category'] ?? 'System',
                    'title_en' => $check['title_en'] ?? $check['name'],
                    'title_km' => $check['title_km'] ?? $check['name'],
                    'message_en' => $check['message_en'] ?? '',
                    'message_km' => $check['message_km'] ?? '',
                    'remedy' => $check['remedy'] ?? null,
                ];
            } elseif ($check['status'] === 'warning') {
                $warningCount++;
                $alerts[] = [
                    'severity' => 'warning',
                    'category' => $check['category'] ?? 'System',
                    'title_en' => $check['title_en'] ?? $check['name'],
                    'title_km' => $check['title_km'] ?? $check['name'],
                    'message_en' => $check['message_en'] ?? '',
                    'message_km' => $check['message_km'] ?? '',
                    'remedy' => $check['remedy'] ?? null,
                ];
            } else {
                $passedCount++;
            }
        }

        $totalChecks = count($allChecks);
        $score = $totalChecks > 0 ? (int) round(($passedCount / $totalChecks) * 100) : 100;
        $overallStatus = $errorCount > 0 ? 'critical' : ($warningCount > 0 ? 'warning' : 'healthy');

        return [
            'status' => $overallStatus,
            'score' => $score,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'passed_count' => $passedCount,
            'total_checks' => $totalChecks,
            'has_issues' => $errorCount > 0 || $warningCount > 0,
            'has_critical_errors' => $errorCount > 0,
            'alerts' => $alerts,
            'categories' => [
                'php' => $phpChecks,
                'storage' => $storageChecks,
                'environment' => $envChecks,
                'database' => $dbChecks,
                'schema' => $schemaChecks,
                'seeds' => $seedChecks,
            ],
            'server_info' => $serverInfo,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Check PHP version and required extensions.
     */
    public static function checkPhpAndExtensions(): array
    {
        $items = [];
        $hasError = false;

        // PHP Version >= 8.1
        $currentPhp = PHP_VERSION;
        $phpPass = version_compare($currentPhp, '8.1.0', '>=');
        if (! $phpPass) {
            $hasError = true;
        }

        $items[] = [
            'category' => 'PHP',
            'name' => 'PHP Version',
            'required' => '>= 8.1.0',
            'current' => $currentPhp,
            'status' => $phpPass ? 'pass' : 'fail',
            'title_en' => 'PHP Version Incompatible',
            'title_km' => 'កំណែ PHP មិនត្រូវតាមតម្រូវការ',
            'message_en' => "Current PHP version ({$currentPhp}) is below the required version 8.1.0.",
            'message_km' => "កំណែ PHP បច្ចុប្បន្ន ({$currentPhp}) ទាបជាងតម្រូវការអប្បបរមា 8.1.0។",
            'remedy' => 'Upgrade your server PHP runtime to 8.1 or higher (e.g. 8.2 / 8.3).',
        ];

        // Required Extensions
        $requiredExtensions = [
            'pdo' => ['name' => 'PDO', 'desc' => 'Database abstraction layer'],
            'pdo_mysql' => ['name' => 'PDO MySQL Driver', 'desc' => 'MySQL database driver for PDO'],
            'openssl' => ['name' => 'OpenSSL', 'desc' => 'Data encryption and security tokens'],
            'mbstring' => ['name' => 'Mbstring', 'desc' => 'Multi-byte string handling and UTF-8 / Khmer support'],
            'tokenizer' => ['name' => 'Tokenizer', 'desc' => 'PHP Code parsing and Blade compilation'],
            'xml' => ['name' => 'XML / DOM', 'desc' => 'XML and Excel export parsing'],
            'ctype' => ['name' => 'Ctype', 'desc' => 'Character type checking'],
            'json' => ['name' => 'JSON', 'desc' => 'JSON data encoding and decoding'],
            'bcmath' => ['name' => 'BCMath', 'desc' => 'Arbitrary precision mathematics for loan interest calculations'],
            'curl' => ['name' => 'cURL', 'desc' => 'External HTTP requests and Telegram API integration'],
            'fileinfo' => ['name' => 'FileInfo', 'desc' => 'MIME type detection for receipts, chat files, and avatar uploads'],
            'gd' => ['name' => 'GD or Imagick', 'desc' => 'Image processing for ID card cropping and loan receipts'],
            'zip' => ['name' => 'ZipArchive', 'desc' => 'Excel import/export and backup archive processing'],
        ];

        foreach ($requiredExtensions as $ext => $info) {
            $loaded = extension_loaded($ext);
            if ($ext === 'gd' && ! $loaded && extension_loaded('imagick')) {
                $loaded = true;
            }

            if (! $loaded) {
                $hasError = true;
            }

            $items[] = [
                'category' => 'PHP Extensions',
                'name' => $info['name'],
                'extension' => $ext,
                'required' => 'Enabled',
                'current' => $loaded ? 'Enabled' : 'Missing / Disabled',
                'status' => $loaded ? 'pass' : 'fail',
                'title_en' => "Missing PHP Extension: {$info['name']}",
                'title_km' => "ខ្វះ PHP Extension: {$info['name']}",
                'message_en' => "Required PHP extension '{$ext}' ({$info['desc']}) is not enabled on this server.",
                'message_km' => "PHP extension '{$ext}' ({$info['desc']}) មិនទាន់បានបើកដំណើរការលើ Server នេះទេ។",
                'remedy' => "Enable 'extension={$ext}' in php.ini and restart your web server (Apache / Nginx / PHP-FPM).",
            ];
        }

        return [
            'name' => 'PHP & Extensions',
            'status' => $hasError ? 'fail' : 'pass',
            'items' => $items,
        ];
    }

    /**
     * Check storage directory writable permissions.
     */
    public static function checkStoragePermissions(): array
    {
        $items = [];
        $hasError = false;

        $paths = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/app/public' => storage_path('app/public'),
            'storage/framework' => storage_path('framework'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        foreach ($paths as $label => $path) {
            if (! File::exists($path)) {
                try {
                    File::makeDirectory($path, 0775, true, true);
                } catch (Throwable $e) {
                    // Ignore create failure and let writable check flag it
                }
            }

            $writable = is_dir($path) && is_writable($path);
            if (! $writable) {
                $hasError = true;
            }

            $items[] = [
                'category' => 'Permissions',
                'name' => $label,
                'path' => $path,
                'required' => 'Writable (0775 / 0777)',
                'current' => $writable ? 'Writable' : 'Read-Only / Unwritable',
                'status' => $writable ? 'pass' : 'fail',
                'title_en' => "Directory Not Writable: {$label}",
                'title_km' => "ថតមិនមានសិទ្ធិកត់ត្រាទិន្នន័យ (Not Writable): {$label}",
                'message_en' => "The directory '{$path}' must be writable by the web server.",
                'message_km' => "ថត '{$path}' ត្រូវតែមានសិទ្ធិ Writable សម្រាប់ Web Server។",
                'remedy' => "Run terminal command: chmod -R 775 " . escapeshellarg($path) . " && chown -R www-data:www-data " . escapeshellarg($path),
            ];
        }

        return [
            'name' => 'Directory Permissions',
            'status' => $hasError ? 'fail' : 'pass',
            'items' => $items,
        ];
    }

    /**
     * Check environment configuration.
     */
    public static function checkEnvironment(): array
    {
        $items = [];
        $hasError = false;
        $hasWarning = false;

        // APP_KEY check
        $appKey = (string) config('app.key');
        $hasKey = ! empty($appKey) && (str_starts_with($appKey, 'base64:') || strlen($appKey) >= 16);
        if (! $hasKey) {
            $hasError = true;
        }

        $items[] = [
            'category' => 'Environment',
            'name' => 'Application Encryption Key (APP_KEY)',
            'required' => 'Configured & Secure',
            'current' => $hasKey ? 'Configured (' . substr($appKey, 0, 10) . '...)' : 'Missing / Invalid',
            'status' => $hasKey ? 'pass' : 'fail',
            'title_en' => 'Application Encryption Key Missing',
            'title_km' => 'ខ្វះ Application Encryption Key (APP_KEY)',
            'message_en' => 'APP_KEY is not configured in .env file. Encrypted sessions and passwords cannot be processed securely.',
            'message_km' => 'មិនទាន់មាន APP_KEY នៅក្នុង .env។ ប្រព័ន្ធមិនអាចដំណើរការការការពារទិន្នន័យបានត្រឹមត្រូវទេ។',
            'remedy' => 'Run terminal command: php artisan key:generate',
        ];

        // Environment mode check
        $env = (string) config('app.env', 'production');
        $debug = (bool) config('app.debug', false);

        if ($env === 'production' && $debug) {
            $hasWarning = true;
            $items[] = [
                'category' => 'Environment',
                'name' => 'Production Debug Mode',
                'required' => 'APP_DEBUG=false in production',
                'current' => 'APP_DEBUG=true',
                'status' => 'warning',
                'title_en' => 'Debug Mode Active in Production',
                'title_km' => 'Debug Mode កំពុងបើកក្នុង Production',
                'message_en' => 'APP_DEBUG is set to true in production environment. Sensitive stack traces might be exposed to visitors.',
                'message_km' => 'APP_DEBUG=true អាចបង្ហាញព័ត៌មានកូដលម្អិតទៅកាន់អ្នកប្រើប្រាស់ពេលមានបញ្ហា។',
                'remedy' => 'Set APP_DEBUG=false in .env file for security.',
            ];
        }

        return [
            'name' => 'Environment Configuration',
            'status' => $hasError ? 'fail' : ($hasWarning ? 'warning' : 'pass'),
            'items' => $items,
        ];
    }

    /**
     * Check database connectivity for default and loan connections.
     */
    public static function checkDatabaseConnections(): array
    {
        $items = [];
        $hasError = false;

        $connections = [
            'default' => [
                'label' => 'Primary Database (' . config('database.default') . ')',
                'name' => config('database.default'),
            ],
            'mysql_loan' => [
                'label' => 'Loan Management Connection (mysql_loan)',
                'name' => 'mysql_loan',
            ],
        ];

        foreach ($connections as $key => $connInfo) {
            $connName = $connInfo['name'];
            $connected = false;
            $dbName = '';
            $dbHost = '';
            $errorMsg = '';

            try {
                $config = config("database.connections.{$connName}", []);
                $dbName = $config['database'] ?? 'Unknown';
                $dbHost = ($config['host'] ?? '127.0.0.1') . ':' . ($config['port'] ?? '3306');

                DB::connection($connName)->getPdo();
                $connected = true;
            } catch (Throwable $e) {
                $connected = false;
                $errorMsg = $e->getMessage();
                $hasError = true;
            }

            $items[] = [
                'category' => 'Database',
                'name' => $connInfo['label'],
                'database' => $dbName,
                'host' => $dbHost,
                'required' => 'Reachable & Connected',
                'current' => $connected ? "Connected ({$dbName} @ {$dbHost})" : "Connection Failed",
                'status' => $connected ? 'pass' : 'fail',
                'title_en' => "Database Connection Failed: {$connInfo['label']}",
                'title_km' => "ការភ្ជាប់មូលដ្ឋានទិន្នន័យបានបរាជ័យ: {$connInfo['label']}",
                'message_en' => "Could not connect to database '{$dbName}' on host '{$dbHost}'. Error: {$errorMsg}",
                'message_km' => "មិនអាចភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ '{$dbName}' នៅ {$dbHost} បានទេ។ កំហុស: {$errorMsg}",
                'remedy' => 'Verify DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD or LOAN_DB_* in your .env file.',
            ];
        }

        return [
            'name' => 'Database Connections',
            'status' => $hasError ? 'fail' : 'pass',
            'items' => $items,
        ];
    }

    /**
     * Check if all core tables exist in the database.
     */
    public static function checkDatabaseSchema(): array
    {
        $items = [];
        $hasError = false;

        $targetConnection = 'mysql_loan';
        $coreTables = [
            'users' => ['desc' => 'User accounts and administrators', 'conn' => null],
            'loan_business_locations' => ['desc' => 'Business branches and Telegram channels', 'conn' => $targetConnection],
            'loan_customers' => ['desc' => 'Borrower accounts and profiles', 'conn' => $targetConnection],
            'loans' => ['desc' => 'Core loan agreements and applications', 'conn' => $targetConnection],
            'loan_payment_schedules' => ['desc' => 'Monthly installment repayment schedules', 'conn' => $targetConnection],
            'loan_payments' => ['desc' => 'Recorded payments and collections', 'conn' => $targetConnection],
            'loan_payment_details' => ['desc' => 'Payment line item distributions', 'conn' => $targetConnection],
            'loan_currencies' => ['desc' => 'Multi-currency configuration (USD, KHR)', 'conn' => $targetConnection],
            'loan_payment_methods' => ['desc' => 'Supported payment methods (Cash, ABA, ACLEDA, etc.)', 'conn' => $targetConnection],
            'loan_chat_threads' => ['desc' => 'Customer live chat threads', 'conn' => $targetConnection],
            'loan_chat_messages' => ['desc' => 'Chat messages and attachments', 'conn' => $targetConnection],
            'loan_telegram_settings' => ['desc' => 'Telegram Bot and webhook configuration', 'conn' => $targetConnection],
            'loan_activity_logs' => ['desc' => 'Audit trails and audit logs', 'conn' => $targetConnection],
        ];

        $missingTables = [];

        foreach ($coreTables as $table => $info) {
            $conn = $info['conn'];
            $exists = false;

            try {
                if ($conn) {
                    $exists = Schema::connection($conn)->hasTable($table);
                } else {
                    $exists = Schema::hasTable($table);
                }
            } catch (Throwable $e) {
                $exists = false;
            }

            if (! $exists) {
                $hasError = true;
                $missingTables[] = $table;
            }

            $items[] = [
                'category' => 'Database Tables',
                'name' => "Table '{$table}'",
                'table' => $table,
                'desc' => $info['desc'],
                'required' => 'Table Exists',
                'current' => $exists ? 'Exists' : 'Missing',
                'status' => $exists ? 'pass' : 'fail',
                'title_en' => "Missing Core Table: {$table}",
                'title_km' => "ខ្វះតារាងទិន្នន័យសំខាន់: {$table}",
                'message_en' => "Table '{$table}' ({$info['desc']}) does not exist in the database. Run migrations.",
                'message_km' => "តារាង '{$table}' ({$info['desc']}) មិនទាន់មានក្នុង Database ទេ។ សូមដំណើរការ migration។",
                'remedy' => 'Run terminal command: php artisan migrate --force',
            ];
        }

        return [
            'name' => 'Database Schema',
            'status' => $hasError ? 'fail' : 'pass',
            'missing_tables' => $missingTables,
            'items' => $items,
        ];
    }

    /**
     * Check if initial reference seed data is loaded.
     */
    public static function checkSeedData(): array
    {
        $items = [];
        $hasWarning = false;

        $checks = [
            'locations' => [
                'name' => 'Business Locations',
                'table' => 'loan_business_locations',
                'desc' => 'At least 1 active branch location',
                'remedy' => 'php artisan db:seed --class=Database\\Seeders\\LoanManagementReferenceSeeder --force',
            ],
            'currencies' => [
                'name' => 'Currencies (USD / KHR)',
                'table' => 'loan_currencies',
                'desc' => 'Supported currencies and exchange rates',
                'remedy' => 'php artisan db:seed --class=Database\\Seeders\\LoanManagementReferenceSeeder --force',
            ],
            'payment_methods' => [
                'name' => 'Payment Methods',
                'table' => 'loan_payment_methods',
                'desc' => 'Standard payment channels (Cash, ABA, ACLEDA, Wing)',
                'remedy' => 'php artisan db:seed --class=Database\\Seeders\\LoanManagementReferenceSeeder --force',
            ],
            'users' => [
                'name' => 'Admin User Accounts',
                'table' => 'users',
                'conn' => null,
                'desc' => 'At least 1 active administrator account',
                'remedy' => 'php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force',
            ],
        ];

        foreach ($checks as $key => $info) {
            $table = $info['table'];
            $conn = $info['conn'] ?? 'mysql_loan';
            $count = 0;
            $tableExists = false;

            try {
                if ($conn) {
                    $tableExists = Schema::connection($conn)->hasTable($table);
                    if ($tableExists) {
                        $count = (int) DB::connection($conn)->table($table)->count();
                    }
                } else {
                    $tableExists = Schema::hasTable($table);
                    if ($tableExists) {
                        $count = (int) DB::table($table)->count();
                    }
                }
            } catch (Throwable $e) {
                $count = 0;
            }

            $hasData = $count > 0;
            if (! $hasData && $tableExists) {
                $hasWarning = true;
            }

            $items[] = [
                'category' => 'Reference Data',
                'name' => $info['name'],
                'table' => $table,
                'required' => '> 0 records',
                'current' => $tableExists ? "{$count} records" : 'Table Missing',
                'status' => $hasData ? 'pass' : ($tableExists ? 'warning' : 'fail'),
                'title_en' => "Empty Reference Data: {$info['name']}",
                'title_km' => "ទិន្នន័យគោលទទេ (Empty Data): {$info['name']}",
                'message_en' => "Table '{$table}' has 0 records. Essential reference data ({$info['desc']}) is missing.",
                'message_km' => "តារាង '{$table}' មិនទាន់មានទិន្នន័យ (0 កំណត់ត្រា)។ សូមដំណើរការ seed ទិន្នន័យគោល។",
                'remedy' => "Run terminal command: {$info['remedy']}",
            ];
        }

        return [
            'name' => 'Seed & Reference Data',
            'status' => $hasWarning ? 'warning' : 'pass',
            'items' => $items,
        ];
    }

    /**
     * Gather live server runtime specifications.
     */
    public static function getServerInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS . ' (' . php_uname('s') . ' ' . php_uname('r') . ')',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / Standalone',
            'memory_limit' => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time' => (ini_get('max_execution_time') ?: '0') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'N/A',
            'post_max_size' => ini_get('post_max_size') ?: 'N/A',
            'timezone' => date_default_timezone_get(),
            'current_time' => now()->toDateTimeString(),
            'laravel_version' => app()->version(),
        ];
    }
}

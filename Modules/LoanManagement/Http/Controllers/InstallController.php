<?php

namespace Modules\LoanManagement\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'loanmanagement';
        $this->appVersion = (string) config('loanmanagement.version', '1.0.0');
        $this->module_display_name = 'LoanManagement';
    }

    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name . '_version');
        if (! empty($is_installed)) {
            return redirect()
                ->action([ModulesIndexController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module is already installed.']);
        }

        $action_url = action([self::class, 'install']);
        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->module_display_name;

        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));
    }

    public function install()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $is_installed = System::getProperty($this->module_name . '_version');
            if (! empty($is_installed)) {
                DB::rollBack();
                return redirect()
                    ->action([ModulesIndexController::class, 'index'])
                    ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module is already installed.']);
            }

            $this->runInstallSteps();

            System::addProperty($this->module_name . '_version', $this->appVersion);
            DB::commit();

            $output = ['success' => 1, 'msg' => $this->module_display_name . ' module installed successfully'];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([ModulesIndexController::class, 'index'])
            ->with('status', $output);
    }

    public function uninstall()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $this->runUninstallSteps();
            System::removeProperty($this->module_name . '_version');
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function update()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $installedVersion = System::getProperty($this->module_name . '_version');
            if (empty($installedVersion) || version_compare($this->appVersion, $installedVersion, '>')) {
                $this->runInstallSteps();
                System::setProperty($this->module_name . '_version', $this->appVersion);
                $output = ['success' => 1, 'msg' => $this->module_display_name . ' module updated successfully to version ' . $this->appVersion];
            } else {
                abort(404);
            }
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function runInstallSteps(): void
    {
        $this->ensureLoanDatabaseExists();
        $this->checkLoanConnection();

        Artisan::call('migrate', [
            '--database' => 'mysql_loan',
            '--path' => 'Modules/LoanManagement/Database/Migrations',
            '--realpath' => false,
            '--force' => true,
        ]);

        Artisan::call('db:seed', [
            '--class' => 'Modules\\LoanManagement\\Database\\Seeders\\LoanManagementDatabaseSeeder',
            '--force' => true,
        ]);

        foreach ((array) config('loanmanagement.permissions', []) as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->enableModuleInStatuses();

        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
        }
    }

    private function runUninstallSteps(): void
    {
        foreach ((array) config('loanmanagement.permissions', []) as $perm) {
            Permission::where('name', $perm)->where('guard_name', 'web')->delete();
        }

        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
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

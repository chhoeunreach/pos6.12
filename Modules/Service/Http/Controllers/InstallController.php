<?php

namespace Modules\Service\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class InstallController extends Controller
{
    private string $moduleName = 'service';

    private string $moduleDisplayName = 'Service';

    private string $permission = 'service.access';

    private string $appVersion;

    public function __construct()
    {
        $this->appVersion = config('service.module_version', '1.0.0');
    }

    public function index()
    {
        $this->authorizeModuleManagement();

        if ($this->isInstalled()) {
            return redirect()
                ->action([ModulesIndexController::class, 'index'])
                ->with('status', [
                    'success' => 1,
                    'msg' => $this->moduleDisplayName . ' module is already installed.',
                ]);
        }

        $action_url = action([self::class, 'install']);
        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->moduleDisplayName;

        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));
    }

    public function install()
    {
        $this->authorizeModuleManagement();

        if ($this->isInstalled()) {
            return redirect()
                ->action([ModulesIndexController::class, 'index'])
                ->with('status', [
                    'success' => 1,
                    'msg' => $this->moduleDisplayName . ' module is already installed.',
                ]);
        }

        try {
            DB::beginTransaction();

            $this->runInstallSteps();
            System::addProperty($this->moduleName . '_version', $this->appVersion);

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => $this->moduleDisplayName . ' module installed successfully',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()
            ->action([ModulesIndexController::class, 'index'])
            ->with('status', $output);
    }

    public function uninstall(Request $request)
    {
        $this->authorizeModuleManagement();

        try {
            Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            System::removeProperty($this->moduleName . '_version');
            $this->disableModuleInStatuses();
            $this->removePublishedAssets();
            $this->removeModuleBootstrapCache();

            if ($request->boolean('remove_data')) {
                $this->dropServiceDatabase();
            }

            $this->clearCaches();

            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Throwable $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function update()
    {
        $this->authorizeModuleManagement();

        try {
            if (! $this->isInstalled()) {
                abort(404);
            }

            $this->runInstallSteps();
            System::setProperty($this->moduleName . '_version', $this->appVersion);

            $output = [
                'success' => 1,
                'msg' => $this->moduleDisplayName . ' module updated successfully to version ' . $this->appVersion,
            ];
        } catch (\Throwable $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function runInstallSteps(): void
    {
        $this->ensureServiceDatabaseExists();
        $this->checkServiceConnection();

        Artisan::call('migrate', [
            '--database' => config('service.database_connection', 'service'),
            '--path' => 'Modules/Service/Database/Migrations',
            '--realpath' => false,
            '--force' => true,
        ]);

        $this->seedServiceDatabase();
        $this->ensureMainPermissionExists();

        Artisan::call('module:publish', [
            'module' => 'Service',
        ]);

        $this->enableModuleInStatuses();
        $this->clearCaches();
    }

    private function seedServiceDatabase(): void
    {
        $connection = config('service.database_connection', 'service');
        $originalDefault = config('database.default');

        $authUser = auth()->user();

        config(['database.default' => $connection]);
        DB::setDefaultConnection($connection);

        try {
            $db = DB::connection($connection);

            if ($db->table('barcodes')->count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'Modules\\Service\\Database\\Seeders\\BarcodesTableSeeder',
                    '--force' => true,
                ]);
            }

            if ($db->table('permissions')->count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'Modules\\Service\\Database\\Seeders\\PermissionsTableSeeder',
                    '--force' => true,
                ]);
            }

            if ($db->table('currencies')->count() === 0) {
                Artisan::call('db:seed', [
                    '--class' => 'Modules\\Service\\Database\\Seeders\\CurrenciesTableSeeder',
                    '--force' => true,
                ]);
            }

            Artisan::call('db:seed', [
                '--class' => 'Modules\\Service\\Database\\Seeders\\ServiceBootstrapSeeder',
                '--force' => true,
            ]);

            if ($authUser && $db->table('users')->where('id', 1)->exists()) {
                $db->table('users')->where('id', 1)->update([
                    'username' => $authUser->username,
                    'surname' => $authUser->surname,
                    'first_name' => $authUser->first_name,
                    'last_name' => $authUser->last_name,
                    'email' => $authUser->email,
                    'password' => $authUser->password,
                    'language' => $authUser->language,
                ]);
            }
        } finally {
            config(['database.default' => $originalDefault]);
            DB::setDefaultConnection($originalDefault);
        }
    }

    private function ensureMainPermissionExists(): void
    {
        Permission::firstOrCreate([
            'name' => $this->permission,
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureServiceDatabaseExists(): void
    {
        $connection = config('service.database_connection', 'service');
        $cfg = Config::get('database.connections.' . $connection);

        if (empty($cfg)) {
            throw new \RuntimeException($connection . ' connection is not configured.');
        }

        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int) ($cfg['port'] ?? 3306);
        $database = $cfg['database'] ?? null;
        $username = $cfg['username'] ?? 'root';
        $password = $cfg['password'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';

        if (empty($database)) {
            throw new \RuntimeException($connection . ' database name is not configured.');
        }

        $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $safeDb = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    private function checkServiceConnection(): void
    {
        $connection = config('service.database_connection', 'service');

        DB::connection($connection)->getPdo();
        DB::connection($connection)->select('SELECT 1');
    }

    private function enableModuleInStatuses(): void
    {
        $path = base_path('modules_statuses.json');

        if (! file_exists($path)) {
            return;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return;
        }

        $json[$this->moduleDisplayName] = true;
        file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function disableModuleInStatuses(): void
    {
        $path = base_path('modules_statuses.json');

        if (! file_exists($path)) {
            return;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return;
        }

        $json[$this->moduleDisplayName] = false;
        file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function removeModuleBootstrapCache(): void
    {
        foreach (glob(base_path('bootstrap/cache/*service*')) ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function removePublishedAssets(): void
    {
        $path = public_path('modules/service');

        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    private function dropServiceDatabase(): void
    {
        $connection = config('service.database_connection', 'service');
        $cfg = Config::get('database.connections.' . $connection);

        if (empty($cfg)) {
            throw new \RuntimeException($connection . ' connection is not configured.');
        }

        $database = $cfg['database'] ?? null;
        $defaultDatabase = Config::get('database.connections.' . Config::get('database.default') . '.database');

        if (empty($database)) {
            throw new \RuntimeException($connection . ' database name is not configured.');
        }

        if ($database === $defaultDatabase) {
            throw new \RuntimeException('Refusing to drop the main application database.');
        }

        DB::purge($connection);

        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int) ($cfg['port'] ?? 3306);
        $username = $cfg['username'] ?? 'root';
        $password = $cfg['password'] ?? '';

        $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $safeDb = str_replace('`', '``', $database);
        $pdo->exec("DROP DATABASE IF EXISTS `{$safeDb}`");
    }

    private function clearCaches(): void
    {
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
        }
    }

    private function isInstalled(): bool
    {
        return ! empty(System::getProperty($this->moduleName . '_version'));
    }

    private function authorizeModuleManagement(): void
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }
    }
}

<?php

namespace Modules\KneayerngLinks\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    private string $moduleName = 'kneayernglinks';

    private string $moduleDisplayName = 'KneayerngLinks';

    private string $permission = 'kneayernglinks.access';

    private string $appVersion = '1.0.0';

    public function __construct()
    {
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

    public function uninstall()
    {
        $this->authorizeModuleManagement();

        try {
            $this->deleteModulePermissions($this->moduleName);

            System::removeProperty($this->moduleName . '_version');
            $this->disableModuleInStatuses();
            $this->removeModuleBootstrapCache();
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
        $this->enableModuleInStatuses();
        $this->clearCaches();
        $this->ensureModulePermissions($this->moduleName);
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
        $path = base_path('bootstrap/cache/kneayernglinks_module.php');

        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function clearCaches(): void
    {
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            \Illuminate\Support\Facades\Artisan::call($cmd);
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

    private function ensureModulePermissions(string $module_name): void
    {
        $controller = $this->moduleDataController($module_name);
        if (! $controller || ! class_exists($controller) || ! method_exists($controller, 'user_permissions')) {
            return;
        }

        foreach ((array) app($controller)->user_permissions() as $permission) {
            $permission_name = is_array($permission)
                ? ($permission['value'] ?? $permission['name'] ?? null)
                : null;

            if (! empty($permission_name)) {
                \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission_name, 'guard_name' => 'web']);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function deleteModulePermissions(string $module_name): void
    {
        $controller = $this->moduleDataController($module_name);
        if (! $controller || ! class_exists($controller) || ! method_exists($controller, 'user_permissions')) {
            return;
        }

        $permissions = [];
        foreach ((array) app($controller)->user_permissions() as $permission) {
            $permission_name = is_array($permission)
                ? ($permission['value'] ?? $permission['name'] ?? null)
                : null;

            if (! empty($permission_name)) {
                $permissions[] = $permission_name;
            }
        }

        if (! empty($permissions)) {
            \Spatie\Permission\Models\Permission::whereIn('name', $permissions)->where('guard_name', 'web')->delete();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function moduleDataController(string $module_name): string
    {
        return 'Modules\\' . $module_name . '\\Http\\Controllers\\DataController';
    }
}

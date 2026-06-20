<?php

namespace Modules\SmartStockInventory\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'smartstockinventory';
        $this->appVersion = config('smartstockinventory.module_version', '1.0.0');
        $this->module_display_name = 'SmartStockInventory';
    }

    public function index()
    {
        $this->authorizeModuleManagement();

        if (! empty(System::getProperty($this->module_name . '_version'))) {
            return $this->redirectAfterInstall([
                    'success' => 1,
                    'msg' => $this->module_display_name . ' module is already installed.',
                ]);
        }

        $action_url = $this->isAccessorySmartStockRequest()
            ? url(config('accessory.route_prefix', 'accessory') . '/smart-stock-inventory/install')
            : action([self::class, 'install']);
        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->module_display_name;

        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));
    }

    public function install()
    {
        $this->authorizeModuleManagement();

        try {
            DB::beginTransaction();

            if (! empty(System::getProperty($this->module_name . '_version'))) {
                DB::rollBack();
                return $this->redirectAfterInstall([
                    'success' => 1,
                    'msg' => $this->module_display_name . ' module is already installed.',
                ]);
            }

            Artisan::call('module:migrate', ['module' => 'SmartStockInventory', '--force' => true]);
            $this->ensurePermissionsExist();
            System::addProperty($this->module_name . '_version', $this->appVersion);
            $this->setModuleStatus(true);
            $this->clearCaches();

            DB::commit();
            $output = ['success' => 1, 'msg' => $this->module_display_name . ' module installed successfully'];
        } catch (\Exception $e) {
            DB::rollBack();
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return $this->redirectAfterInstall($output);
    }

    public function uninstall()
    {
        $this->authorizeModuleManagement();

        try {
            $this->deletePermissions();
            System::removeProperty($this->module_name . '_version');
            $this->setModuleStatus(false);
            $this->clearCaches();
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function update()
    {
        $this->authorizeModuleManagement();

        try {
            if (empty(System::getProperty($this->module_name . '_version'))) {
                abort(404);
            }

            Artisan::call('module:migrate', ['module' => 'SmartStockInventory', '--force' => true]);
            $this->ensurePermissionsExist();
            System::setProperty($this->module_name . '_version', $this->appVersion);
            $this->setModuleStatus(true);
            $this->clearCaches();
            $output = ['success' => 1, 'msg' => $this->module_display_name . ' module updated successfully to version ' . $this->appVersion];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function authorizeModuleManagement(): void
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function permissions(): array
    {
        return array_column((new DataController())->user_permissions(), 'value');
    }

    private function ensurePermissionsExist(): void
    {
        foreach ($this->permissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function deletePermissions(): void
    {
        Permission::whereIn('name', $this->permissions())
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function clearCaches(): void
    {
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
        }
    }

    private function setModuleStatus(bool $active): void
    {
        $path = base_path('modules_statuses.json');
        $statuses = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $statuses[$this->module_display_name] = $active;

        file_put_contents($path, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function redirectAfterInstall(array $status)
    {
        if ($this->isAccessorySmartStockRequest()) {
            return redirect()
                ->to(url(config('accessory.route_prefix', 'accessory') . '/home'))
                ->with('status', $status);
        }

        return redirect()
            ->action([ModulesIndexController::class, 'index'])
            ->with('status', $status);
    }

    private function isAccessorySmartStockRequest(): bool
    {
        return request()->is(trim(config('accessory.route_prefix', 'accessory'), '/') . '/smart-stock-inventory/install*');
    }
}

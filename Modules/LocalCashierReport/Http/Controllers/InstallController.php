<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'localcashierreport';
        $this->appVersion = config('localcashierreport.module_version', '1.0.0');
        $this->module_display_name = 'LocalCashierReport';
        $this->permission = 'local_cashier_report.view';
    }

    public function index()
    {
        $this->authorizeModuleManagement();

        if (! empty(System::getProperty($this->module_name . '_version'))) {
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
        $this->authorizeModuleManagement();

        if (! empty(System::getProperty($this->module_name . '_version'))) {
            return redirect()
                ->action([ModulesIndexController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module is already installed.']);
        }

        $this->ensurePermissionsExist();
        System::addProperty($this->module_name . '_version', $this->appVersion);
        $this->setModuleStatus(true);
        $this->clearCaches();

        return redirect()
            ->action([ModulesIndexController::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module installed successfully']);
    }

    public function uninstall()
    {
        $this->authorizeModuleManagement();

        try {
            Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
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

    private function ensurePermissionsExist(): void
    {
        Permission::firstOrCreate([
            'name' => $this->permission,
            'guard_name' => 'web',
        ]);

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
}

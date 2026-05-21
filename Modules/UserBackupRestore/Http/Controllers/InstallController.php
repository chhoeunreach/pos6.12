<?php

namespace Modules\UserBackupRestore\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'userbackuprestore';
        $this->appVersion = config('userbackuprestore.module_version', '1.0.0');
        $this->module_display_name = 'UserBackupRestore';
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

        System::addProperty($this->module_name . '_version', $this->appVersion);

        return redirect()
            ->action([ModulesIndexController::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module installed successfully']);
    }

    public function uninstall()
    {
        $this->authorizeModuleManagement();

        try {
            System::removeProperty($this->module_name . '_version');
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

            System::setProperty($this->module_name . '_version', $this->appVersion);
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
}

<?php

namespace Modules\ManageLot\Http\Controllers;

use App\Http\Controllers\Install\ModulesController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        // Core ModuleUtil::isModuleInstalled() checks: strtolower(ModuleName) . '_version'
        // For "ManageLot" => "managelot_version"
        $this->module_name = 'managelot';
        $this->appVersion = config('manage_lot.module_version');
        $this->module_display_name = config('manage_lot.module_display_name', 'Manage Lot');
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name . '_version');
        if (! empty($is_installed)) {
            return redirect()
                ->action([ModulesController::class, 'index'])
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
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $is_installed = System::getProperty($this->module_name . '_version');
            if (! empty($is_installed)) {
                DB::rollBack();
                return redirect()
                    ->action([ModulesController::class, 'index'])
                    ->with('status', ['success' => 1, 'msg' => $this->module_display_name . ' module is already installed.']);
            }

            Artisan::call('module:enable', ['module' => 'ManageLot']);

            // No DB writes/migrations needed for this REPORT module, but keep migrate hook for future optional changes.
            Artisan::call('module:migrate', ['module' => 'ManageLot', '--force' => true]);
            Artisan::call('optimize:clear');

            System::addProperty($this->module_name . '_version', $this->appVersion);

            DB::commit();

            $output = ['success' => 1, 'msg' => $this->module_display_name . ' module installed successfully'];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()
            ->action([ModulesController::class, 'index'])
            ->with('status', $output);
    }

    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Artisan::call('module:disable', ['module' => 'ManageLot']);
            System::removeProperty($this->module_name . '_version');
            Artisan::call('optimize:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            $output = ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function update()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $installedVersion = System::getProperty($this->module_name . '_version');
            if (empty($installedVersion) || version_compare($this->appVersion, $installedVersion, '>')) {
                Artisan::call('module:migrate', ['module' => 'ManageLot', '--force' => true]);
                System::setProperty($this->module_name . '_version', $this->appVersion);
            } else {
                abort(404);
            }

            $output = ['success' => 1, 'msg' => $this->module_display_name . ' module updated successfully to version ' . $this->appVersion];
            return redirect()->back()->with(['status' => $output]);
        } catch (\Exception $e) {
            return redirect()->back()->with(['status' => ['success' => false, 'msg' => $e->getMessage()]]);
        }
    }
}


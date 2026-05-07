<?php

namespace Modules\ImportBackup\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'importbackup';
        $this->appVersion = config('importbackup.module_version', '1.0.0');
        $this->module_display_name = 'ImportBackup';
    }

    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name . '_version');
        if (! empty($is_installed)) {
            $output = [
                'success' => true,
                'msg' => $this->module_display_name . ' module is already installed.',
            ];

            return redirect()
                ->action([ModulesIndexController::class, 'index'])
                ->with('status', $output);
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
                abort(404);
            }

            try {
                Artisan::call('module:publish', ['module' => 'ImportBackup', '--force' => true]);
            } catch (\Throwable $e) {
                Artisan::call('module:publish');
            }

            System::addProperty($this->module_name . '_version', $this->appVersion);
            DB::commit();

            $output = ['success' => 1, 'msg' => 'ImportBackup module installed successfully'];
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
            $installed_version = System::getProperty($this->module_name . '_version');
            if (empty($installed_version)) {
                abort(404);
            }

            System::setProperty($this->module_name . '_version', $this->appVersion);
            $output = ['success' => 1, 'msg' => 'ImportBackup module updated successfully to version ' . $this->appVersion];
        } catch (\Exception $e) {
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}

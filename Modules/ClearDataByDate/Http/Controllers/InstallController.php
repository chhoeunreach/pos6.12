<?php

namespace Modules\ClearDataByDate\Http\Controllers;

use App\System;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        // IMPORTANT:
        // Core ModuleUtil::isModuleInstalled() checks System property: strtolower(ModuleName) . '_version'
        // For this module, ModuleName is "ClearDataByDate" so the expected key is "cleardatabydate_version".
        // Keep the legacy snake_case key for backward compatibility.
        $this->module_name = 'cleardatabydate';
        $this->legacy_module_name = 'clear_data_by_date';
        $this->appVersion = config('clear_data_by_date.module_version');
        $this->module_display_name = 'Clear Data by Date';
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name.'_version') ?: System::getProperty($this->legacy_module_name.'_version');
        if (! empty($is_installed)) {
            return redirect()
                ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => $this->module_display_name.' module is already installed.']);
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
        try {
            DB::beginTransaction();

            $is_installed = System::getProperty($this->module_name.'_version') ?: System::getProperty($this->legacy_module_name.'_version');
            if (! empty($is_installed)) {
                DB::rollBack();
                return redirect()
                    ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
                    ->with('status', ['success' => 1, 'msg' => $this->module_display_name.' module is already installed.']);
            }

            // Some deployments keep ClearDataByDate tables in the main app migrations.
            // If the module has its own migrations, run them; otherwise skip gracefully.
            $migrationPath = base_path('Modules/ClearDataByDate/Database/Migrations');
            if (is_dir($migrationPath)) {
                Artisan::call('module:migrate', ['module' => 'ClearDataByDate', '--force' => true]);
            }
            System::addProperty($this->module_name.'_version', $this->appVersion);
            // Clean up legacy key if it exists.
            if (! empty(System::getProperty($this->legacy_module_name.'_version'))) {
                System::removeProperty($this->legacy_module_name.'_version');
            }

            DB::commit();

            $output = ['success' => 1,
                'msg' => $this->module_display_name.' module installed successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            System::removeProperty($this->module_name.'_version');
            System::removeProperty($this->legacy_module_name.'_version');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    public function update()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $installedVersion = System::getProperty($this->module_name.'_version') ?: System::getProperty($this->legacy_module_name.'_version');
            if (version_compare($this->appVersion, $installedVersion, '>')) {
                Artisan::call('module:migrate', ['module' => 'ClearDataByDate', '--force' => true]);
                System::setProperty($this->module_name.'_version', $this->appVersion);
                System::removeProperty($this->legacy_module_name.'_version');
            } else {
                abort(404);
            }

            $output = ['success' => 1,
                'msg' => $this->module_display_name.' module updated successfully to version '.$this->appVersion,
            ];

            return redirect()->back()->with(['status' => $output]);
        } catch (\Exception $e) {
            return redirect()->back()->with(['status' => ['success' => false, 'msg' => $e->getMessage()]]);
        }
    }
}

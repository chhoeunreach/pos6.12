<?php

namespace Modules\FixStockMismatch\Http\Controllers;

use App\Http\Controllers\Install\ModulesController as ModulesIndexController;
use App\System;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'fixstockmismatch';
        $this->appVersion = config('fixstockmismatch.module_version', '1.0.0');
        $this->module_display_name = 'FixStockMismatch';
    }

    /**
     * Show install page.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        $this->installSettings();

        $is_installed = System::getProperty($this->module_name . '_version');
        if (! empty($is_installed)) {
            abort(404);
        }

        $action_url = action([self::class, 'install']);
        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->module_display_name;

        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));
    }

    /**
     * Initialize all install functions.
     */
    private function installSettings(): void
    {
        config(['app.debug' => true]);
        Artisan::call('config:clear');
    }

    /**
     * Install module (no license required).
     */
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

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'FixStockMismatch', '--force' => true]);

            // Publish assets (safe even if module has none).
            try {
                Artisan::call('module:publish', ['module' => 'FixStockMismatch', '--force' => true]);
            } catch (\Throwable $e) {
                Artisan::call('module:publish');
            }

            System::addProperty($this->module_name . '_version', $this->appVersion);

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => 'FixStockMismatch module installed successfully',
            ];
        } catch (\Exception $e) {
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

    /**
     * Uninstall (keeps module data, just removes version flag).
     *
     * @return Response
     */
    public function uninstall()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            System::removeProperty($this->module_name . '_version');

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Update: re-run migrations and bump version flag.
     *
     * @return Response
     */
    public function update()
    {
        if (! auth()->user()->can('manage_modules')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '512M');

            $installed_version = System::getProperty($this->module_name . '_version');
            if (empty($installed_version)) {
                abort(404);
            }

            $this->installSettings();

            DB::statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', ['module' => 'FixStockMismatch', '--force' => true]);
            System::setProperty($this->module_name . '_version', $this->appVersion);

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => 'FixStockMismatch module updated successfully to version ' . $this->appVersion,
            ];

            return redirect()->back()->with(['status' => $output]);
        } catch (\Exception $e) {
            DB::rollBack();
            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];

            return redirect()->back()->with(['status' => $output]);
        }
    }
}


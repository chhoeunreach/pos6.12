<?php

namespace Modules\NotificationCenter\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'notificationcenter';
        $this->appVersion = config('notificationcenter.module_version');
        $this->module_display_name = 'Notification Center';
    }

    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $is_installed = System::getProperty($this->module_name.'_version');
        if (! empty($is_installed)) {
            abort(404);
        }

        $action_url = action([\Modules\NotificationCenter\Http\Controllers\InstallController::class, 'install']);
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

            request()->validate([
                'license_code' => 'required',
                'login_username' => 'required',
            ]);

            $license_code = request()->license_code;
            $email = request()->email;
            $login_username = request()->login_username;
            $pid = config('notificationcenter.pid');

            $response = pos_boot(url('/'), __DIR__, $license_code, $email, $login_username, $type = 1, $pid);
            if (! empty($response)) {
                return $response;
            }

            $is_installed = System::getProperty($this->module_name.'_version');
            if (! empty($is_installed)) {
                abort(404);
            }

            Artisan::call('module:migrate', ['module' => 'NotificationCenter', '--force' => true]);
            System::addProperty($this->module_name.'_version', $this->appVersion);
            $this->clearCaches();

            DB::commit();

            $output = ['success' => 1, 'msg' => $this->module_display_name.' module installed successfully'];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => $e->getMessage()];
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
            $this->clearCaches();
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
            DB::beginTransaction();

            $installed_version = System::getProperty($this->module_name.'_version');
            if (version_compare($this->appVersion, $installed_version, '>')) {
                Artisan::call('module:migrate', ['module' => 'NotificationCenter', '--force' => true]);
                System::setProperty($this->module_name.'_version', $this->appVersion);
                $this->clearCaches();
            } else {
                abort(404);
            }

            DB::commit();
            $output = ['success' => 1, 'msg' => $this->module_display_name.' updated to version '.$this->appVersion];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => $e->getMessage()];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    private function clearCaches(): void
    {
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
        }
    }
}

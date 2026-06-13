<?php

namespace Modules\Repair\Http\Controllers;

use App\System;
use Composer\Semver\Comparator;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'repair';
        $this->appVersion = config('repair.module_version');
        $this->module_display_name = 'Repair';
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        $this->installSettings();

        //Check if installed or not.
        $installed_version = $this->getInstalledVersion();
        if (! empty($installed_version)) {
            return $this->alreadyInstalledResponse($installed_version);
        }

        $action_url = $this->isServiceRepairRequest()
            ? url(config('service.route_prefix', 'service').'/repair/install')
            : url('repair/install');
        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->module_display_name;
        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));

    }

    /**
     * Initialize all install functions
     */
    private function installSettings()
    {
        config(['app.debug' => true]);
        Artisan::call('config:clear');
    }

    /**
     * Installing Repair Module
     */
    public function install()
    {
        $connection = $this->moduleConnection();

        try {
            DB::connection($connection)->beginTransaction();

            request()->validate(
                ['license_code' => 'required',
                    'login_username' => 'required', ],
                ['license_code.required' => 'License code is required',
                    'login_username.required' => 'Username is required', ]
            );

            $license_code = request()->license_code;
            $email = request()->email;
            $login_username = request()->login_username;
            $pid = config('repair.pid');

            //Validate
            $response = pos_boot(url('/'), __DIR__, $license_code, $email, $login_username, $type = 1, $pid);

            if (! empty($response)) {
                return $response;
            }

            $installed_version = $this->getInstalledVersion();
            if (! empty($installed_version)) {
                DB::connection($connection)->rollBack();

                return $this->alreadyInstalledResponse($installed_version);
            }

            DB::connection($connection)->statement('SET default_storage_engine=INNODB;');
            Artisan::call('module:migrate', [
                'module' => 'Repair',
                '--database' => $connection,
                '--force' => true,
            ]);
            $this->addInstalledVersion($this->appVersion);
            $this->clearCaches();

            DB::connection($connection)->commit();

            $output = ['success' => 1,
                'msg' => 'Repair module installed succesfully',
            ];
        } catch (\Exception $e) {
            DB::connection($connection)->rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        if ($this->isServiceRepairRequest()) {
            return redirect()
                ->to(url(config('service.route_prefix', 'service').'/home'))
                ->with('status', $output);
        }

        return redirect()
                ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
                ->with('status', $output);
    }

    /**
     * Uninstall
     *
     * @return Response
     */
    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $this->removeInstalledVersion();
            $this->clearCaches();

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

    /**
     * update module
     *
     * @return Response
     */
    public function update()
    {
        //Check if repair_version is same as appVersion then 404
        //If appVersion > repair_version - run update script.
        //Else there is some problem.
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $connection = $this->moduleConnection();

        try {
            DB::connection($connection)->beginTransaction();
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '512M');

            $repair_version = $this->getInstalledVersion();

            if (Comparator::greaterThan($this->appVersion, $repair_version)) {
                ini_set('max_execution_time', 0);
                ini_set('memory_limit', '512M');
                $this->installSettings();

                DB::connection($connection)->statement('SET default_storage_engine=INNODB;');
                Artisan::call('module:migrate', [
                    'module' => 'Repair',
                    '--database' => $connection,
                    '--force' => true,
                ]);
                $this->setInstalledVersion($this->appVersion);
                $this->clearCaches();
            } else {
                DB::connection($connection)->rollBack();

                return $this->alreadyInstalledResponse($repair_version);
            }

            DB::connection($connection)->commit();

            $output = ['success' => 1,
                'msg' => 'Repair module updated Succesfully to version '.$this->appVersion.' !!',
            ];

            return redirect()->back()->with(['status' => $output]);
        } catch (\Exception $e) {
            DB::connection($connection)->rollBack();
            exit($e->getMessage());
        }
    }

    private function moduleConnection(): string
    {
        return $this->isServiceRepairRequest()
            ? config('service.database_connection', 'service')
            : config('database.default', 'mysql');
    }

    private function getInstalledVersion(): ?string
    {
        $row = (new System)
            ->setConnection($this->moduleConnection())
            ->newQuery()
            ->where('key', $this->module_name.'_version')
            ->first();

        return $row->value ?? null;
    }

    private function addInstalledVersion(string $version): void
    {
        (new System)
            ->setConnection($this->moduleConnection())
            ->newQuery()
            ->updateOrCreate(
                ['key' => $this->module_name.'_version'],
                ['value' => $version]
            );
    }

    private function setInstalledVersion(string $version): void
    {
        (new System)
            ->setConnection($this->moduleConnection())
            ->newQuery()
            ->where('key', $this->module_name.'_version')
            ->update(['value' => $version]);
    }

    private function removeInstalledVersion(): void
    {
        (new System)
            ->setConnection($this->moduleConnection())
            ->newQuery()
            ->where('key', $this->module_name.'_version')
            ->delete();
    }

    private function alreadyInstalledResponse(?string $installed_version)
    {
        $message = $this->module_display_name.' module is already installed.';
        if (! empty($installed_version)) {
            $message = $this->module_display_name.' module is already installed (version '.$installed_version.').';
        }

        $output = [
            'success' => false,
            'msg' => $message,
        ];

        if ($this->isServiceRepairRequest()) {
            return redirect()
                ->to($this->serviceRepairHomeUrl())
                ->with('status', $output);
        }

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    private function serviceRepairHomeUrl(): string
    {
        return url(trim(config('service.route_prefix', 'service'), '/').'/repair/dashboard');
    }

    private function clearCaches(): void
    {
        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            Artisan::call($cmd);
        }
    }

    private function isServiceRepairRequest(): bool
    {
        return request()->is(trim(config('service.route_prefix', 'service'), '/').'/repair/install*');
    }
}

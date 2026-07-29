<?php

namespace Modules\HrSellManagement\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class InstallController extends Controller
{
    public function install()
    {
        abort_unless(auth()->user()->can('manage_modules') || auth()->user()->can('superadmin') || auth()->user()->can('business_settings.access'), 403);

        try {
            DB::beginTransaction();

            Artisan::call('module:migrate', ['module' => 'HrSellManagement', '--force' => true]);
            foreach ((new DataController())->user_permissions() as $permission) {
                Permission::firstOrCreate(['name' => $permission['value']], ['guard_name' => 'web']);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            System::addProperty('hrsellmanagement_version', config('hrsellmanagement.module_version', '1.0.0'));
            $this->setModuleStatus(true);

            DB::commit();
            $status = ['success' => 1, 'msg' => 'HR Sell Management installed'];
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = ['success' => 0, 'msg' => $e->getMessage()];
        }

        foreach (['optimize:clear', 'config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (\Throwable $e) {
            }
        }

        return redirect()->route('hr-sell.dashboard')->with('status', $status);
    }

    private function setModuleStatus(bool $active): void
    {
        $path = base_path('modules_statuses.json');
        $statuses = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $statuses['HrSellManagement'] = $active;
        file_put_contents($path, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

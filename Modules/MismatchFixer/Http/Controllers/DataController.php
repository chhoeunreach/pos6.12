<?php

namespace Modules\MismatchFixer\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package()
    {
        return [[
            'name' => 'mismatchfixer_module',
            'label' => 'MismatchFixer Module',
            'default' => false,
        ]];
    }

    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'mismatchfixer_module', 'superadmin_package');

        if ($is_enabled && auth()->user()->can('mismatch_fixer.view')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown('Inventory Tools', function ($sub) {
                    $sub->url(url('/mismatch-fixer'), 'Mismatch Detector', ['icon' => 'fa fa-search']);
                    $sub->url(url('/mismatch-fixer?tab=broken_transfers'), 'Broken Transfers', ['icon' => 'fa fa-chain-broken']);
                    $sub->url(url('/mismatch-fixer/logs'), 'Fix Logs', ['icon' => 'fa fa-list']);
                    if (auth()->user()->can('mismatch_fixer.settings')) {
                        $sub->url(url('/mismatch-fixer/settings'), 'Settings', ['icon' => 'fa fa-cog']);
                    }
                }, [
                    'icon' => 'fa fa-wrench',
                    'active' => request()->is('mismatch-fixer*'),
                ])->order(97);
            });
        }
    }

    public function user_permissions()
    {
        return [
            ['value' => 'mismatch_fixer.view', 'label' => 'View Mismatch Fixer', 'default' => false],
            ['value' => 'mismatch_fixer.fix', 'label' => 'Fix Mismatch Rows', 'default' => false],
            ['value' => 'mismatch_fixer.logs', 'label' => 'View Mismatch Fix Logs', 'default' => false],
            ['value' => 'mismatch_fixer.settings', 'label' => 'Manage Mismatch Fixer Settings', 'default' => false],
        ];
    }
}

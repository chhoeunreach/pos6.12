<?php

namespace Modules\FixStockMismatch\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Utils\ModuleUtil;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'fixstockmismatch_module',
                'label' => __('fixstockmismatch::lang.fixstockmismatch_module'),
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();

        $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
            $business_id,
            'fixstockmismatch_module',
            'superadmin_package'
        );

        if ($is_enabled && auth()->user()->can('stock_mismatch.view')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    url('stock-mismatch'),
                    __('fixstockmismatch::lang.fix_stock_mismatch'),
                    [
                        'icon' => 'fa fas fa-wrench',
                        'active' => request()->segment(1) == 'stock-mismatch',
                        'style' => config('app.env') == 'demo' ? 'background-color: #ff851b;' : '',
                    ]
                )->order(96);
            });
        }
    }

    public function user_permissions()
    {
        return [
            [
                'value' => 'stock_mismatch.view',
                'label' => 'View Stock Mismatch',
                'default' => false,
            ],
            [
                'value' => 'stock_mismatch.fix',
                'label' => 'Fix Stock Mismatch',
                'default' => false,
            ],
        ];
    }
}

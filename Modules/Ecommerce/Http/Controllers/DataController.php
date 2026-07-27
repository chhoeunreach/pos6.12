<?php

namespace Modules\Ecommerce\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    protected static $menu_registered = false;

    public function modifyAdminMenu()
    {
        if (self::$menu_registered) {
            return;
        }

        if (! auth()->check() || ! auth()->user()->can('business_settings.access')) {
            return;
        }

        self::$menu_registered = true;

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                route('ecom-api-settings.index'),
                'Ecommerce API',
                [
                    'icon' => '',
                    'active' => request()->segment(1) == 'ecommerce-api-settings',
                ]
            )->order(86);
        });
    }
}

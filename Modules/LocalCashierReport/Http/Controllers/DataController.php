<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'local_cashier_report.view',
                'label' => 'Local Cashier Report (view)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (! auth()->check() || ! auth()->user()->can('local_cashier_report.view')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $reports = $menu->whereTitle(__('report.reports'));

            if (! empty($reports)) {
                $reports->url(
                    route('local-cashier-report.index'),
                    'Local Cashier Report',
                    ['icon' => '', 'active' => request()->segment(1) === 'local-cashier-report']
                )->order(999);

                return;
            }

            $menu->url(
                route('local-cashier-report.index'),
                'Local Cashier Report',
                ['icon' => 'fa fa-file-text-o', 'active' => request()->segment(1) === 'local-cashier-report']
            )->order(999);
        });
    }
}

<?php

namespace Modules\MasterData\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'master_data.view',
                'label' => 'Master Data (view)',
                'default' => false,
            ],
            [
                'value' => 'master_data.export',
                'label' => 'Master Data (export)',
                'default' => false,
            ],
            [
                'value' => 'master_data.import',
                'label' => 'Master Data (import)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        $business_id = (int) session('business.id');
        $is_admin = auth()->user()->hasRole('Admin#' . $business_id) ? true : false;
        $is_superadmin = auth()->user()->can('superadmin');

        if (! ($is_admin || $is_superadmin || auth()->user()->can('master_data.view') || auth()->user()->can('master_data.export') || auth()->user()->can('master_data.import'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                route('master-data.index'),
                'Master Data',
                [
                    'icon' => '<svg aria-hidden="true" class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M4 6l8 -3l8 3l0 12l-8 3l-8 -3z"></path>
                    <path d="M4 6l8 3l8 -3"></path>
                    <path d="M12 9l0 12"></path>
                    </svg>',
                    'active' => request()->segment(1) === 'master-data',
                ]
            )->order(63);
        });
    }
}


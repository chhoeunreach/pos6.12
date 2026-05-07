<?php

namespace Modules\ClearDataByDate\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'clear_data_by_date.access',
                'label' => 'Clear Data by Date (access)',
                'default' => false,
            ],
            // Backward/forward compatibility with some UltimatePOS builds
            [
                'value' => 'clear_data_by_date.view',
                'label' => 'Clear Data by Date (view)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        if (! auth()->user()->can('clear_data_by_date.access') && ! auth()->user()->can('clear_data_by_date.view')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $settingsTitle = __('business.settings');
            $settings = $menu->whereTitle($settingsTitle);

            if (! empty($settings)) {
                $settings->url(
                    route('clear_data_by_date.index'),
                    'Clear Data by Date',
                    ['icon' => '', 'active' => request()->segment(1) === 'clear-data-by-date']
                )->order(999);

                return;
            }

            $menu->url(
                route('clear_data_by_date.index'),
                'Clear Data by Date',
                ['icon' => '<i class="fa fa-trash"></i>', 'active' => request()->segment(1) === 'clear-data-by-date']
            )->order(999);
        });
    }
}


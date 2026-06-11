<?php

namespace Modules\NotificationCenter\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'notification_center.access',
                'label' => 'Notification Center (access)',
                'default' => false,
            ],
            [
                'value' => 'notification_center.groups',
                'label' => 'Notification Center (manage groups)',
                'default' => false,
            ],
            [
                'value' => 'notification_center.templates',
                'label' => 'Notification Center (manage templates)',
                'default' => false,
            ],
            [
                'value' => 'notification_center.settings',
                'label' => 'Notification Center (settings)',
                'default' => false,
            ],
            [
                'value' => 'notification_center.logs',
                'label' => 'Notification Center (view logs)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->user()->can('notification_center.access')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                url('notification-center/groups'),
                __('Notification Center'),
                [
                    'icon' => '<svg aria-hidden="true" class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M10 5a2 2 0 0 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"></path>
                    <path d="M9 17v1a3 3 0 0 0 6 0v-1"></path>
                    </svg>',
                    'active' => request()->segment(1) === 'notification-center',
                ]
            )->order(60);
        });
    }
}

<?php

namespace Modules\UserBackupRestore\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'user_backup_restore.view',
                'label' => 'User Backup Restore (view)',
                'default' => false,
            ],
            [
                'value' => 'user_backup_restore.export',
                'label' => 'User Backup Restore (export)',
                'default' => false,
            ],
            [
                'value' => 'user_backup_restore.import',
                'label' => 'User Backup Restore (import)',
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

        if (! ($is_admin || $is_superadmin || auth()->user()->can('user_backup_restore.view') || auth()->user()->can('user_backup_restore.export') || auth()->user()->can('user_backup_restore.import') || auth()->user()->can('user.view') || auth()->user()->can('user.create'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                route('user-backup-restore.index'),
                'User Backup Restore',
                [
                    'icon' => '<svg aria-hidden="true" class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M12 18.004h-5.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.38 0 2.57 .811 3.128 1.986"></path>
                    <path d="M12 9v6"></path>
                    <path d="M9 12l3 3l3 -3"></path>
                    </svg>',
                    'active' => request()->segment(1) === 'user-backup-restore',
                ]
            )->order(62);
        });
    }
}


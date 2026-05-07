<?php

namespace Modules\CustomBackup\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function modifyAdminMenu()
    {
        $is_admin_for_backup_menu = auth()->user()->hasRole('Admin#' . session('business.id')) ? true : false;
        $is_superadmin_for_backup_menu = auth()->user()->can('superadmin');

        if (! (auth()->user()->can('backup') || $is_admin_for_backup_menu || $is_superadmin_for_backup_menu)) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action([CustomBackupController::class, 'index']),
                'Custom Backup',
                [
                    'icon' => '<svg aria-hidden="true" class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                <path d="M12 18.004h-5.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.38 0 2.57 .811 3.128 1.986"></path>
                <path d="M19 22v-6"></path>
                <path d="M22 19l-3 -3l-3 3"></path>
              </svg>',
                    'active' => request()->segment(1) == 'custom-backup',
                ]
            )->order(60);
        });
    }
}

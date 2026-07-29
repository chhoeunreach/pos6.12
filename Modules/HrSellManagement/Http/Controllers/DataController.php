<?php

namespace Modules\HrSellManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [['name' => 'hrsellmanagement_module', 'label' => 'HR Sell Management Module', 'default' => false]];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'hr_sell.view', 'label' => 'HR Sell - View dashboard and sales', 'default' => false],
            ['value' => 'hr_sell.create', 'label' => 'HR Sell - Link sales', 'default' => false],
            ['value' => 'hr_sell.update', 'label' => 'HR Sell - Update sales and notes', 'default' => false],
            ['value' => 'hr_sell.approve', 'label' => 'HR Sell - Approve/reject', 'default' => false],
            ['value' => 'hr_sell.report', 'label' => 'HR Sell - Reports', 'default' => false],
            ['value' => 'hr_sell.report.edit', 'label' => 'HR Sell - Edit reports', 'default' => false],
            ['value' => 'hr_sell.report.delete', 'label' => 'HR Sell - Delete reports', 'default' => false],
            ['value' => 'hr_sell.settings', 'label' => 'HR Sell - Settings', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();
        $canOpen = $this->canAnyHrSellPermission($user);

        if (! $canOpen) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($user) {
            $menu->dropdown('HR Sell', function ($sub) use ($user) {
                if ($user->can('hr_sell.view') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('hr-sell.dashboard'), 'Dashboard', [
                        'icon' => 'fa fa-dashboard',
                        'active' => request()->is('hr-sell'),
                    ]);

                    $sub->url(route('hr-sell.sales.index'), 'HR Sell List', [
                        'icon' => 'fa fa-list',
                        'active' => request()->is('hr-sell/sales') || request()->is('hr-sell/sales/*'),
                    ]);
                }

                if ($user->can('hr_sell.report') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('hr-sell.reports.index'), 'Reports', [
                        'icon' => 'fa fa-bar-chart',
                        'active' => request()->is('hr-sell/reports*'),
                    ]);
                }

                if ($user->can('hr_sell.settings') || $user->can('superadmin') || $user->can('business_settings.access')) {
                    $sub->url(route('hr-sell.settings.index'), 'Settings', [
                        'icon' => 'fa fa-cogs',
                        'active' => request()->is('hr-sell/settings*'),
                    ]);
                }
            }, ['icon' => 'fa fa-users', 'active' => request()->is('hr-sell*')])->order(36);
        });
    }

    private function canAnyHrSellPermission($user): bool
    {
        foreach (['view', 'create', 'update', 'approve', 'report', 'report.edit', 'report.delete', 'settings'] as $ability) {
            if ($user->can('hr_sell.' . $ability)) {
                return true;
            }
        }

        return $user->can('superadmin') || $user->can('business_settings.access');
    }
}

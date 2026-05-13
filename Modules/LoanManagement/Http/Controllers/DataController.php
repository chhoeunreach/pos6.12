<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [[
            'name' => 'loanmanagement_module',
            'label' => 'LoanManagement Module',
            'default' => false,
        ]];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'loan_management.view', 'label' => 'Loan Management (view)', 'default' => false],
            ['value' => 'loan_management.create', 'label' => 'Loan Management (create)', 'default' => false],
            ['value' => 'loan_management.edit', 'label' => 'Loan Management (edit)', 'default' => false],
            ['value' => 'loan_management.delete', 'label' => 'Loan Management (delete)', 'default' => false],
            ['value' => 'loan_management.approve', 'label' => 'Loan Management (approve)', 'default' => false],
            ['value' => 'loan_management.payment', 'label' => 'Loan Management (payment)', 'default' => false],
            ['value' => 'loan_management.report', 'label' => 'Loan Management (report)', 'default' => false],
            ['value' => 'loan_management.setting', 'label' => 'Loan Management (setting)', 'default' => false],
            ['value' => 'loan_management.sell_list', 'label' => 'Loan Sell List', 'default' => false],
            ['value' => 'loan_management.sell_view', 'label' => 'Loan Sell View', 'default' => false],
            ['value' => 'loan_management.sell_convert', 'label' => 'Loan Sell Convert', 'default' => false],
            ['value' => 'loan_management.create_from_sell', 'label' => 'Create Loan From Sell', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->check() || ! auth()->user()->can('loan_management.view')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $root = $menu->dropdown('Installment / Loan', function ($sub) {
                $sub->url(route('loan-management.dashboard'), 'Dashboard', ['icon' => 'fa fa-dashboard']);
                $sub->url(route('loan-management.loans.create-from-sell'), 'Create Loan From Sell', ['icon' => 'fa fa-plus-circle']);
                $sub->url(route('loan-management.loans'), 'Installment List', ['icon' => 'fa fa-file-text-o']);
                $sub->url(route('loan-management.payments'), 'Payments', ['icon' => 'fa fa-money']);
                $sub->url(route('loan-management.late-customers'), 'Late Customers', ['icon' => 'fa fa-exclamation-circle']);
                $sub->url(route('loan-management.customers'), 'Customers', ['icon' => 'fa fa-users']);
                $sub->url(route('loan-management.reports'), 'Reports', ['icon' => 'fa fa-bar-chart']);
                $sub->url(route('loan-management.settings'), 'Settings', ['icon' => 'fa fa-cogs']);
            }, [
                'icon' => 'fa fa-handshake-o',
                'active' => request()->segment(1) === 'loan-management',
            ]);

            $root->order((int) config('loanmanagement.menu_order', 38));
        });
    }
}

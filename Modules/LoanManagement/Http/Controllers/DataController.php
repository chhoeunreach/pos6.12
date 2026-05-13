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
            ['value' => 'loan_management.customers.view', 'label' => 'Customers (view)', 'default' => false],
            ['value' => 'loan_management.guarantors.view', 'label' => 'Guarantors (view)', 'default' => false],
            ['value' => 'loan_management.blacklist.view', 'label' => 'Blacklist (view)', 'default' => false],
            ['value' => 'loan_management.loans.view', 'label' => 'Loans (view)', 'default' => false],
            ['value' => 'loan_management.overdue.view', 'label' => 'Overdue (view)', 'default' => false],
            ['value' => 'loan_management.monthly_payments.view', 'label' => 'Monthly Payments (view)', 'default' => false],
            ['value' => 'loan_management.gps.view', 'label' => 'GPS (view)', 'default' => false],
            ['value' => 'loan_management.chat.view', 'label' => 'Chat (view)', 'default' => false],
            ['value' => 'loan_management.reports.view', 'label' => 'Reports (view)', 'default' => false],
            ['value' => 'loan_management.aba.view', 'label' => 'ABA (view)', 'default' => false],
            ['value' => 'loan_management.import.view', 'label' => 'Import (view)', 'default' => false],
            ['value' => 'loan_management.settings.view', 'label' => 'Settings (view)', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->check() || ! auth()->user()->can('loan_management.view')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $root = $menu->dropdown('Installment', function ($sub) {
                $sub->url(route('loan-management.dashboard'), 'Dashboard', ['icon' => 'fa fa-dashboard']);

                $sub->url('javascript:void(0)', 'Customers', ['icon' => 'fa fa-users']);
                $sub->url(route('loan-management.customers.index'), '- Customers', ['icon' => 'fa fa-user']);
                $sub->url(route('loan-management.guarantors.index'), '- Guarantors', ['icon' => 'fa fa-handshake-o']);
                $sub->url(route('loan-management.blacklist.index'), '- Blacklist', ['icon' => 'fa fa-ban']);
                $sub->url(route('loan-management.customers.clone-from-pos'), '- Clone From POS (recommended)', ['icon' => 'fa fa-copy']);

                $sub->url('javascript:void(0)', 'Loans', ['icon' => 'fa fa-credit-card']);
                $sub->url(route('loan-management.loans.index'), '- Installment', ['icon' => 'fa fa-money']);
                $sub->url(route('loan-management.overdue.index'), '- Overdue / Late Payments', ['icon' => 'fa fa-exclamation-triangle']);
                $sub->url(route('loan-management.loans.create-from-sell'), '- Create From Sell (recommended)', ['icon' => 'fa fa-plus-circle']);

                $sub->url('javascript:void(0)', 'Collections', ['icon' => 'fa fa-map-marker']);
                $sub->url(route('loan-management.monthly-payments.index'), '- Monthly Payments', ['icon' => 'fa fa-calendar-check-o']);
                $sub->url(route('loan-management.gps.index'), '- GPS Tracking', ['icon' => 'fa fa-map']);
                $sub->url(route('loan-management.chat.index'), '- Live Chat', ['icon' => 'fa fa-comments']);
                $sub->url(route('loan-management.collection-visits.index'), '- Collection Visits (recommended)', ['icon' => 'fa fa-street-view']);

                $sub->url('javascript:void(0)', 'Reports', ['icon' => 'fa fa-bar-chart']);
                $sub->url(route('loan-management.reports.payments'), '- Payments Report', ['icon' => 'fa fa-line-chart']);
                $sub->url(route('loan-management.reports.index'), '- Loan Summary Report (recommended)', ['icon' => 'fa fa-list']);
                $sub->url(route('loan-management.aba.index'), '- ABA Transactions Report (recommended)', ['icon' => 'fa fa-qrcode']);

                $sub->url('javascript:void(0)', 'Tools', ['icon' => 'fa fa-cogs']);
                $sub->url(route('loan-management.tools.monthly-import-export'), '- Monthly Payments Import/Export', ['icon' => 'fa fa-exchange']);
                $sub->url(route('loan-management.tools.loan-import-export'), '- Loan Import/Export', ['icon' => 'fa fa-upload']);
                $sub->url(route('loan-management.tools.send-notification'), '- Send Notification', ['icon' => 'fa fa-bell']);

                $sub->url('javascript:void(0)', 'Settings', ['icon' => 'fa fa-wrench']);
                $sub->url(route('loan-management.settings.index'), '- General Settings', ['icon' => 'fa fa-cog']);
                $sub->url(route('loan-management.settings.payment-methods'), '- Payment Methods', ['icon' => 'fa fa-credit-card']);
                $sub->url(route('loan-management.settings.currencies'), '- Currencies', ['icon' => 'fa fa-money']);
            }, [
                'icon' => 'fa fa-handshake-o',
                'active' => request()->segment(1) === 'loan-management',
            ]);

            $root->order((int) config('loanmanagement.menu_order', 38));
        });
    }
}

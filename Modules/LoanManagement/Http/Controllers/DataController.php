<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;

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
            ['value' => 'loan_management.chat.delete', 'label' => 'Chat (delete empty)', 'default' => false],
            ['value' => 'loan_management.reports.view', 'label' => 'Reports (view)', 'default' => false],
            ['value' => 'loan_management.aba.view', 'label' => 'ABA (view)', 'default' => false],
            ['value' => 'loan_management.import.view', 'label' => 'Import (view)', 'default' => false],
            ['value' => 'loan_management.settings.view', 'label' => 'Settings (view)', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        return;
    }
}

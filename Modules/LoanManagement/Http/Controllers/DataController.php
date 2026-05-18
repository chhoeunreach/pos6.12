<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

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

    public function get_additional_script(): array
    {
        if (! auth()->check()) {
            return [];
        }

        if (! auth()->user()->can('loan_management.view')) {
            return [];
        }

        if (! Route::has('loan-management.dashboard')) {
            return [];
        }

        $url = route('loan-management.dashboard');
        $posButton = '<a href="'.$url.'" id="loan_management_pos_header_link" title="Loan Management" class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white hover:tw-bg-white/60 tw-cursor-pointer tw-border-2 tw-w-auto tw-h-auto tw-py-1 tw-px-4 tw-rounded-md pull-right"><strong><i class="fa fa-credit-card tw-text-[#646EE4] !tw-text-sm"></i> &nbsp;Loan Management</strong></a>';

        return [
            'additional_js' => '<script>
                $(function () {
                    if ($("#pos_header_more_options").length && !$("#loan_management_pos_header_link").length) {
                        $("#pos_header_more_options").prepend('.json_encode($posButton).');
                    }
                });
            </script>',
        ];
    }
}

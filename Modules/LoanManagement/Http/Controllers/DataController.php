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
            ['value' => 'loan_management.view', 'label' => 'Loan Management - Full View', 'default' => false],
            ['value' => 'loan_management.dashboard.view', 'label' => 'Dashboard - View', 'default' => false],
            ['value' => 'loan_management.create', 'label' => 'General - Create', 'default' => false],
            ['value' => 'loan_management.edit', 'label' => 'General - Edit', 'default' => false],
            ['value' => 'loan_management.delete', 'label' => 'General - Delete', 'default' => false],
            ['value' => 'loan_management.approve', 'label' => 'General - Approve/Status', 'default' => false],

            ['value' => 'loan_management.loans.view', 'label' => 'Loans - View', 'default' => false],
            ['value' => 'loan_management.loans.create', 'label' => 'Loans - Create Loan', 'default' => false],
            ['value' => 'loan_management.create_from_sell', 'label' => 'Loans - Create From Sell', 'default' => false],
            ['value' => 'loan_management.loans.edit', 'label' => 'Loans - Edit', 'default' => false],
            ['value' => 'loan_management.loans.approve', 'label' => 'Loans - Approve', 'default' => false],
            ['value' => 'loan_management.loans.reject', 'label' => 'Loans - Reject', 'default' => false],
            ['value' => 'loan_management.sell_list', 'label' => 'Sell List - View', 'default' => false],
            ['value' => 'loan_management.sell_view', 'label' => 'Sell List - View Sale Details', 'default' => false],
            ['value' => 'loan_management.sell_convert', 'label' => 'Sell List - Convert To Loan', 'default' => false],

            ['value' => 'loan_management.customers.view', 'label' => 'Customers - View', 'default' => false],
            ['value' => 'loan_management.customers.create', 'label' => 'Customers - Create', 'default' => false],
            ['value' => 'loan_management.customers.edit', 'label' => 'Customers - Edit', 'default' => false],
            ['value' => 'loan_management.customers.delete', 'label' => 'Customers - Delete', 'default' => false],
            ['value' => 'loan_management.guarantors.view', 'label' => 'Guarantors - View', 'default' => false],
            ['value' => 'loan_management.blacklist.view', 'label' => 'Blacklist - View', 'default' => false],
            ['value' => 'loan_management.customer_gps.manage', 'label' => 'Customer GPS - Manage', 'default' => false],

            ['value' => 'loan_management.schedules.view', 'label' => 'Schedules - View', 'default' => false],
            ['value' => 'loan_management.monthly_payments.view', 'label' => 'Monthly Payments - View', 'default' => false],
            ['value' => 'loan_management.overdue.view', 'label' => 'Overdue - View', 'default' => false],
            ['value' => 'loan_management.payments.view', 'label' => 'Payments - View', 'default' => false],
            ['value' => 'loan_management.payments.create', 'label' => 'Payments - Create/Collect', 'default' => false],
            ['value' => 'loan_management.payment', 'label' => 'Payments - Manage', 'default' => false],
            ['value' => 'loan_management.payment_history.view', 'label' => 'Payment History - View', 'default' => false],

            ['value' => 'loan_management.collection.view', 'label' => 'Collection - View Cases', 'default' => false],
            ['value' => 'loan_management.collection.assign', 'label' => 'Collection - Assign Collector', 'default' => false],
            ['value' => 'loan_management.collection.recovery', 'label' => 'Collection - Recovery Management', 'default' => false],
            ['value' => 'loan_management.collection.legal', 'label' => 'Collection - Legal Cases', 'default' => false],
            ['value' => 'loan_management.collection.repossess', 'label' => 'Collection - Repossessions', 'default' => false],
            ['value' => 'loan_management.collection.writeoff', 'label' => 'Collection - Write Off', 'default' => false],
            ['value' => 'loan_management.collection_visits.view', 'label' => 'Collection Visits - View', 'default' => false],

            ['value' => 'loan_management.gps.view', 'label' => 'GPS Tracking - View', 'default' => false],
            ['value' => 'loan_management.chat.view', 'label' => 'Live Chat - View', 'default' => false],
            ['value' => 'loan_management.chat.reply', 'label' => 'Live Chat - Reply', 'default' => false],
            ['value' => 'loan_management.chat.assign', 'label' => 'Live Chat - Assign', 'default' => false],
            ['value' => 'loan_management.chat.close', 'label' => 'Live Chat - Close', 'default' => false],
            ['value' => 'loan_management.chat.delete', 'label' => 'Live Chat - Delete Empty', 'default' => false],
            ['value' => 'loan_management.chat.admin', 'label' => 'Live Chat - Admin', 'default' => false],

            ['value' => 'loan_management.aba.view', 'label' => 'ABA Transactions - View', 'default' => false],
            ['value' => 'loan_management.reports.view', 'label' => 'Reports - View', 'default' => false],
            ['value' => 'loan_management.report', 'label' => 'Reports - Manage', 'default' => false],
            ['value' => 'loan_management.import.view', 'label' => 'Import Excel - View', 'default' => false],
            ['value' => 'loan_management.settings.view', 'label' => 'Settings - View', 'default' => false],
            ['value' => 'loan_management.setting', 'label' => 'Settings - Manage', 'default' => false],
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

        $canOpenLoan = auth()->user()->can('loan_management.view');
        $canManageRoles = auth()->user()->can('roles.create') || auth()->user()->can('roles.update');

        if (! $canOpenLoan && ! $canManageRoles) {
            return [];
        }

        $scripts = [];

        if ($canOpenLoan && Route::has('loan-management.dashboard')) {
            $url = route('loan-management.dashboard');
            $posButton = '<a href="'.$url.'" id="loan_management_pos_header_link" title="Loan Management" class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white hover:tw-bg-white/60 tw-cursor-pointer tw-border-2 tw-w-auto tw-h-auto tw-py-1 tw-px-4 tw-rounded-md pull-right"><strong><i class="fa fa-credit-card tw-text-[#646EE4] !tw-text-sm"></i> &nbsp;Loan Management</strong></a>';

            $scripts[] = '
                if ($("#pos_header_more_options").length && !$("#loan_management_pos_header_link").length) {
                    $("#pos_header_more_options").prepend('.json_encode($posButton).');
                }
            ';
        }

        if ($canManageRoles) {
            $scripts[] = '
                $(".check_group").each(function () {
                    var group = $(this);
                    var title = $.trim(group.find("h4:first").text());

                    if (title !== "LoanManagement" || group.find(".lm-role-select-all").length) {
                        return;
                    }

                    var buttons = $(
                        "<div class=\"lm-role-select-all\" style=\"margin-top:8px;\">" +
                            "<button type=\"button\" class=\"btn btn-xs btn-primary lm-check-all\">Select all</button> " +
                            "<button type=\"button\" class=\"btn btn-xs btn-default lm-uncheck-all\">Unselect all</button>" +
                        "</div>"
                    );

                    group.find(".col-md-3:first").append(buttons);

                    function setLoanPermissions(checked) {
                        group.find("input[type=checkbox][name=\"permissions[]\"]").each(function () {
                            var input = $(this);
                            if (input.prop("disabled")) {
                                return;
                            }

                            if ($.fn.iCheck) {
                                input.iCheck(checked ? "check" : "uncheck");
                            } else {
                                input.prop("checked", checked).trigger("change");
                            }
                        });
                    }

                    buttons.on("click", ".lm-check-all", function () {
                        setLoanPermissions(true);
                    });

                    buttons.on("click", ".lm-uncheck-all", function () {
                        setLoanPermissions(false);
                    });
                });
            ';
        }

        return [
            'additional_js' => '<script>$(function () {'.implode("\n", $scripts).'});</script>',
        ];
    }
}

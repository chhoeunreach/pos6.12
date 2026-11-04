<?php

namespace Modules\DailyFinancialManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'daily_financial.dashboard.view',
                'label' => 'Daily Financial - View Dashboard',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.income.view',
                'label' => 'Daily Financial - View Income',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.income.create',
                'label' => 'Daily Financial - Create Income',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.expense.view',
                'label' => 'Daily Financial - View Expenses',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.expense.create',
                'label' => 'Daily Financial - Create Expenses',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.approvals.review',
                'label' => 'Daily Financial - Review Approvals',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.approvals.approve',
                'label' => 'Daily Financial - Approve Financial Records',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.closing.view',
                'label' => 'Daily Financial - View Daily Closings',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.closing.create',
                'label' => 'Daily Financial - Execute Daily Closing',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.reconciliation.view',
                'label' => 'Daily Financial - View Cash Reconciliations',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.reports.view',
                'label' => 'Daily Financial - View Reports',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.reports.export',
                'label' => 'Daily Financial - Export Reports (Excel/PDF)',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.sync.manage',
                'label' => 'Daily Financial - Manage POS/Loan Sync',
                'default' => false,
            ],
            [
                'value' => 'daily_financial.audit.view',
                'label' => 'Daily Financial - View Audit Logs',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();
        $businessId = session('business.id');
        $isAdmin = $user->hasRole('Admin#' . $businessId);

        $canView = $isAdmin
            || $user->can('daily_financial.dashboard.view')
            || $user->can('daily_financial.reports.view')
            || $user->can('daily_financial.income.view')
            || $user->can('daily_financial.expense.view')
            || $user->can('daily_financial.closing.view');

        if (! $canView) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($isAdmin, $user) {
            $menu->dropdown(
                __('dailyfinancialmanagement::lang.daily_financial_management'),
                function ($sub) use ($isAdmin, $user) {
                    // Dashboard
                    $sub->url(
                        route('daily-financial.dashboard'),
                        __('dailyfinancialmanagement::lang.dashboard'),
                        ['icon' => 'fa fa-tachometer', 'active' => request()->segment(1) == 'daily-financial' && request()->segment(2) == '']
                    );

                    // Income Management
                    if ($isAdmin || $user->can('daily_financial.income.view')) {
                        $sub->url(
                            route('daily-financial.income.index'),
                            __('dailyfinancialmanagement::lang.income_management'),
                            ['icon' => 'fa fa-arrow-circle-down', 'active' => request()->segment(2) == 'income']
                        );
                    }

                    // Expense Management
                    if ($isAdmin || $user->can('daily_financial.expense.view')) {
                        $sub->url(
                            route('daily-financial.expense.index'),
                            __('dailyfinancialmanagement::lang.expense_management'),
                            ['icon' => 'fa fa-arrow-circle-up', 'active' => request()->segment(2) == 'expense']
                        );
                    }

                    // Cash & Bank Accounts
                    if ($isAdmin || $user->can('daily_financial.account.manage')) {
                        $sub->url(
                            route('daily-financial.accounts.index'),
                            __('dailyfinancialmanagement::lang.cash_bank_accounts'),
                            ['icon' => 'fa fa-university', 'active' => request()->segment(2) == 'accounts']
                        );
                    }

                    // Approval Workflow
                    if ($isAdmin || $user->can('daily_financial.approvals.review') || $user->can('daily_financial.approvals.approve')) {
                        $sub->url(
                            route('daily-financial.approvals.index'),
                            __('dailyfinancialmanagement::lang.approvals'),
                            ['icon' => 'fa fa-check-square-o', 'active' => request()->segment(2) == 'approvals']
                        );
                    }

                    // Daily Closing
                    if ($isAdmin || $user->can('daily_financial.closing.view')) {
                        $sub->url(
                            route('daily-financial.closing.index'),
                            __('dailyfinancialmanagement::lang.daily_closing'),
                            ['icon' => 'fa fa-calendar-check-o', 'active' => request()->segment(2) == 'closing']
                        );
                    }

                    // Cash Reconciliation
                    if ($isAdmin || $user->can('daily_financial.reconciliation.view')) {
                        $sub->url(
                            route('daily-financial.reconciliation.index'),
                            __('dailyfinancialmanagement::lang.cash_reconciliation'),
                            ['icon' => 'fa fa-balance-scale', 'active' => request()->segment(2) == 'reconciliation']
                        );
                    }

                    // Financial Reports Dropdown or links
                    if ($isAdmin || $user->can('daily_financial.reports.view')) {
                        $sub->url(
                            route('daily-financial.reports.executive'),
                            __('dailyfinancialmanagement::lang.executive_report'),
                            ['icon' => 'fa fa-file-text', 'active' => request()->segment(2) == 'reports' && request()->segment(3) == 'executive']
                        );
                        $sub->url(
                            route('daily-financial.reports.profit-loss'),
                            __('dailyfinancialmanagement::lang.profit_and_loss'),
                            ['icon' => 'fa fa-line-chart', 'active' => request()->segment(2) == 'reports' && request()->segment(3) == 'profit-loss']
                        );
                        $sub->url(
                            route('daily-financial.reports.cash-flow'),
                            __('dailyfinancialmanagement::lang.cash_flow'),
                            ['icon' => 'fa fa-exchange', 'active' => request()->segment(2) == 'reports' && request()->segment(3) == 'cash-flow']
                        );
                    }

                    // Categories
                    if ($isAdmin || $user->can('daily_financial.category.manage')) {
                        $sub->url(
                            route('daily-financial.categories.index'),
                            __('dailyfinancialmanagement::lang.financial_categories'),
                            ['icon' => 'fa fa-tags', 'active' => request()->segment(2) == 'categories']
                        );
                    }

                    // POS & Installment Sync
                    if ($isAdmin || $user->can('daily_financial.sync.manage')) {
                        $sub->url(
                            route('daily-financial.sync.index'),
                            __('dailyfinancialmanagement::lang.sync_pos_data'),
                            ['icon' => 'fa fa-refresh', 'active' => request()->segment(2) == 'sync']
                        );
                    }

                    // Audit Logs
                    if ($isAdmin || $user->can('daily_financial.audit.view')) {
                        $sub->url(
                            route('daily-financial.audit.index'),
                            __('dailyfinancialmanagement::lang.audit_logs'),
                            ['icon' => 'fa fa-history', 'active' => request()->segment(2) == 'audit-logs']
                        );
                    }
                },
                [
                    'icon' => '<svg class="tw-size-5 tw-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 9m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" /><path d="M14 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2" /></svg>',
                    'active' => request()->segment(1) == 'daily-financial',
                ]
            )->order(25);
        });
    }
}


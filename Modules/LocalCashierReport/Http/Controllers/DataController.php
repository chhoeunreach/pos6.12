<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\LocalCashierReport\Support\ReportLanguage;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'local_cashier_report.view',
                'label' => ReportLanguage::text('permission_view'),
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        $canViewFinancial = auth()->user()->can('profit_loss_report.view');
        $canViewLocalCashier = auth()->user()->can('local_cashier_report.view');
        $canViewExpense = auth()->user()->can('expense_report.view') || $canViewLocalCashier;

        if (! $canViewLocalCashier && ! $canViewExpense && ! $canViewFinancial) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($canViewLocalCashier, $canViewExpense, $canViewFinancial) {
            $reports = $menu->whereTitle(__('report.reports'));

            $isLocalCashierActive = request()->segment(1) === 'local-cashier-report' && request()->segment(2) !== 'expenses-list';
            $isExpensesListActive = (request()->segment(1) === 'local-cashier-report' && request()->segment(2) === 'expenses-list')
                || (request()->segment(1) === 'reports' && (request()->segment(2) === 'expenses-list' || request()->segment(2) === 'cashier-expenses-list'));

            if (! empty($reports)) {
                if ($canViewFinancial) {
                    $reports->url(
                        route('reports.financial-dashboard'),
                        ReportLanguage::text('financial_dashboard'),
                        ['icon' => 'fa fa-line-chart', 'active' => request()->is('reports/financial-dashboard')]
                    )->order(998);
                }

                if ($canViewLocalCashier) {
                    $reports->url(
                        route('local-cashier-report.index'),
                        ReportLanguage::text('local_cashier_report'),
                        ['icon' => '', 'active' => $isLocalCashierActive]
                    )->order(999);
                }

                if ($canViewExpense) {
                    $reports->url(
                        route('local-cashier-report.expenses-list'),
                        ReportLanguage::text('expenses_list'),
                        ['icon' => '', 'active' => $isExpensesListActive]
                    )->order(1000);
                }

                return;
            }

            if ($canViewLocalCashier) {
                $menu->url(
                    route('local-cashier-report.index'),
                    ReportLanguage::text('local_cashier_report'),
                    ['icon' => 'fa fa-file-text-o', 'active' => $isLocalCashierActive]
                )->order(999);
            }

            if ($canViewExpense) {
                $menu->url(
                    route('local-cashier-report.expenses-list'),
                    ReportLanguage::text('expenses_list'),
                    ['icon' => 'fa fa-money', 'active' => $isExpensesListActive]
                )->order(1000);
            }
        });
    }
}

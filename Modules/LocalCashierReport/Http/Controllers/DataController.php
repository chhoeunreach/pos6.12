<?php

namespace Modules\LocalCashierReport\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function user_permissions(): array
    {
        return [
            [
                'value' => 'local_cashier_report.view',
                'label' => 'Local Cashier Report (view)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        $canViewLocalCashier = auth()->user()->can('local_cashier_report.view');
        $canViewExpense = auth()->user()->can('expense_report.view') || $canViewLocalCashier;

        if (! $canViewLocalCashier && ! $canViewExpense) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($canViewLocalCashier, $canViewExpense) {
            $reports = $menu->whereTitle(__('report.reports'));

            $isLocalCashierActive = request()->segment(1) === 'local-cashier-report' && request()->segment(2) !== 'expenses-list';
            $isExpensesListActive = (request()->segment(1) === 'local-cashier-report' && request()->segment(2) === 'expenses-list')
                || (request()->segment(1) === 'reports' && (request()->segment(2) === 'expenses-list' || request()->segment(2) === 'cashier-expenses-list'));

            if (! empty($reports)) {
                if ($canViewLocalCashier) {
                    $reports->url(
                        route('local-cashier-report.index'),
                        'Local Cashier Report',
                        ['icon' => '', 'active' => $isLocalCashierActive]
                    )->order(999);
                }

                if ($canViewExpense) {
                    $reports->url(
                        route('local-cashier-report.expenses-list'),
                        'Expenses list',
                        ['icon' => '', 'active' => $isExpensesListActive]
                    )->order(1000);
                }

                return;
            }

            if ($canViewLocalCashier) {
                $menu->url(
                    route('local-cashier-report.index'),
                    'Local Cashier Report',
                    ['icon' => 'fa fa-file-text-o', 'active' => $isLocalCashierActive]
                )->order(999);
            }

            if ($canViewExpense) {
                $menu->url(
                    route('local-cashier-report.expenses-list'),
                    'Expenses list',
                    ['icon' => 'fa fa-money', 'active' => $isExpensesListActive]
                )->order(1000);
            }
        });
    }
}

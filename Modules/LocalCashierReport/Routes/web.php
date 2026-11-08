<?php

use Illuminate\Support\Facades\Route;
use Modules\LocalCashierReport\Http\Controllers\ExpenseListReportController;
use Modules\LocalCashierReport\Http\Controllers\FinancialDashboardController;
use Modules\LocalCashierReport\Http\Controllers\FinancialExpenseController;
use Modules\LocalCashierReport\Http\Controllers\FinancialSalesController;
use Modules\LocalCashierReport\Http\Controllers\FinancialLotHistoryController;
use Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseCostController;
use Modules\LocalCashierReport\Http\Controllers\FinancialPurchaseLineController;
use Modules\LocalCashierReport\Http\Controllers\InstallController;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;

Route::middleware(['web', 'auth'])->prefix('local-cashier-report')->group(function () {
    Route::get('/install', [InstallController::class, 'index']);
    Route::post('/install', [InstallController::class, 'install']);
    Route::get('/install/uninstall', [InstallController::class, 'uninstall']);
    Route::get('/install/update', [InstallController::class, 'update']);
});

Route::middleware([
    'web',
    'auth',
    'SetSessionData',
    'language',
    'timezone',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->group(function () {
    Route::get('/reports/financial-dashboard/purchase-price/{sourceName}/{line}', [FinancialPurchaseLineController::class, 'show'])->where(['sourceName' => 'main|accessory|service', 'line' => '[0-9]+'])->name('reports.financial-purchase-line.show');
    Route::get('/reports/financial-dashboard/purchase-prices/{sourceName}/{purchase}', [FinancialPurchaseLineController::class, 'index'])->where(['sourceName' => 'main|accessory|service', 'purchase' => '[0-9]+'])->name('reports.financial-purchase-line.index');
    Route::put('/reports/financial-dashboard/purchase-price/{sourceName}/{line}', [FinancialPurchaseLineController::class, 'update'])->where(['sourceName' => 'main|accessory|service', 'line' => '[0-9]+'])->name('reports.financial-purchase-line.update');

    Route::prefix('local-cashier-report')->group(function () {
        Route::get('/', [LocalCashierReportController::class, 'index'])->name('local-cashier-report.index');
        Route::get('/export', [LocalCashierReportController::class, 'export'])->name('local-cashier-report.export');
        Route::get('/print', [LocalCashierReportController::class, 'print'])->name('local-cashier-report.print');
        Route::get('/expenses-list', [ExpenseListReportController::class, 'index'])->name('local-cashier-report.expenses-list');
    });

    Route::get('/reports/financial-dashboard/cost-sales/{sourceName}/{line}', [FinancialPurchaseCostController::class, 'edit'])->whereNumber('line')->name('reports.financial-purchase-cost.edit');
    Route::put('/reports/financial-dashboard/cost-sales/{sourceName}/{line}', [FinancialPurchaseCostController::class, 'update'])->whereNumber('line')->name('reports.financial-purchase-cost.update');
    Route::get('/reports/financial-dashboard/cost-sales', [FinancialSalesController::class, 'costs'])->name('reports.financial-cost-sales.index');
    Route::get('/reports/financial-dashboard/revenue-sales', [FinancialSalesController::class, 'revenues'])->name('reports.financial-revenue-sales.index');
    Route::get('/reports/financial-dashboard/lot-history/{sourceName}/{variation}/{lot}', [FinancialLotHistoryController::class, 'show'])->whereNumber('variation')->where('lot', '.*')->name('reports.financial-lot-history.show');
    Route::get('/reports/financial-dashboard/sales', [FinancialSalesController::class, 'index'])->name('reports.financial-sales.index');

    Route::get('/reports/financial-dashboard/expenses', [FinancialExpenseController::class, 'index'])->name('reports.financial-expenses.index');
    Route::get('/reports/financial-dashboard/expenses/{source}/{id}', [FinancialExpenseController::class, 'edit'])->whereNumber('id')->name('reports.financial-expenses.edit');
    Route::put('/reports/financial-dashboard/expenses/{source}/{id}', [FinancialExpenseController::class, 'update'])->whereNumber('id')->name('reports.financial-expenses.update');

    Route::get('/reports/financial-dashboard', [FinancialDashboardController::class, 'index'])->name('reports.financial-dashboard');

    Route::get('/reports/cashier-expenses-list', [ExpenseListReportController::class, 'index']);
    Route::get('/reports/expenses-list', [ExpenseListReportController::class, 'index'])->name('reports.expenses-list');
});


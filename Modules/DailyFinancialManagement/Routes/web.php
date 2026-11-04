<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'daily-financial'], function () {
    Route::get('/', 'DashboardController@index')->name('daily-financial.dashboard');
    Route::get('/dashboard/ajax-metrics', 'DashboardController@ajaxMetrics')->name('daily-financial.dashboard.ajax');

    // Income Management
    Route::resource('income', 'IncomeController', ['as' => 'daily-financial']);

    // Expense Management
    Route::resource('expense', 'ExpenseController', ['as' => 'daily-financial']);

    // Financial Categories
    Route::resource('categories', 'CategoryController', ['as' => 'daily-financial']);

    // Cash and Bank Accounts
    Route::resource('accounts', 'AccountController', ['as' => 'daily-financial']);
    Route::post('accounts/transfer', 'AccountController@transfer')->name('daily-financial.accounts.transfer');
    Route::get('accounts/{id}/movements', 'AccountController@movements')->name('daily-financial.accounts.movements');

    // Approval Workflow
    Route::get('approvals', 'ApprovalController@index')->name('daily-financial.approvals.index');
    Route::post('approvals/{id}/action', 'ApprovalController@handleAction')->name('daily-financial.approvals.action');
    Route::get('approvals/settings', 'ApprovalController@settings')->name('daily-financial.approvals.settings');
    Route::post('approvals/settings', 'ApprovalController@updateSettings')->name('daily-financial.approvals.settings.update');

    // Daily Closing
    Route::get('closing', 'DailyClosingController@index')->name('daily-financial.closing.index');
    Route::get('closing/create', 'DailyClosingController@create')->name('daily-financial.closing.create');
    Route::post('closing', 'DailyClosingController@store')->name('daily-financial.closing.store');
    Route::get('closing/{id}', 'DailyClosingController@show')->name('daily-financial.closing.show');
    Route::post('closing/{id}/approve', 'DailyClosingController@approve')->name('daily-financial.closing.approve');
    Route::post('closing/{id}/lock', 'DailyClosingController@lockPeriod')->name('daily-financial.closing.lock');

    // Cash & Bank Reconciliation
    Route::get('reconciliation', 'ReconciliationController@index')->name('daily-financial.reconciliation.index');
    Route::post('reconciliation', 'ReconciliationController@store')->name('daily-financial.reconciliation.store');

    // Financial Reports
    Route::get('reports/executive', 'ReportController@executiveReport')->name('daily-financial.reports.executive');
    Route::get('reports/profit-loss', 'ReportController@profitLossReport')->name('daily-financial.reports.profit-loss');
    Route::get('reports/cash-flow', 'ReportController@cashFlowReport')->name('daily-financial.reports.cash-flow');
    Route::get('reports/income', 'ReportController@incomeReport')->name('daily-financial.reports.income');
    Route::get('reports/expense', 'ReportController@expenseReport')->name('daily-financial.reports.expense');
    Route::get('reports/branch-comparison', 'ReportController@branchComparisonReport')->name('daily-financial.reports.branch-comparison');
    Route::get('reports/department-summary', 'ReportController@departmentSummaryReport')->name('daily-financial.reports.department-summary');
    Route::get('reports/pending-transactions', 'ReportController@pendingReport')->name('daily-financial.reports.pending');
    Route::get('reports/monthly-summary', 'ReportController@monthlySummaryReport')->name('daily-financial.reports.monthly');

    // Export endpoints
    Route::get('reports/export/excel', 'ExportController@exportExcel')->name('daily-financial.reports.export.excel');
    Route::get('reports/export/pdf', 'ExportController@exportPdf')->name('daily-financial.reports.export.pdf');

    // POS & Loan Data Synchronization
    Route::get('sync', 'SyncController@index')->name('daily-financial.sync.index');
    Route::post('sync/trigger', 'SyncController@triggerSync')->name('daily-financial.sync.trigger');

    // Audit Logs
    Route::get('audit-logs', 'AuditLogController@index')->name('daily-financial.audit.index');
});


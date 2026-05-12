<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use Modules\LoanManagement\Http\Controllers\AdminCustomerTrackingController;
use Modules\LoanManagement\Http\Controllers\InstallController;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Modules\LoanManagement\Http\Controllers\LoanDashboardController;
use Modules\LoanManagement\Http\Controllers\LoanFromSellController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Http\Controllers\LoanSellListController;
use Modules\LoanManagement\Http\Controllers\SettingsController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('loan-management')
    ->group(function () {
        Route::get('/', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard.home')->middleware('can:loan_management.view');
        Route::get('/dashboard', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard')->middleware('can:loan_management.view');
        Route::get('/dashboard/data', [LoanDashboardController::class, 'data'])->name('loan-management.dashboard.data')->middleware('can:loan_management.view');

        Route::get('/sell-list', [LoanSellListController::class, 'index'])->name('loan-management.sell-list')->middleware('can:loan_management.sell_list');
        Route::get('/sell-list/{transaction}/view', [LoanSellListController::class, 'view'])->name('loan-management.sell-list.view')->middleware('can:loan_management.sell_view');
        Route::get('/sell-list/{transaction}/add-to-installment', [LoanSellListController::class, 'createFromSell'])->name('loan-management.sell-list.add')->middleware('can:loan_management.sell_convert');
        Route::post('/sell-list/{transaction}/add-to-installment', [LoanSellListController::class, 'storeFromSell'])->name('loan-management.sell-list.store')->middleware('can:loan_management.sell_convert');

        Route::get('/loans/create-from-sell', [LoanFromSellController::class, 'index'])->name('loan-management.loans.create-from-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/search-sells', [LoanFromSellController::class, 'search'])->name('loan-management.loans.search-sells')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/sell/{transaction_id}/clone', [LoanFromSellController::class, 'clone'])->name('loan-management.loans.clone-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/sell/{transaction_id}/check-duplicate', [LoanFromSellController::class, 'checkDuplicateLoan'])->name('loan-management.loans.check-duplicate')->middleware('can:loan_management.create_from_sell');
        Route::post('/loans/preview-schedule', [LoanFromSellController::class, 'previewSchedule'])->name('loan-management.loans.preview-schedule')->middleware('can:loan_management.create_from_sell');
        Route::post('/loans/store-from-sell', [LoanFromSellController::class, 'store'])->name('loan-management.loans.store-from-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/list-data', [LoanInstallmentListController::class, 'data'])->name('loan-management.loans.list-data')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/view', [LoanInstallmentListController::class, 'show'])->name('loan-management.loans.view')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/edit', [LoanInstallmentListController::class, 'edit'])->name('loan-management.loans.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/update', [LoanInstallmentListController::class, 'update'])->name('loan-management.loans.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/status', [LoanInstallmentListController::class, 'changeStatus'])->name('loan-management.loans.status')->middleware('can:loan_management.approve');
        Route::delete('/loans/{loan}', [LoanInstallmentListController::class, 'destroy'])->name('loan-management.loans.destroy')->middleware('can:loan_management.delete');

        Route::get('/customers', [LoanCustomerController::class, 'index'])->name('loan-management.customers')->middleware('can:loan_management.view');
        Route::get('/customers/create', [LoanCustomerController::class, 'create'])->name('loan-management.customers.create')->middleware('can:loan_management.create');
        Route::post('/customers', [LoanCustomerController::class, 'store'])->name('loan-management.customers.store')->middleware('can:loan_management.create');
        Route::get('/customers/clone-from-pos', [LoanCustomerController::class, 'cloneFromUltimatePos'])->name('loan-management.customers.clone-from-pos')->middleware('can:loan_management.create');
        Route::post('/customers/clone-from-pos', [LoanCustomerController::class, 'cloneFromUltimatePosStore'])->name('loan-management.customers.clone-from-pos.store')->middleware('can:loan_management.create');
        Route::get('/customers/search-main-contacts', [LoanCustomerController::class, 'searchMainContacts'])->name('loan-management.customers.search-main-contacts')->middleware('can:loan_management.create');
        Route::get('/customers/{customer}', [LoanCustomerController::class, 'show'])->name('loan-management.customers.show')->middleware('can:loan_management.view');
        Route::get('/customers/{customer}/edit', [LoanCustomerController::class, 'edit'])->name('loan-management.customers.edit')->middleware('can:loan_management.edit');
        Route::put('/customers/{customer}', [LoanCustomerController::class, 'update'])->name('loan-management.customers.update')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/blacklist', [LoanCustomerController::class, 'blacklist'])->name('loan-management.customers.blacklist')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/enable-login', [LoanCustomerController::class, 'enableLogin'])->name('loan-management.customers.enable-login')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/disable-login', [LoanCustomerController::class, 'disableLogin'])->name('loan-management.customers.disable-login')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/enable-gps', [LoanCustomerController::class, 'enableGpsTracking'])->name('loan-management.customers.enable-gps')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/disable-gps', [LoanCustomerController::class, 'disableGpsTracking'])->name('loan-management.customers.disable-gps')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/sync-main-contact', [LoanCustomerController::class, 'syncFromUltimatePos'])->name('loan-management.customers.sync-main-contact')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/reset-password', [LoanCustomerController::class, 'resetPassword'])->name('loan-management.customers.reset-password')->middleware('can:loan_management.edit');
        Route::delete('/customers/{customer}', [LoanCustomerController::class, 'destroy'])->name('loan-management.customers.destroy')->middleware('can:loan_management.delete');
        Route::get('/customer-tracking', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.customer-tracking')->middleware('can:loan_management.view');
        Route::get('/customer-tracking/data', [AdminCustomerTrackingController::class, 'data'])->name('loan-management.customer-tracking.data')->middleware('can:loan_management.view');
        Route::get('/customer-tracking/{customerId}/history', [AdminCustomerTrackingController::class, 'history'])->name('loan-management.customer-tracking.history')->middleware('can:loan_management.view');
        Route::post('/customer-tracking/{customerId}/toggle', [AdminCustomerTrackingController::class, 'toggle'])->name('loan-management.customer-tracking.toggle')->middleware('can:loan_management.customer_gps.manage');
        Route::get('/live-chat', [LoanChatController::class, 'webInbox'])->name('loan-management.live-chat')->middleware('can:loan_management.chat.view');
        Route::get('/live-chat/{thread}', [LoanChatController::class, 'webDetail'])->name('loan-management.live-chat.detail')->middleware('can:loan_management.chat.view');
        Route::get('/loans', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans')->middleware('can:loan_management.view');
        Route::get('/payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Payments')->name('loan-management.payments');
        Route::get('/late-customers', [DashboardController::class, 'placeholder'])->defaults('page', 'Late Customers')->name('loan-management.late-customers');
        Route::get('/reports', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports');
        Route::get('/settings', [SettingsController::class, 'invoicePrefix'])->name('loan-management.settings')->middleware('can:loan_management.view');
        Route::post('/settings/invoice-prefix', [SettingsController::class, 'updateInvoicePrefix'])->name('loan-management.settings.invoice-prefix')->middleware('can:loan_management.view');

        Route::get('/install', [InstallController::class, 'index'])->middleware('superadmin');
        Route::post('/install', [InstallController::class, 'install'])->middleware('superadmin');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->middleware('superadmin');
        Route::get('/install/update', [InstallController::class, 'update'])->middleware('superadmin');
    });

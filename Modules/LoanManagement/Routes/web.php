<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use Modules\LoanManagement\Http\Controllers\AdminCustomerTrackingController;
use Modules\LoanManagement\Http\Controllers\InstallController;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Modules\LoanManagement\Http\Controllers\LoanDashboardController;
use Modules\LoanManagement\Http\Controllers\LoanFromSellController;
use Modules\LoanManagement\Http\Controllers\LoanUltimatePosSellController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Http\Controllers\LoanLocationController;
use Modules\LoanManagement\Http\Controllers\LoanSellListController;
use Modules\LoanManagement\Http\Controllers\SettingsController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('loan-management')
    ->group(function () {
        Route::get('/', function () {
            return redirect()->route('loan-management.dashboard');
        })->name('loan-management.dashboard.home')->middleware('can:loan_management.view');
        Route::get('/dashboard', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard')->middleware('can:loan_management.view');
        Route::get('/dashboard/main', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard.index')->middleware('can:loan_management.view');
        Route::get('/dashboard/data', [LoanDashboardController::class, 'data'])->name('loan-management.dashboard.data')->middleware('can:loan_management.view');

        Route::get('/sell-list', function () {
            return redirect()->route('loan-management.loans.create-from-sell');
        })->name('loan-management.sell-list')->middleware('can:loan_management.create_from_sell');
        Route::get('/sell-list/{transaction}/view', [LoanSellListController::class, 'view'])->name('loan-management.sell-list.view')->middleware('can:loan_management.sell_view');
        Route::get('/sell-list/{transaction}/add-to-installment', [LoanSellListController::class, 'createFromSell'])->name('loan-management.sell-list.add')->middleware('can:loan_management.sell_convert');
        Route::post('/sell-list/{transaction}/add-to-installment', [LoanSellListController::class, 'storeFromSell'])->name('loan-management.sell-list.store')->middleware('can:loan_management.sell_convert');

        Route::get('/loans/create-from-sell', [LoanFromSellController::class, 'index'])->name('loan-management.loans.create-from-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/create', [LoanFromSellController::class, 'index'])->name('loan-management.loans.create')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/search-sales', [LoanFromSellController::class, 'searchSales'])->name('loan-management.loans.search-sales')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/search-sells', [LoanFromSellController::class, 'search'])->name('loan-management.loans.search-sells')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/sales/{transaction}/clone-data', [LoanFromSellController::class, 'cloneData'])->name('loan-management.loans.sales.clone-data')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/sell/{transaction_id}/clone', [LoanFromSellController::class, 'clone'])->name('loan-management.loans.clone-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/sell/{transaction_id}/check-duplicate', [LoanFromSellController::class, 'checkDuplicateLoan'])->name('loan-management.loans.check-duplicate')->middleware('can:loan_management.create_from_sell');
        Route::post('/loans/preview-schedule', [LoanFromSellController::class, 'previewSchedule'])->name('loan-management.loans.preview-schedule')->middleware('can:loan_management.create_from_sell');
        Route::post('/loans/store-from-sell', [LoanFromSellController::class, 'store'])->name('loan-management.loans.store-from-sell')->middleware('can:loan_management.create_from_sell');
        Route::post('/loans/add-sell', [LoanUltimatePosSellController::class, 'storeSell'])->name('loan-management.loans.add-sell')->middleware('can:loan_management.create_from_sell');
        Route::get('/ajax/customers/search', [LoanUltimatePosSellController::class, 'searchCustomers'])->name('loan-management.ajax.customers.search')->middleware('can:loan_management.create_from_sell');
        Route::get('/ajax/products/search', [LoanUltimatePosSellController::class, 'searchProducts'])->name('loan-management.ajax.products.search')->middleware('can:loan_management.create_from_sell');
        Route::get('/ajax/imei/search', [LoanUltimatePosSellController::class, 'searchImei'])->name('loan-management.ajax.imei.search')->middleware('can:loan_management.create_from_sell');
        Route::get('/loans/list-data', [LoanInstallmentListController::class, 'data'])->name('loan-management.loans.list-data')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/print-modal', [LoanInstallmentListController::class, 'printModal'])->name('loan-management.loans.print-modal')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/print', [LoanInstallmentListController::class, 'print'])->name('loan-management.loans.print')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/payment/create', [LoanInstallmentListController::class, 'createPayment'])->name('loan-management.loans.payment.create')->middleware('can:loan_management.view');
        Route::post('/loans/{loan}/payment', [LoanInstallmentListController::class, 'storePayment'])->name('loan-management.loans.payment.store')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/view', [LoanInstallmentListController::class, 'show'])->name('loan-management.loans.view')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/edit', [LoanInstallmentListController::class, 'edit'])->name('loan-management.loans.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/update', [LoanInstallmentListController::class, 'update'])->name('loan-management.loans.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/status', [LoanInstallmentListController::class, 'changeStatus'])->name('loan-management.loans.status')->middleware('can:loan_management.approve');
        Route::delete('/loans/{loan}', [LoanInstallmentListController::class, 'destroy'])->name('loan-management.loans.destroy')->middleware('can:loan_management.delete');

        Route::get('/customers', [LoanCustomerController::class, 'index'])->name('loan-management.customers')->middleware('can:loan_management.view');
        Route::get('/customers/list', [LoanCustomerController::class, 'index'])->name('loan-management.customers.index')->middleware('can:loan_management.view');
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
        Route::delete('/chat/{thread}', [LoanChatController::class, 'destroy'])->name('loan-management.chat.destroy')->middleware('can:loan_management.chat.delete');
        Route::get('/loans', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans')->middleware('can:loan_management.view');
        Route::get('/loans/list', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans.index')->middleware('can:loan_management.view');
        Route::get('/schedules', [DashboardController::class, 'placeholder'])->defaults('page', 'Installment Schedules')->name('loan-management.schedules.index')->middleware('can:loan_management.view');
        Route::get('/monthly-payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Monthly Payments')->name('loan-management.monthly-payments.index')->middleware('can:loan_management.view');
        Route::get('/overdue', [DashboardController::class, 'overdue'])->name('loan-management.overdue.index')->middleware('can:loan_management.view');
        Route::get('/locations', [LoanLocationController::class, 'index'])->name('loan-management.locations.index')->middleware('can:loan_management.view');
        Route::post('/locations/{location}/assets', [LoanLocationController::class, 'update'])->name('loan-management.locations.assets.update')->middleware('can:loan_management.view');
        Route::get('/location-assets/{location}/{filename}', [LoanLocationController::class, 'asset'])->name('loan-management.locations.assets.show')->middleware('can:loan_management.view');

        Route::get('/payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Payments')->name('loan-management.payments')->middleware('can:loan_management.view');
        Route::get('/payments/index', [DashboardController::class, 'placeholder'])->defaults('page', 'Payments')->name('loan-management.payments.index')->middleware('can:loan_management.view');
        Route::get('/payment-history', [DashboardController::class, 'placeholder'])->defaults('page', 'Payment History')->name('loan-management.payment-history.index')->middleware('can:loan_management.view');
        Route::get('/collection-visits', [DashboardController::class, 'placeholder'])->defaults('page', 'Collection Visits')->name('loan-management.collection-visits.index')->middleware('can:loan_management.view');
        Route::get('/gps', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.gps.index')->middleware('can:loan_management.view');
        Route::get('/chat', [LoanChatController::class, 'webInbox'])->name('loan-management.chat.index')->middleware('can:loan_management.chat.view');

        Route::get('/finance/aba-transactions', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba.index')->middleware('can:loan_management.view');
        Route::get('/aba', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba')->middleware('can:loan_management.view');
        Route::get('/reports', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports')->middleware('can:loan_management.view');
        Route::get('/reports/index', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports.index')->middleware('can:loan_management.view');
        Route::get('/reports/payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Payments Report')->name('loan-management.reports.payments')->middleware('can:loan_management.view');

        Route::get('/tools/import', [DashboardController::class, 'placeholder'])->defaults('page', 'Import Excel')->name('loan-management.import.index')->middleware('can:loan_management.view');
        Route::get('/import', [DashboardController::class, 'placeholder'])->defaults('page', 'Import Excel')->name('loan-management.import')->middleware('can:loan_management.view');
        Route::get('/tools/monthly-import-export', [DashboardController::class, 'placeholder'])->defaults('page', 'Monthly Payments Import/Export')->name('loan-management.tools.monthly-import-export')->middleware('can:loan_management.view');
        Route::get('/tools/loan-import-export', [DashboardController::class, 'placeholder'])->defaults('page', 'Loan Import/Export')->name('loan-management.tools.loan-import-export')->middleware('can:loan_management.view');
        Route::get('/tools/send-notification', [DashboardController::class, 'placeholder'])->defaults('page', 'Send Notification')->name('loan-management.tools.send-notification')->middleware('can:loan_management.view');
        Route::get('/settings', [SettingsController::class, 'invoicePrefix'])->name('loan-management.settings')->middleware('can:loan_management.view');
        Route::get('/settings/index', [SettingsController::class, 'invoicePrefix'])->name('loan-management.settings.index')->middleware('can:loan_management.view');
        Route::get('/settings/payment-methods', [SettingsController::class, 'paymentMethods'])->name('loan-management.settings.payment-methods')->middleware('can:loan_management.view');
        Route::post('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods'])->name('loan-management.settings.payment-methods.update')->middleware('can:loan_management.view');
        Route::get('/settings/currencies', [DashboardController::class, 'placeholder'])->defaults('page', 'Currencies')->name('loan-management.settings.currencies')->middleware('can:loan_management.view');
        Route::post('/settings/invoice-prefix', [SettingsController::class, 'updateInvoicePrefix'])->name('loan-management.settings.invoice-prefix')->middleware('can:loan_management.view');

        Route::get('/guarantors', [DashboardController::class, 'placeholder'])->defaults('page', 'Guarantors')->name('loan-management.guarantors.index')->middleware('can:loan_management.view');
        Route::get('/blacklist', [DashboardController::class, 'placeholder'])->defaults('page', 'Blacklist')->name('loan-management.blacklist.index')->middleware('can:loan_management.view');

        Route::get('/install', [InstallController::class, 'index'])->middleware('superadmin');
        Route::post('/install', [InstallController::class, 'install'])->middleware('superadmin');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->middleware('superadmin');
        Route::get('/install/update', [InstallController::class, 'update'])->middleware('superadmin');
    });

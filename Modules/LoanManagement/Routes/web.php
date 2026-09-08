<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\AdminCustomerTrackingController;
use Modules\LoanManagement\Http\Controllers\CambodiaAddressController;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use Modules\LoanManagement\Http\Controllers\LoanActivityLogController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\LoanCollectionController;
use Modules\LoanManagement\Http\Controllers\LoanCreateController;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Modules\LoanManagement\Http\Controllers\LoanDashboardController;
use Modules\LoanManagement\Http\Controllers\LoanFromSellController;
use Modules\LoanManagement\Http\Controllers\LoanImportExportController;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Http\Controllers\LoanLocationController;
use Modules\LoanManagement\Http\Controllers\LoanPaymentController;
use Modules\LoanManagement\Http\Controllers\LoanProductController;
use Modules\LoanManagement\Http\Controllers\LoanRoleController;
use Modules\LoanManagement\Http\Controllers\LoanTelegramChatController;
use Modules\LoanManagement\Http\Controllers\LoanTelegramWebhookController;
use Modules\LoanManagement\Http\Controllers\LoanUserController;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Modules\LoanManagement\Http\Controllers\SettingsController;
use Modules\LoanManagement\Http\Controllers\SystemHealthController;

Route::middleware(['web'])
    ->post('/webhook/loan-telegram', [LoanTelegramWebhookController::class, 'handle'])
    ->name('loan-management.telegram.webhook');

Route::middleware(['web'])
    ->get('/loan-management/settings/business/login-background', [SettingsController::class, 'businessLoginBackground'])
    ->name('loan-management.settings.business.login-background');

Route::middleware(['web'])
    ->get('/loan-management/settings/business/public-logo', [SettingsController::class, 'businessPublicLogo'])
    ->name('loan-management.settings.business.public-logo');

Route::middleware(['web'])->group(function () {
    Route::get('/', [PublicAppController::class, 'home'])->name('loan-management.public.home');
    Route::get('/register', [PublicAppController::class, 'register'])->name('loan-management.public.register');
    Route::post('/register', [PublicAppController::class, 'storeRegistration'])->name('loan-management.public.register.store');
    Route::get('/customer/login', [PublicAppController::class, 'customerLogin'])->name('loan-management.public.customer-login');
    Route::get('/loan-management/customer/login', fn () => redirect()->route('loan-management.public.customer-login'));
    Route::post('/customer/login', [PublicAppController::class, 'customerLoginStore'])->name('loan-management.public.customer-login.store');
    Route::match(['get', 'post'], '/customer/logout', [PublicAppController::class, 'customerLogout'])->name('loan-management.public.customer-logout');
    Route::match(['get', 'post'], '/logout', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

    Route::get('/customer/dashboard', [PublicAppController::class, 'customerDashboard'])->name('loan-management.public.customer-dashboard');
    Route::post('/customer/profile-photo', [PublicAppController::class, 'updateProfilePhoto'])->name('loan-management.public.customer-profile-photo');
    Route::get('/customer/loan-request', [PublicAppController::class, 'customerLoanRequest'])->name('loan-management.public.customer-loan-request');
    Route::post('/customer/loan-request', [PublicAppController::class, 'storeCustomerLoanRequest'])->name('loan-management.public.customer-loan-request.store');
    Route::post('/customer/loan-request/{id}/cancel', [PublicAppController::class, 'cancelCustomerLoanRequest'])->name('loan-management.public.customer-loan-request.cancel');
});

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'loan.activity'])
    ->prefix('loan-management')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('loan-management.dashboard'))->name('loan-management.dashboard.home');
        Route::get('/dashboard', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard');
        Route::get('/dashboard/main', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard.index');
        Route::get('/dashboard/data', [LoanDashboardController::class, 'data'])->name('loan-management.dashboard.data');
        Route::get('/dashboard/quick-search', [LoanDashboardController::class, 'quickSearch'])->name('loan-management.dashboard.quick-search');
        Route::match(['get', 'post'], '/language', [SettingsController::class, 'switchLanguage'])->name('loan-management.language.switch');

        Route::get('/admin-loan', [DashboardController::class, 'adminLoan'])->name('loan-management.admin-loan');
        Route::get('/admin-loan/export', [DashboardController::class, 'adminLoanExport'])->name('loan-management.admin-loan.export');
        Route::get('/admin-loan/details', [DashboardController::class, 'adminLoanDetails'])->name('loan-management.admin-loan.details');
        Route::post('/admin-loan/details/{loan}/update', [DashboardController::class, 'adminLoanInlineUpdate'])->where(['loan' => '[0-9]+'])->name('loan-management.admin-loan.details.update');

        Route::get('/operations/{page}', [LoanCollectionController::class, 'index'])->whereIn('page', ['new-loans', 'active-loans', 'due-today', 'today-collection', 'partial-payments', 'closed-accounts'])->name('loan-management.operations.page');
        Route::get('/collection/{page}', [LoanCollectionController::class, 'index'])->whereIn('page', ['overdue-accounts', 'promise-to-pay', 'broken-promise', 'field-visit-required', 'skip-customers', 'delinquent-accounts', 'recovery-management', 'debt-collection'])->name('loan-management.collection.page');
        Route::get('/risk/{page}', [LoanCollectionController::class, 'index'])->whereIn('page', ['high-risk-customers', 'fraud-risk', 'legal-cases', 'blacklisted-customers', 'repossessions'])->name('loan-management.risk.page');
        Route::get('/communication/{page}', [LoanCollectionController::class, 'index'])->whereIn('page', ['voice-calls', 'notifications', 'sms-telegram-logs'])->name('loan-management.communication.page');
        Route::get('/customers-workflow/{page}', [LoanCollectionController::class, 'index'])->whereIn('page', ['contact-history'])->name('loan-management.customer-workflow.page');
        Route::get('/collection-reports', [LoanCollectionController::class, 'reports'])->name('loan-management.collection.reports');
        Route::get('/collection-reports/{report}', [LoanCollectionController::class, 'report'])->name('loan-management.collection.report');

        Route::get('/loans/create-from-sell', fn () => redirect()->route('loan-management.loans.create'))->name('loan-management.loans.create-from-sell');
        Route::get('/loans/search-sales', [LoanFromSellController::class, 'searchSales'])->name('loan-management.loans.search-sales');
        Route::get('/loans/search-sells', [LoanFromSellController::class, 'search'])->name('loan-management.loans.search-sells');
        Route::get('/loans/sales/{transaction_id}/clone-data', [LoanFromSellController::class, 'cloneData'])->name('loan-management.loans.sales.clone-data');
        Route::get('/loans/sell/{transaction_id}/clone', [LoanFromSellController::class, 'clone'])->name('loan-management.loans.clone-sell');
        Route::get('/loans/sell/{transaction_id}/check-duplicate', [LoanFromSellController::class, 'checkDuplicateLoan'])->name('loan-management.loans.check-duplicate');
        Route::post('/loans/preview-schedule', [LoanFromSellController::class, 'previewSchedule'])->name('loan-management.loans.preview-schedule');
        Route::post('/loans/store-from-sell', fn () => abort(404))->name('loan-management.loans.store-from-sell');

        Route::get('/loans/create', [LoanCreateController::class, 'index'])->name('loan-management.loans.create');
        Route::get('/loans/create-standalone-modal', [LoanCreateController::class, 'modal'])->name('loan-management.loans.create-standalone-modal');
        Route::get('/loans/ajax/search-loan-customers', [LoanCreateController::class, 'searchCustomers'])->name('loan-management.loans.ajax.search-customers');
        Route::get('/loans/ajax/check-customer-duplicate', [LoanCreateController::class, 'checkCustomerDuplicate'])->name('loan-management.loans.ajax.check-customer-duplicate');
        Route::get('/loans/ajax/product-by-serial', [LoanCreateController::class, 'lookupProductBySerial'])->name('loan-management.loans.ajax.product-by-serial');
        Route::post('/loans/ajax/scan-id-card', [LoanCreateController::class, 'scanIdCard'])->name('loan-management.loans.ajax.scan-id-card');
        Route::post('/loans/ajax/scan-product-photo', [LoanCreateController::class, 'scanProductPhoto'])->name('loan-management.loans.ajax.scan-product-photo');
        Route::post('/loans/preview-standalone-schedule', [LoanCreateController::class, 'previewSchedule'])->name('loan-management.loans.preview-standalone-schedule');
        Route::post('/loans/store-standalone', [LoanCreateController::class, 'store'])->name('loan-management.loans.store-standalone');
        Route::get('/loans/calculator', [LoanCreateController::class, 'calculator'])->name('loan-management.loans.calculator');
        Route::get('/loans/calculator/print', [LoanCreateController::class, 'calculatorPrint'])->name('loan-management.loans.calculator.print');

        Route::get('/cambodia-address/sync', [CambodiaAddressController::class, 'sync'])->name('loan-management.cambodia-address.sync');
        Route::get('/cambodia-address/provinces', [CambodiaAddressController::class, 'provinces'])->name('loan-management.cambodia-address.provinces');
        Route::get('/cambodia-address/districts', [CambodiaAddressController::class, 'districts'])->name('loan-management.cambodia-address.districts');
        Route::get('/cambodia-address/communes', [CambodiaAddressController::class, 'communes'])->name('loan-management.cambodia-address.communes');
        Route::get('/cambodia-address/villages', [CambodiaAddressController::class, 'villages'])->name('loan-management.cambodia-address.villages');

        Route::get('/loans', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans');
        Route::get('/loans/list', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans.index');
        Route::get('/loans/list-data', [LoanInstallmentListController::class, 'data'])->name('loan-management.loans.list-data');
        Route::get('/loans/{loan}/view', [LoanInstallmentListController::class, 'show'])->name('loan-management.loans.view');
        Route::get('/loans/{loan}/sections/show', [LoanInstallmentListController::class, 'showSections'])->name('loan-management.loans.sections.show');
        Route::get('/loans/{loan}/edit', [LoanInstallmentListController::class, 'edit'])->name('loan-management.loans.edit');
        Route::get('/loans/{loan}/sections/edit', [LoanInstallmentListController::class, 'editSections'])->name('loan-management.loans.sections.edit');
        Route::put('/loans/{loan}', [LoanInstallmentListController::class, 'update'])->name('loan-management.loans.update');
        Route::delete('/loans/{loan}', [LoanInstallmentListController::class, 'destroy'])->name('loan-management.loans.destroy');
        Route::post('/loans/{loan}/status', [LoanInstallmentListController::class, 'changeStatus'])->name('loan-management.loans.status');
        Route::get('/loans/{loan}/print-modal', [LoanInstallmentListController::class, 'printModal'])->name('loan-management.loans.print-modal');
        Route::get('/loans/{loan}/print', [LoanInstallmentListController::class, 'print'])->name('loan-management.loans.print');
        Route::get('/loans/{loan}/convert-to-pos', [LoanInstallmentListController::class, 'convertToPos'])->name('loan-management.loans.convert-to-pos');
        Route::get('/loans/{loan}/payment/copy-info', [LoanInstallmentListController::class, 'paymentCopyInfo'])->name('loan-management.loans.payment.copy-info');
        Route::get('/loans/{loan}/payment/create', [LoanInstallmentListController::class, 'createPayment'])->name('loan-management.loans.payment.create');
        Route::get('/loans/{loan}/payment/quick-pay', [LoanInstallmentListController::class, 'mobileQuickPay'])->name('loan-management.loans.payment.quick-pay');
        Route::post('/loans/{loan}/payment', [LoanInstallmentListController::class, 'storePayment'])->name('loan-management.loans.payment.store');
        Route::get('/loans/{loan}/payments/collection-modal', [LoanPaymentController::class, 'collectionModal'])->name('loan-management.loans.payments.collection-modal');
        Route::post('/loans/{loan}/schedules/update-all', [LoanInstallmentListController::class, 'updateSchedulesFromEdit'])->name('loan-management.loans.schedules.update-all');
        Route::post('/loans/{loan}/schedules/refresh', [LoanInstallmentListController::class, 'refreshSchedules'])->name('loan-management.loans.schedules.refresh');
        Route::get('/loans/{loan}/schedules/{schedule}/edit', [LoanInstallmentListController::class, 'editSchedule'])->where(['loan' => '[0-9]+', 'schedule' => '[0-9]+'])->name('loan-management.loans.schedules.edit');
        Route::post('/loans/{loan}/schedules/{schedule}', [LoanInstallmentListController::class, 'updateSchedule'])->where(['loan' => '[0-9]+', 'schedule' => '[0-9]+'])->name('loan-management.loans.schedules.update');
        Route::delete('/loans/{loan}/schedules/{schedule}', [LoanInstallmentListController::class, 'destroySchedule'])->where(['loan' => '[0-9]+', 'schedule' => '[0-9]+'])->name('loan-management.loans.schedules.destroy');
        Route::get('/loans/{loan}/items/create', [LoanInstallmentListController::class, 'createItem'])->name('loan-management.loans.items.create');
        Route::post('/loans/{loan}/items', [LoanInstallmentListController::class, 'storeItem'])->name('loan-management.loans.items.store');
        Route::get('/loans/{loan}/items/{item}/edit', [LoanInstallmentListController::class, 'editItem'])->name('loan-management.loans.items.edit');
        Route::post('/loans/{loan}/items/{item}', [LoanInstallmentListController::class, 'updateItem'])->name('loan-management.loans.items.update');
        Route::delete('/loans/{loan}/items/{item}', [LoanInstallmentListController::class, 'destroyItem'])->name('loan-management.loans.items.destroy');
        Route::get('/loans/{loan}/workflow/edit', [LoanInstallmentListController::class, 'editWorkflow'])->name('loan-management.loans.workflow.edit');
        Route::post('/loans/{loan}/workflow', [LoanInstallmentListController::class, 'updateWorkflow'])->name('loan-management.loans.workflow.update');
        Route::post('/loans/{loan}/edit/scan-id-card', [LoanCreateController::class, 'scanIdCard'])->name('loan-management.loans.edit.scan-id-card');
        Route::post('/loans/{loan}/edit/scan-product-photo', [LoanCreateController::class, 'scanProductPhoto'])->name('loan-management.loans.edit.scan-product-photo');
        Route::get('/loans/{loan}/edit/cambodia-address/provinces', [CambodiaAddressController::class, 'provinces'])->name('loan-management.loans.edit.cambodia-address.provinces');
        Route::get('/loans/{loan}/edit/cambodia-address/districts', [CambodiaAddressController::class, 'districts'])->name('loan-management.loans.edit.cambodia-address.districts');
        Route::get('/loans/{loan}/edit/cambodia-address/communes', [CambodiaAddressController::class, 'communes'])->name('loan-management.loans.edit.cambodia-address.communes');
        Route::get('/loans/{loan}/edit/cambodia-address/villages', [CambodiaAddressController::class, 'villages'])->name('loan-management.loans.edit.cambodia-address.villages');

        Route::get('/customers', [LoanCustomerController::class, 'index'])->name('loan-management.customers');
        Route::get('/customers/index', [LoanCustomerController::class, 'index'])->name('loan-management.customers.index');
        Route::get('/customers/create', [LoanCustomerController::class, 'create'])->name('loan-management.customers.create');
        Route::post('/customers', [LoanCustomerController::class, 'store'])->name('loan-management.customers.store');
        Route::get('/customers/clone-from-pos', [LoanCustomerController::class, 'cloneFromUltimatePos'])->name('loan-management.customers.clone-from-pos');
        Route::post('/customers/clone-from-pos', [LoanCustomerController::class, 'cloneFromUltimatePosStore'])->name('loan-management.customers.clone-from-pos.store');
        Route::get('/customers/search-main-contacts', [LoanCustomerController::class, 'searchMainContacts'])->name('loan-management.customers.search-main-contacts');
        Route::get('/customers/{customer}', [LoanCustomerController::class, 'show'])->name('loan-management.customers.show');

        Route::get('/products', [LoanProductController::class, 'index'])->name('loan-management.products');
        Route::get('/products/index', [LoanProductController::class, 'index'])->name('loan-management.products.index');
        Route::get('/products/create', [LoanProductController::class, 'create'])->name('loan-management.products.create');
        Route::post('/products', [LoanProductController::class, 'store'])->name('loan-management.products.store');
        Route::get('/products/export-csv', [LoanProductController::class, 'exportCsv'])->name('loan-management.products.export-csv');
        Route::post('/products/bulk-action', [LoanProductController::class, 'bulkAction'])->name('loan-management.products.bulk-action');
        Route::get('/products/ajax-search', [LoanProductController::class, 'ajaxSearch'])->name('loan-management.products.ajax-search');
        Route::get('/products/{product}', [LoanProductController::class, 'show'])->name('loan-management.products.show');
        Route::get('/products/{product}/edit', [LoanProductController::class, 'edit'])->name('loan-management.products.edit');
        Route::put('/products/{product}', [LoanProductController::class, 'update'])->name('loan-management.products.update');
        Route::delete('/products/{product}', [LoanProductController::class, 'destroy'])->name('loan-management.products.destroy');
        Route::post('/products/{product}/stock-adjust', [LoanProductController::class, 'quickStockAdjust'])->name('loan-management.products.stock-adjust');
        Route::get('/products/{product}/calculator-data', [LoanProductController::class, 'calculatorData'])->name('loan-management.products.calculator-data');
        Route::get('/customers/{customer}/edit', [LoanCustomerController::class, 'edit'])->name('loan-management.customers.edit');
        Route::put('/customers/{customer}', [LoanCustomerController::class, 'update'])->name('loan-management.customers.update');
        Route::delete('/customers/{customer}', [LoanCustomerController::class, 'destroy'])->name('loan-management.customers.destroy');
        Route::post('/customers/{customer}/blacklist', [LoanCustomerController::class, 'blacklist'])->name('loan-management.customers.blacklist');
        Route::post('/customers/{customer}/login/enable', [LoanCustomerController::class, 'enableLogin'])->name('loan-management.customers.login.enable');
        Route::post('/customers/{customer}/login/disable', [LoanCustomerController::class, 'disableLogin'])->name('loan-management.customers.login.disable');
        Route::post('/customers/{customer}/reset-password', [LoanCustomerController::class, 'resetPassword'])->name('loan-management.customers.reset-password');
        Route::post('/customers/{customer}/gps/enable', [LoanCustomerController::class, 'enableGpsTracking'])->name('loan-management.customers.gps.enable');
        Route::post('/customers/{customer}/gps/disable', [LoanCustomerController::class, 'disableGpsTracking'])->name('loan-management.customers.gps.disable');
        Route::post('/customers/{customer}/telegram/link', [LoanCustomerController::class, 'generateTelegramLink'])->name('loan-management.customers.telegram.link');
        Route::post('/customers/{customer}/telegram/unlink', [LoanCustomerController::class, 'unlinkTelegram'])->name('loan-management.customers.telegram.unlink');
        Route::post('/customers/{customer}/sync-main-contact', [LoanCustomerController::class, 'syncFromUltimatePos'])->name('loan-management.customers.sync-main-contact');

        Route::get('/customer-tracking', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.customer-tracking');
        Route::get('/customer-tracking/data', [AdminCustomerTrackingController::class, 'data'])->name('loan-management.customer-tracking.data');
        Route::get('/customer-tracking/{customerId}/history', [AdminCustomerTrackingController::class, 'history'])->name('loan-management.customer-tracking.history');
        Route::post('/customer-tracking/{customerId}/toggle', [AdminCustomerTrackingController::class, 'toggle'])->name('loan-management.customer-tracking.toggle');

        Route::get('/payments', [LoanPaymentController::class, 'index'])->name('loan-management.payments');
        Route::get('/payments/index', [LoanPaymentController::class, 'index'])->name('loan-management.payments.index');
        Route::get('/payments/{payment}', [LoanPaymentController::class, 'show'])->name('loan-management.payments.show');
        Route::get('/payments/{payment}/edit', [LoanPaymentController::class, 'edit'])->name('loan-management.payments.edit');
        Route::put('/payments/{payment}', [LoanPaymentController::class, 'update'])->name('loan-management.payments.update');
        Route::delete('/payments/{payment}', [LoanPaymentController::class, 'destroy'])->name('loan-management.payments.destroy');

        Route::get('/live-chat', [LoanChatController::class, 'webInbox'])->name('loan-management.live-chat');
        Route::get('/live-chat/{thread}', [LoanChatController::class, 'webDetail'])->name('loan-management.live-chat.detail');
        Route::get('/chat-files/{file}', [LoanTelegramChatController::class, 'file'])->where(['file' => '[0-9]+'])->name('loan-management.chat-files.show');
        Route::get('/chat', [LoanChatController::class, 'webInbox'])->name('loan-management.chat.index');
        Route::get('/chat/{thread}', [LoanChatController::class, 'webDetail'])->name('loan-management.chat.detail');
        Route::delete('/chat/{thread}', [LoanChatController::class, 'destroy'])->name('loan-management.chat.destroy');
        foreach (['chat-api' => LoanChatController::class, 'telegram-chat-api' => LoanTelegramChatController::class] as $prefix => $controller) {
            Route::get("/{$prefix}/chats", [$controller, 'index'])->name("loan-management.{$prefix}.index");
            Route::post("/{$prefix}/chats", [$controller, 'store'])->name("loan-management.{$prefix}.store");
            Route::get("/{$prefix}/chats/{thread}", [$controller, 'show'])->name("loan-management.{$prefix}.show");
            Route::post("/{$prefix}/chats/{thread}/messages", [$controller, 'sendMessage'])->name("loan-management.{$prefix}.messages");
            Route::post("/{$prefix}/chats/{thread}/read", [$controller, 'read'])->name("loan-management.{$prefix}.read");
        }
        Route::post('/chat-api/chats/{thread}/assign', [LoanChatController::class, 'assign'])->name('loan-management.chat-api.assign');
        Route::post('/chat-api/chats/{thread}/transfer', [LoanChatController::class, 'transfer'])->name('loan-management.chat-api.transfer');
        Route::post('/chat-api/chats/{thread}/typing', [LoanChatController::class, 'typing'])->name('loan-management.chat-api.typing');
        Route::post('/chat-api/chats/{thread}/close', [LoanChatController::class, 'close'])->name('loan-management.chat-api.close');
        Route::post('/chat-api/chats/{thread}/reopen', [LoanChatController::class, 'reopen'])->name('loan-management.chat-api.reopen');
        Route::post('/chat-api/chats/{thread}/pin', [LoanChatController::class, 'pin'])->name('loan-management.chat-api.pin');
        Route::post('/chat-api/chats/{thread}/mute', [LoanChatController::class, 'mute'])->name('loan-management.chat-api.mute');
        Route::post('/telegram-chat-api/chats/{thread}/invoice-image', [LoanTelegramChatController::class, 'sendInvoiceImage'])->name('loan-management.telegram-chat-api.invoice-image');
        Route::put('/telegram-chat-api/chats/{thread}/messages/{message}', [LoanTelegramChatController::class, 'updateMessage'])->name('loan-management.telegram-chat-api.messages.update');
        Route::delete('/telegram-chat-api/chats/{thread}/messages/{message}', [LoanTelegramChatController::class, 'destroyMessage'])->name('loan-management.telegram-chat-api.messages.destroy');

        Route::get('/locations', [LoanLocationController::class, 'index'])->name('loan-management.locations.index');
        Route::get('/locations/asset-gallery', [LoanLocationController::class, 'assetGalleryModal'])->name('loan-management.locations.asset-gallery');
        Route::get('/locations/export', [LoanLocationController::class, 'export'])->name('loan-management.locations.export');
        Route::get('/locations/template', [LoanLocationController::class, 'template'])->name('loan-management.locations.template');
        Route::post('/locations/import', [LoanLocationController::class, 'import'])->name('loan-management.locations.import');
        Route::post('/locations/sync-pos', [LoanLocationController::class, 'syncFromPos'])->name('loan-management.locations.sync-pos');
        Route::post('/locations', [LoanLocationController::class, 'store'])->name('loan-management.locations.store');
        Route::put('/locations/{location}', [LoanLocationController::class, 'updateDetails'])->name('loan-management.locations.update');
        Route::delete('/locations/{location}', [LoanLocationController::class, 'destroy'])->name('loan-management.locations.destroy');
        Route::post('/locations/{location}/assets', [LoanLocationController::class, 'update'])->name('loan-management.locations.assets.update');
        Route::post('/locations/{location}/telegram-test', [LoanLocationController::class, 'testTelegram'])->name('loan-management.locations.telegram-test');
        Route::get('/location-assets/{location}/{filename}', [LoanLocationController::class, 'asset'])->name('loan-management.locations.assets.show');

        Route::get('/settings', fn () => redirect()->route('loan-management.settings.business'))->name('loan-management.settings');
        Route::get('/settings/business', [SettingsController::class, 'business'])->name('loan-management.settings.business');
        Route::get('/settings/business/logo', [SettingsController::class, 'businessLogo'])->name('loan-management.settings.business.logo');
        Route::post('/settings/business', [SettingsController::class, 'updateBusiness'])->name('loan-management.settings.business.update');
        Route::get('/settings/cms', [SettingsController::class, 'cms'])->name('loan-management.settings.cms');
        Route::post('/settings/cms', [SettingsController::class, 'updateCms'])->name('loan-management.settings.cms.update');
        Route::post('/settings/invoice-prefix', fn () => redirect()->route('loan-management.locations.index'))->name('loan-management.settings.invoice-prefix');
        Route::get('/settings/payment-methods', [SettingsController::class, 'paymentMethods'])->name('loan-management.settings.payment-methods');
        Route::post('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods'])->name('loan-management.settings.payment-methods.update');
        Route::get('/settings/currencies', fn () => redirect()->route('loan-management.settings.payment-methods'))->name('loan-management.settings.currencies');
        Route::post('/settings/currencies', fn () => redirect()->route('loan-management.settings.payment-methods'))->name('loan-management.settings.currencies.update');
        Route::get('/settings/telegram', [SettingsController::class, 'telegram'])->name('loan-management.settings.telegram');
        Route::post('/settings/telegram', [SettingsController::class, 'updateTelegram'])->name('loan-management.settings.telegram.update');
        Route::post('/settings/telegram/secret', [SettingsController::class, 'generateTelegramWebhookSecret'])->name('loan-management.settings.telegram.secret');
        Route::post('/settings/telegram/test', [SettingsController::class, 'testTelegramConnection'])->name('loan-management.settings.telegram.test');
        Route::post('/settings/telegram/webhook', [SettingsController::class, 'registerTelegramWebhook'])->name('loan-management.settings.telegram.webhook');

        Route::get('/system-status', [SystemHealthController::class, 'status'])->name('loan-management.system.status');
        Route::get('/system-status/data', [SystemHealthController::class, 'data'])->name('loan-management.system.status.data');
        Route::get('/system-check', [SystemHealthController::class, 'status'])->name('loan-management.system.check');

        Route::get('/activity-logs', [LoanActivityLogController::class, 'index'])->name('loan-management.activity-logs.index');

        Route::get('/users/export', [LoanUserController::class, 'export'])->name('loan-management.users.export');
        Route::get('/users/import-template', [LoanUserController::class, 'downloadTemplate'])->name('loan-management.users.import-template');
        Route::post('/users/import', [LoanUserController::class, 'import'])->name('loan-management.users.import');
        Route::get('/users', [LoanUserController::class, 'index'])->name('loan-management.users.index');
        Route::get('/users/create', [LoanUserController::class, 'create'])->name('loan-management.users.create');
        Route::post('/users', [LoanUserController::class, 'store'])->name('loan-management.users.store');
        Route::get('/users/{user}', [LoanUserController::class, 'show'])->where(['user' => '[0-9]+'])->name('loan-management.users.show');
        Route::get('/users/{user}/edit', [LoanUserController::class, 'edit'])->where(['user' => '[0-9]+'])->name('loan-management.users.edit');
        Route::put('/users/{user}', [LoanUserController::class, 'update'])->where(['user' => '[0-9]+'])->name('loan-management.users.update');
        Route::delete('/users/{user}', [LoanUserController::class, 'destroy'])->where(['user' => '[0-9]+'])->name('loan-management.users.destroy');
        Route::post('/users/{user}/toggle-status', [LoanUserController::class, 'toggleStatus'])->where(['user' => '[0-9]+'])->name('loan-management.users.toggle-status');
        Route::post('/users/{user}/reset-password', [LoanUserController::class, 'resetPassword'])->where(['user' => '[0-9]+'])->name('loan-management.users.reset-password');

        Route::get('/roles/export', [LoanRoleController::class, 'export'])->name('loan-management.roles.export');
        Route::get('/roles/import-template', [LoanRoleController::class, 'downloadTemplate'])->name('loan-management.roles.import-template');
        Route::post('/roles/import', [LoanRoleController::class, 'import'])->name('loan-management.roles.import');
        Route::get('/roles', [LoanRoleController::class, 'index'])->name('loan-management.roles.index');
        Route::get('/roles/create', [LoanRoleController::class, 'create'])->name('loan-management.roles.create');
        Route::post('/roles', [LoanRoleController::class, 'store'])->name('loan-management.roles.store');
        Route::get('/roles/{role}/edit', [LoanRoleController::class, 'edit'])->where(['role' => '[0-9]+'])->name('loan-management.roles.edit');
        Route::put('/roles/{role}', [LoanRoleController::class, 'update'])->where(['role' => '[0-9]+'])->name('loan-management.roles.update');
        Route::delete('/roles/{role}', [LoanRoleController::class, 'destroy'])->where(['role' => '[0-9]+'])->name('loan-management.roles.destroy');

        Route::get('/tools/import-export', [LoanImportExportController::class, 'index'])->name('loan-management.import.index');
        Route::post('/tools/import', [LoanImportExportController::class, 'import'])->name('loan-management.import.upload');
        Route::post('/tools/import/start', [LoanImportExportController::class, 'startImport'])->name('loan-management.import.start');
        Route::post('/tools/import/process', [LoanImportExportController::class, 'processImport'])->name('loan-management.import.process');
        Route::get('/tools/import/{batch}/progress', [LoanImportExportController::class, 'importProgress'])->name('loan-management.import.progress');
        Route::get('/tools/import/{batch}/invalid-rows', [LoanImportExportController::class, 'invalidRows'])->name('loan-management.import.invalid-rows');
        Route::get('/tools/template/{type}', [LoanImportExportController::class, 'template'])->name('loan-management.import.template');
        Route::get('/tools/export', [LoanImportExportController::class, 'export'])->name('loan-management.export.download');
        Route::get('/tools/monthly-import-export', [LoanImportExportController::class, 'payments'])->name('loan-management.tools.monthly-import-export');
        Route::get('/tools/loan-import-export', [LoanImportExportController::class, 'loans'])->name('loan-management.tools.loan-import-export');
        Route::get('/tools/send-notification', [DashboardController::class, 'placeholder'])->defaults('page', 'Send Notification')->name('loan-management.tools.send-notification');

        Route::get('/schedules', [DashboardController::class, 'loanSchedules'])->name('loan-management.schedules.index');
        Route::get('/schedules/calendar', [DashboardController::class, 'installmentCalendar'])->name('loan-management.schedules.calendar');
        Route::get('/schedules/calendar-day-details', [DashboardController::class, 'installmentCalendarDayDetails'])->name('loan-management.schedules.calendar-day-details');
        Route::get('/monthly-payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Monthly Payments')->name('loan-management.monthly-payments.index');
        Route::get('/overdue', [DashboardController::class, 'overdue'])->name('loan-management.overdue.index');
        Route::get('/collection-visits', [DashboardController::class, 'collectionVisits'])->name('loan-management.collection-visits.index');
        Route::get('/gps', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.gps.index');
        Route::get('/finance/aba-transactions', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba.index');
        Route::get('/aba', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba');
        Route::get('/reports', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports');
        Route::get('/reports/index', [DashboardController::class, 'installmentReports'])->name('loan-management.reports.index');
        Route::get('/reports/dashboard', [DashboardController::class, 'dashboardReports'])->name('loan-management.reports.dashboard');
        Route::get('/reports/daily-loan-summary', [DashboardController::class, 'dailyLoanSummary'])->name('loan-management.reports.daily-loan-summary');
        Route::get('/reports/monthly-loan-summary', [DashboardController::class, 'monthlyLoanSummary'])->name('loan-management.reports.monthly-loan-summary');
        Route::get('/reports/yearly-loan-summary', [DashboardController::class, 'yearlyLoanSummary'])->name('loan-management.reports.yearly-loan-summary');
        Route::get('/reports/payment-summary-by-type', [DashboardController::class, 'paymentSummaryByType'])->name('loan-management.reports.payment-summary-by-type');
        Route::get('/reports/payments', [DashboardController::class, 'paymentSummaryByType'])->name('loan-management.reports.payments');
        Route::get('/blacklist/export-csv', [DashboardController::class, 'blacklistExportCsv'])->name('loan-management.blacklist.export-csv');
        Route::get('/blacklist/search-customers', [LoanCustomerController::class, 'blacklistSearchCustomers'])->name('loan-management.blacklist.search-customers');
        Route::get('/blacklist', [DashboardController::class, 'blacklistIndex'])->name('loan-management.blacklist.index');
        Route::post('/blacklist', [LoanCustomerController::class, 'blacklistStore'])->name('loan-management.blacklist.store');
    });

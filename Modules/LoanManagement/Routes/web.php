<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\DashboardController;
use Modules\LoanManagement\Http\Controllers\AdminCustomerTrackingController;
use Modules\LoanManagement\Http\Controllers\CambodiaAddressController;
use Modules\LoanManagement\Http\Controllers\InstallController;
use Modules\LoanManagement\Http\Controllers\LoanActivityLogController;
use Modules\LoanManagement\Http\Controllers\LoanCustomerController;
use Modules\LoanManagement\Http\Controllers\LoanDashboardController;
use Modules\LoanManagement\Http\Controllers\LoanCreateController;
use Modules\LoanManagement\Http\Controllers\LoanFromSellController;
use Modules\LoanManagement\Http\Controllers\LoanImportExportController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\LoanCollectionController;
use Modules\LoanManagement\Http\Controllers\LoanInstallmentListController;
use Modules\LoanManagement\Http\Controllers\LoanLocationController;
use Modules\LoanManagement\Http\Controllers\LoanPaymentController;
use Modules\LoanManagement\Http\Controllers\LoanTelegramChatController;
use Modules\LoanManagement\Http\Controllers\LoanTelegramWebhookController;
use Modules\LoanManagement\Http\Controllers\LoanUserController;
use Modules\LoanManagement\Http\Controllers\SettingsController;

// Public Telegram webhook - deliberately outside the auth-wrapped group below (Telegram
// cannot authenticate as a staff session) and outside the 'loan-management' prefix so its
// URI matches the '/webhook/*' CSRF exemption in App\Http\Middleware\VerifyCsrfToken.
// Security is enforced inside the controller via Telegram's X-Telegram-Bot-Api-Secret-Token header.
Route::middleware(['web'])
    ->post('/webhook/loan-telegram', [LoanTelegramWebhookController::class, 'handle'])
    ->name('loan-management.telegram.webhook');

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'loan.activity'])
    ->prefix('loan-management')
    ->group(function () {
        $createLoanPermission = 'loan.permission:loan_management.create_from_sell|loan_management.loans.create|loan_management.create';
        $managePaymentPermission = 'loan.permission:loan_management.payment|loan_management.payments.create|loan_management.edit';

        Route::get('/', function () {
            return redirect()->route('loan-management.dashboard');
        })->name('loan-management.dashboard.home')->middleware('can:loan_management.view');
        Route::get('/dashboard', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard')->middleware('can:loan_management.view');
        Route::get('/dashboard/main', [LoanDashboardController::class, 'index'])->name('loan-management.dashboard.index')->middleware('can:loan_management.view');
        Route::get('/dashboard/data', [LoanDashboardController::class, 'data'])->name('loan-management.dashboard.data')->middleware('can:loan_management.view');
        Route::get('/dashboard/quick-search', [LoanDashboardController::class, 'quickSearch'])->name('loan-management.dashboard.quick-search')->middleware('can:loan_management.view');
        Route::get('/admin-loan', [DashboardController::class, 'adminLoan'])->name('loan-management.admin-loan')->middleware('can:loan_management.view');
        Route::get('/admin-loan/export', [DashboardController::class, 'adminLoanExport'])->name('loan-management.admin-loan.export')->middleware('can:loan_management.view');
        Route::get('/admin-loan/details', [DashboardController::class, 'adminLoanDetails'])->name('loan-management.admin-loan.details')->middleware('can:loan_management.view');
        Route::post('/admin-loan/details/{loan}/update', [DashboardController::class, 'adminLoanInlineUpdate'])->name('loan-management.admin-loan.details.update')->middleware('can:loan_management.edit');
        Route::post('/language', [SettingsController::class, 'switchLanguage'])->name('loan-management.language.switch')->middleware('can:loan_management.view');

        Route::get('/operations/{page}', [LoanCollectionController::class, 'index'])
            ->whereIn('page', ['new-loans', 'active-loans', 'due-today', 'partial-payments', 'closed-accounts'])
            ->name('loan-management.operations.page')
            ->middleware('can:loan_management.view');
        Route::get('/collection/{page}', [LoanCollectionController::class, 'index'])
            ->whereIn('page', ['overdue-accounts', 'promise-to-pay', 'broken-promise', 'field-visit-required', 'skip-customers', 'delinquent-accounts', 'recovery-management', 'debt-collection'])
            ->name('loan-management.collection.page')
            ->middleware('can:loan_management.view');
        Route::get('/risk/{page}', [LoanCollectionController::class, 'index'])
            ->whereIn('page', ['high-risk-customers', 'fraud-risk', 'legal-cases', 'blacklisted-customers', 'repossessions'])
            ->name('loan-management.risk.page')
            ->middleware('can:loan_management.view');
        Route::get('/communication/{page}', [LoanCollectionController::class, 'index'])
            ->whereIn('page', ['voice-calls', 'notifications', 'sms-telegram-logs'])
            ->name('loan-management.communication.page')
            ->middleware('can:loan_management.view');
        Route::get('/customers-workflow/{page}', [LoanCollectionController::class, 'index'])
            ->whereIn('page', ['contact-history'])
            ->name('loan-management.customer-workflow.page')
            ->middleware('can:loan_management.view');
        Route::get('/collection-reports', [LoanCollectionController::class, 'reports'])
            ->name('loan-management.collection.reports')
            ->middleware('can:loan_management.view');
        Route::get('/collection-reports/{report}', [LoanCollectionController::class, 'report'])
            ->name('loan-management.collection.report')
            ->middleware('can:loan_management.view');

        Route::get('/loans/create-from-sell', [LoanFromSellController::class, 'index'])->name('loan-management.loans.create-from-sell')->middleware($createLoanPermission);
        Route::get('/loans/search-sales', [LoanFromSellController::class, 'searchSales'])->name('loan-management.loans.search-sales')->middleware($createLoanPermission);
        Route::get('/loans/search-sells', [LoanFromSellController::class, 'search'])->name('loan-management.loans.search-sells')->middleware($createLoanPermission);
        Route::get('/loans/sales/{transaction_id}/clone-data', [LoanFromSellController::class, 'cloneData'])->name('loan-management.loans.sales.clone-data')->middleware($createLoanPermission);
        Route::get('/loans/sell/{transaction_id}/clone', [LoanFromSellController::class, 'clone'])->name('loan-management.loans.clone-sell')->middleware($createLoanPermission);
        Route::get('/loans/sell/{transaction_id}/check-duplicate', [LoanFromSellController::class, 'checkDuplicateLoan'])->name('loan-management.loans.check-duplicate')->middleware($createLoanPermission);
        Route::post('/loans/preview-schedule', [LoanFromSellController::class, 'previewSchedule'])->name('loan-management.loans.preview-schedule')->middleware($createLoanPermission);
        Route::post('/loans/store-from-sell', [LoanFromSellController::class, 'store'])->name('loan-management.loans.store-from-sell')->middleware($createLoanPermission);

        Route::get('/loans/create', [LoanCreateController::class, 'index'])->name('loan-management.loans.create')->middleware($createLoanPermission);
        Route::get('/loans/create-standalone-modal', [LoanCreateController::class, 'modal'])->name('loan-management.loans.create-standalone-modal')->middleware($createLoanPermission);
        Route::get('/loans/ajax/search-loan-customers', [LoanCreateController::class, 'searchCustomers'])->name('loan-management.loans.ajax.search-customers')->middleware($createLoanPermission);
        Route::post('/loans/ajax/scan-id-card', [LoanCreateController::class, 'scanIdCard'])->name('loan-management.loans.ajax.scan-id-card')->middleware($createLoanPermission);
        Route::post('/loans/ajax/scan-product-photo', [LoanCreateController::class, 'scanProductPhoto'])->name('loan-management.loans.ajax.scan-product-photo')->middleware($createLoanPermission);
        Route::get('/loans/ajax/product-by-serial', [LoanCreateController::class, 'lookupProductBySerial'])->name('loan-management.loans.ajax.product-by-serial')->middleware($createLoanPermission);
        Route::get('/cambodia-address/sync', [CambodiaAddressController::class, 'sync'])->name('loan-management.cambodia-address.sync')->middleware($createLoanPermission);
        Route::get('/cambodia-address/provinces', [CambodiaAddressController::class, 'provinces'])->name('loan-management.cambodia-address.provinces')->middleware($createLoanPermission);
        Route::get('/cambodia-address/districts', [CambodiaAddressController::class, 'districts'])->name('loan-management.cambodia-address.districts')->middleware($createLoanPermission);
        Route::get('/cambodia-address/communes', [CambodiaAddressController::class, 'communes'])->name('loan-management.cambodia-address.communes')->middleware($createLoanPermission);
        Route::get('/cambodia-address/villages', [CambodiaAddressController::class, 'villages'])->name('loan-management.cambodia-address.villages')->middleware($createLoanPermission);
        Route::post('/loans/preview-standalone-schedule', [LoanCreateController::class, 'previewSchedule'])->name('loan-management.loans.preview-standalone-schedule')->middleware($createLoanPermission);
        Route::post('/loans/store-standalone', [LoanCreateController::class, 'store'])->name('loan-management.loans.store-standalone')->middleware($createLoanPermission);
        Route::get('/loans/calculator', [LoanCreateController::class, 'calculator'])->name('loan-management.loans.calculator')->middleware($createLoanPermission);
        Route::get('/loans/calculator/print', [LoanCreateController::class, 'calculatorPrint'])->name('loan-management.loans.calculator.print')->middleware($createLoanPermission);
        Route::get('/loans/list-data', [LoanInstallmentListController::class, 'data'])->name('loan-management.loans.list-data')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/print-modal', [LoanInstallmentListController::class, 'printModal'])->name('loan-management.loans.print-modal')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/print', [LoanInstallmentListController::class, 'print'])->name('loan-management.loans.print')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/payments/collection-modal', [LoanPaymentController::class, 'collectionModal'])->name('loan-management.loans.payments.collection-modal')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/payment/create', [LoanInstallmentListController::class, 'createPayment'])->name('loan-management.loans.payment.create')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/payment/quick-pay', [LoanInstallmentListController::class, 'mobileQuickPay'])->name('loan-management.loans.payment.quick-pay')->middleware('can:loan_management.view');
        Route::post('/loans/{loan}/payment', [LoanInstallmentListController::class, 'storePayment'])->name('loan-management.loans.payment.store')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/convert-to-pos', [LoanInstallmentListController::class, 'convertToPos'])->name('loan-management.loans.convert-to-pos')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/payment/copy-info', [LoanInstallmentListController::class, 'paymentCopyInfo'])->name('loan-management.loans.payment.copy-info')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/view', [LoanInstallmentListController::class, 'show'])->name('loan-management.loans.view')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/sections/show', [LoanInstallmentListController::class, 'showSections'])->name('loan-management.loans.sections.show')->middleware('can:loan_management.view');
        Route::get('/loans/{loan}/edit', [LoanInstallmentListController::class, 'edit'])->name('loan-management.loans.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/ajax/scan-id-card', [LoanCreateController::class, 'scanIdCard'])->name('loan-management.loans.edit.scan-id-card')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/ajax/scan-product-photo', [LoanCreateController::class, 'scanProductPhoto'])->name('loan-management.loans.edit.scan-product-photo')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/cambodia-address/provinces', [CambodiaAddressController::class, 'provinces'])->name('loan-management.loans.edit.cambodia-address.provinces')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/cambodia-address/districts', [CambodiaAddressController::class, 'districts'])->name('loan-management.loans.edit.cambodia-address.districts')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/cambodia-address/communes', [CambodiaAddressController::class, 'communes'])->name('loan-management.loans.edit.cambodia-address.communes')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/cambodia-address/villages', [CambodiaAddressController::class, 'villages'])->name('loan-management.loans.edit.cambodia-address.villages')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/sections/edit', [LoanInstallmentListController::class, 'editSections'])->name('loan-management.loans.sections.edit')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/workflow/edit', [LoanInstallmentListController::class, 'editWorkflow'])->name('loan-management.loans.workflow.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/workflow', [LoanInstallmentListController::class, 'updateWorkflow'])->name('loan-management.loans.workflow.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/update', [LoanInstallmentListController::class, 'update'])->name('loan-management.loans.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/schedules/update-from-edit', [LoanInstallmentListController::class, 'updateSchedulesFromEdit'])->name('loan-management.loans.schedules.update-from-edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/schedules/refresh', [LoanInstallmentListController::class, 'refreshSchedules'])->name('loan-management.loans.schedules.refresh')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/items/create', [LoanInstallmentListController::class, 'createItem'])->name('loan-management.loans.items.create')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/items', [LoanInstallmentListController::class, 'storeItem'])->name('loan-management.loans.items.store')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/items/{item}/edit', [LoanInstallmentListController::class, 'editItem'])->name('loan-management.loans.items.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/items/{item}', [LoanInstallmentListController::class, 'updateItem'])->name('loan-management.loans.items.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/items/{item}/delete', [LoanInstallmentListController::class, 'destroyItem'])->name('loan-management.loans.items.destroy')->middleware('can:loan_management.edit');
        Route::get('/loans/{loan}/schedules/{schedule}/edit', [LoanInstallmentListController::class, 'editSchedule'])->name('loan-management.loans.schedules.edit')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/schedules/{schedule}', [LoanInstallmentListController::class, 'updateSchedule'])->name('loan-management.loans.schedules.update')->middleware('can:loan_management.edit');
        Route::post('/loans/{loan}/schedules/{schedule}/delete', [LoanInstallmentListController::class, 'destroySchedule'])->name('loan-management.loans.schedules.destroy')->middleware('can:loan_management.edit');
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
        Route::post('/customers/{customer}/telegram/link', [LoanCustomerController::class, 'generateTelegramLink'])->name('loan-management.customers.telegram.link')->middleware('can:loan_management.edit');
        Route::post('/customers/{customer}/telegram/unlink', [LoanCustomerController::class, 'unlinkTelegram'])->name('loan-management.customers.telegram.unlink')->middleware('can:loan_management.edit');
        Route::delete('/customers/{customer}', [LoanCustomerController::class, 'destroy'])->name('loan-management.customers.destroy')->middleware('can:loan_management.delete');
        Route::get('/customer-tracking', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.customer-tracking')->middleware('can:loan_management.view');
        Route::get('/customer-tracking/data', [AdminCustomerTrackingController::class, 'data'])->name('loan-management.customer-tracking.data')->middleware('can:loan_management.view');
        Route::get('/customer-tracking/{customerId}/history', [AdminCustomerTrackingController::class, 'history'])->name('loan-management.customer-tracking.history')->middleware('can:loan_management.view');
        Route::post('/customer-tracking/{customerId}/toggle', [AdminCustomerTrackingController::class, 'toggle'])->name('loan-management.customer-tracking.toggle')->middleware('can:loan_management.customer_gps.manage');
        Route::get('/live-chat', [LoanChatController::class, 'webInbox'])->name('loan-management.live-chat')->middleware('can:loan_management.chat.view');
        Route::get('/live-chat/{thread}', [LoanChatController::class, 'webDetail'])->name('loan-management.live-chat.detail')->middleware('can:loan_management.chat.view');
        Route::get('/chat-api/chats', [LoanChatController::class, 'index'])->name('loan-management.chat-api.index')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats', [LoanChatController::class, 'store'])->name('loan-management.chat-api.store')->middleware('can:loan_management.chat.view');
        Route::get('/chat-api/chats/{thread}', [LoanChatController::class, 'show'])->name('loan-management.chat-api.show')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats/{thread}/messages', [LoanChatController::class, 'sendMessage'])->name('loan-management.chat-api.messages')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats/{thread}/assign', [LoanChatController::class, 'assign'])->name('loan-management.chat-api.assign')->middleware('can:loan_management.chat.assign');
        Route::post('/chat-api/chats/{thread}/transfer', [LoanChatController::class, 'transfer'])->name('loan-management.chat-api.transfer')->middleware('loan.permission:loan_management.chat.transfer|loan_management.chat.assign');
        Route::post('/chat-api/chats/{thread}/read', [LoanChatController::class, 'read'])->name('loan-management.chat-api.read')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats/{thread}/typing', [LoanChatController::class, 'typing'])->name('loan-management.chat-api.typing')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats/{thread}/close', [LoanChatController::class, 'close'])->name('loan-management.chat-api.close')->middleware('can:loan_management.chat.close');
        Route::post('/chat-api/chats/{thread}/reopen', [LoanChatController::class, 'reopen'])->name('loan-management.chat-api.reopen')->middleware('can:loan_management.chat.close');
        Route::post('/chat-api/chats/{thread}/pin', [LoanChatController::class, 'pin'])->name('loan-management.chat-api.pin')->middleware('can:loan_management.chat.view');
        Route::post('/chat-api/chats/{thread}/mute', [LoanChatController::class, 'mute'])->name('loan-management.chat-api.mute')->middleware('can:loan_management.chat.view');
        Route::delete('/chat/{thread}', [LoanChatController::class, 'destroy'])->name('loan-management.chat.destroy')->middleware('can:loan_management.chat.delete');

        // Telegram customer-chat bridge - fully separate from the chat-api/* routes above
        // (which power the staff's own internal Live Chat tool and never touch Telegram).
        Route::get('/telegram-chat-api/chats', [LoanTelegramChatController::class, 'index'])->name('loan-management.telegram-chat-api.index')->middleware('can:loan_management.chat.view');
        Route::post('/telegram-chat-api/chats', [LoanTelegramChatController::class, 'store'])->name('loan-management.telegram-chat-api.store')->middleware('can:loan_management.chat.view');
        Route::get('/telegram-chat-api/chats/{thread}', [LoanTelegramChatController::class, 'show'])->name('loan-management.telegram-chat-api.show')->middleware('can:loan_management.chat.view');
        Route::post('/telegram-chat-api/chats/{thread}/messages', [LoanTelegramChatController::class, 'sendMessage'])->name('loan-management.telegram-chat-api.messages')->middleware('can:loan_management.chat.view');
        Route::post('/telegram-chat-api/chats/{thread}/invoice-image', [LoanTelegramChatController::class, 'sendInvoiceImage'])->name('loan-management.telegram-chat-api.invoice-image')->middleware('can:loan_management.chat.view');
        Route::put('/telegram-chat-api/chats/{thread}/messages/{message}', [LoanTelegramChatController::class, 'updateMessage'])->name('loan-management.telegram-chat-api.messages.update')->middleware('loan.permission:loan_management.chat.view|loan_management.chat.reply|loan_management.chat.admin');
        Route::delete('/telegram-chat-api/chats/{thread}/messages/{message}', [LoanTelegramChatController::class, 'destroyMessage'])->name('loan-management.telegram-chat-api.messages.destroy')->middleware('loan.permission:loan_management.chat.view|loan_management.chat.reply|loan_management.chat.delete|loan_management.chat.admin');
        Route::post('/telegram-chat-api/chats/{thread}/read', [LoanTelegramChatController::class, 'read'])->name('loan-management.telegram-chat-api.read')->middleware('can:loan_management.chat.view');
        Route::get('/loans', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans')->middleware('can:loan_management.view');
        Route::get('/loans/list', [LoanInstallmentListController::class, 'index'])->name('loan-management.loans.index')->middleware('can:loan_management.view');
        Route::get('/schedules', [DashboardController::class, 'placeholder'])->defaults('page', 'Installment Schedules')->name('loan-management.schedules.index')->middleware('can:loan_management.view');
        Route::get('/monthly-payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Monthly Payments')->name('loan-management.monthly-payments.index')->middleware('can:loan_management.view');
        Route::get('/overdue', [DashboardController::class, 'overdue'])->name('loan-management.overdue.index')->middleware('can:loan_management.view');
        Route::get('/locations', [LoanLocationController::class, 'index'])->name('loan-management.locations.index')->middleware('can:loan_management.view');
        Route::get('/locations/asset-gallery', [LoanLocationController::class, 'assetGalleryModal'])->name('loan-management.locations.asset-gallery')->middleware('can:loan_management.view');
        Route::get('/locations/export', [LoanLocationController::class, 'export'])->name('loan-management.locations.export')->middleware('can:loan_management.view');
        Route::get('/locations/template', [LoanLocationController::class, 'template'])->name('loan-management.locations.template')->middleware('can:loan_management.view');
        Route::post('/locations/import', [LoanLocationController::class, 'import'])->name('loan-management.locations.import')->middleware('can:loan_management.view');
        Route::post('/locations/sync-pos', [LoanLocationController::class, 'syncFromPos'])->name('loan-management.locations.sync-pos')->middleware('can:loan_management.view');
        Route::post('/locations', [LoanLocationController::class, 'store'])->name('loan-management.locations.store')->middleware('can:loan_management.view');
        Route::put('/locations/{location}', [LoanLocationController::class, 'updateDetails'])->name('loan-management.locations.update')->middleware('can:loan_management.view');
        Route::delete('/locations/{location}', [LoanLocationController::class, 'destroy'])->name('loan-management.locations.destroy')->middleware('can:loan_management.view');
        Route::post('/locations/{location}/assets', [LoanLocationController::class, 'update'])->name('loan-management.locations.assets.update')->middleware('can:loan_management.view');
        Route::post('/locations/{location}/telegram-test', [LoanLocationController::class, 'testTelegram'])->name('loan-management.locations.telegram-test')->middleware('can:loan_management.view');
        Route::get('/location-assets/{location}/{filename}', [LoanLocationController::class, 'asset'])->name('loan-management.locations.assets.show')->middleware('can:loan_management.view');

        Route::get('/payments', [LoanPaymentController::class, 'index'])->name('loan-management.payments')->middleware('can:loan_management.view');
        Route::get('/payments/index', [LoanPaymentController::class, 'index'])->name('loan-management.payments.index')->middleware('can:loan_management.view');
        Route::get('/payments/{payment}', [LoanPaymentController::class, 'show'])->name('loan-management.payments.show')->middleware('can:loan_management.view');
        Route::get('/payments/{payment}/edit', [LoanPaymentController::class, 'edit'])->name('loan-management.payments.edit')->middleware($managePaymentPermission);
        Route::put('/payments/{payment}', [LoanPaymentController::class, 'update'])->name('loan-management.payments.update')->middleware($managePaymentPermission);
        Route::delete('/payments/{payment}', [LoanPaymentController::class, 'destroy'])->name('loan-management.payments.destroy')->middleware($managePaymentPermission);
        Route::get('/payment-history', [LoanPaymentController::class, 'index'])->name('loan-management.payment-history.index')->middleware('can:loan_management.view');
        Route::get('/collection-visits', [DashboardController::class, 'placeholder'])->defaults('page', 'Collection Visits')->name('loan-management.collection-visits.index')->middleware('can:loan_management.view');
        Route::get('/gps', [AdminCustomerTrackingController::class, 'index'])->name('loan-management.gps.index')->middleware('can:loan_management.view');
        Route::get('/chat', [LoanChatController::class, 'webInbox'])->name('loan-management.chat.index')->middleware('can:loan_management.chat.view');

        Route::get('/finance/aba-transactions', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba.index')->middleware('can:loan_management.view');
        Route::get('/aba', [DashboardController::class, 'placeholder'])->defaults('page', 'ABA Transactions')->name('loan-management.aba')->middleware('can:loan_management.view');
        Route::get('/reports', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports')->middleware('can:loan_management.view');
        Route::get('/reports/index', [DashboardController::class, 'placeholder'])->defaults('page', 'Reports')->name('loan-management.reports.index')->middleware('can:loan_management.view');
        Route::get('/reports/yearly-loan-summary', [DashboardController::class, 'yearlyLoanSummary'])->name('loan-management.reports.yearly-loan-summary')->middleware('can:loan_management.view');
        Route::get('/reports/payments', [DashboardController::class, 'placeholder'])->defaults('page', 'Payments Report')->name('loan-management.reports.payments')->middleware('can:loan_management.view');

        Route::get('/tools/import', [LoanImportExportController::class, 'index'])->name('loan-management.import.index')->middleware('can:loan_management.import.view');
        Route::get('/import', [LoanImportExportController::class, 'index'])->name('loan-management.import')->middleware('can:loan_management.import.view');
        Route::post('/tools/import', [LoanImportExportController::class, 'import'])->name('loan-management.import.store')->middleware('can:loan_management.import.view');
        Route::post('/tools/import/start', [LoanImportExportController::class, 'startImport'])->name('loan-management.import.start')->middleware('can:loan_management.import.view');
        Route::post('/tools/import/process', [LoanImportExportController::class, 'processImport'])->name('loan-management.import.process')->middleware('can:loan_management.import.view');
        Route::get('/tools/import/progress/{batch}', [LoanImportExportController::class, 'importProgress'])->name('loan-management.import.progress')->middleware('can:loan_management.import.view');
        Route::get('/tools/export', [LoanImportExportController::class, 'index'])->name('loan-management.export.index')->middleware('can:loan_management.export.view');
        Route::get('/export', [LoanImportExportController::class, 'index'])->name('loan-management.export')->middleware('can:loan_management.export.view');
        Route::get('/tools/export/download', [LoanImportExportController::class, 'export'])->name('loan-management.export.download')->middleware('can:loan_management.export.view');
        Route::get('/tools/import-template/{type}', [LoanImportExportController::class, 'template'])->name('loan-management.import.template')->middleware('can:loan_management.import.view');
        Route::get('/tools/monthly-import-export', [LoanImportExportController::class, 'payments'])->name('loan-management.tools.monthly-import-export')->middleware('loan.permission:loan_management.import.view|loan_management.export.view');
        Route::get('/tools/loan-import-export', [LoanImportExportController::class, 'loans'])->name('loan-management.tools.loan-import-export')->middleware('loan.permission:loan_management.import.view|loan_management.export.view');
        Route::get('/tools/send-notification', [DashboardController::class, 'placeholder'])->defaults('page', 'Send Notification')->name('loan-management.tools.send-notification')->middleware('can:loan_management.view');
        Route::get('/tools/activity-logs', [LoanActivityLogController::class, 'index'])->name('loan-management.activity-logs.index')->middleware('can:loan_management.view');
        Route::get('/settings', [SettingsController::class, 'invoicePrefix'])->name('loan-management.settings')->middleware('can:loan_management.view');
        Route::get('/settings/index', [SettingsController::class, 'invoicePrefix'])->name('loan-management.settings.index')->middleware('can:loan_management.view');
        Route::get('/settings/payment-methods', [SettingsController::class, 'paymentMethods'])->name('loan-management.settings.payment-methods')->middleware('can:loan_management.view');
        Route::post('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods'])->name('loan-management.settings.payment-methods.update')->middleware('can:loan_management.view');
        Route::get('/settings/currencies', [DashboardController::class, 'placeholder'])->defaults('page', 'Currencies')->name('loan-management.settings.currencies')->middleware('can:loan_management.view');
        Route::post('/settings/invoice-prefix', [SettingsController::class, 'updateInvoicePrefix'])->name('loan-management.settings.invoice-prefix')->middleware('can:loan_management.view');
        Route::get('/settings/telegram', [SettingsController::class, 'telegram'])->name('loan-management.settings.telegram')->middleware('can:loan_management.view');
        Route::post('/settings/telegram', [SettingsController::class, 'updateTelegram'])->name('loan-management.settings.telegram.update')->middleware('can:loan_management.view');
        Route::post('/settings/telegram/generate-secret', [SettingsController::class, 'generateTelegramWebhookSecret'])->name('loan-management.settings.telegram.generate-secret')->middleware('can:loan_management.view');
        Route::post('/settings/telegram/test-connection', [SettingsController::class, 'testTelegramConnection'])->name('loan-management.settings.telegram.test-connection')->middleware('can:loan_management.view');
        Route::post('/settings/telegram/register-webhook', [SettingsController::class, 'registerTelegramWebhook'])->name('loan-management.settings.telegram.register-webhook')->middleware('can:loan_management.view');

        Route::get('/guarantors', [DashboardController::class, 'placeholder'])->defaults('page', 'Guarantors')->name('loan-management.guarantors.index')->middleware('can:loan_management.view');
        Route::get('/blacklist', [DashboardController::class, 'placeholder'])->defaults('page', 'Blacklist')->name('loan-management.blacklist.index')->middleware('can:loan_management.view');

        Route::get('/users', [LoanUserController::class, 'index'])->name('loan-management.users.index')->middleware('can:loan_management.view');
        Route::get('/users/create', [LoanUserController::class, 'create'])->name('loan-management.users.create')->middleware('can:loan_management.create');
        Route::post('/users', [LoanUserController::class, 'store'])->name('loan-management.users.store')->middleware('can:loan_management.create');
        Route::get('/users/{user}', [LoanUserController::class, 'show'])->name('loan-management.users.show')->middleware('can:loan_management.view');
        Route::get('/users/{user}/edit', [LoanUserController::class, 'edit'])->name('loan-management.users.edit')->middleware('can:loan_management.edit');
        Route::put('/users/{user}', [LoanUserController::class, 'update'])->name('loan-management.users.update')->middleware('can:loan_management.edit');
        Route::delete('/users/{user}', [LoanUserController::class, 'destroy'])->name('loan-management.users.destroy')->middleware('can:loan_management.delete');
        Route::post('/users/{user}/toggle-status', [LoanUserController::class, 'toggleStatus'])->name('loan-management.users.toggle-status')->middleware('can:loan_management.edit');
        Route::post('/users/{user}/reset-password', [LoanUserController::class, 'resetPassword'])->name('loan-management.users.reset-password')->middleware('can:loan_management.edit');

        Route::get('/install', [InstallController::class, 'index'])->middleware('superadmin');
        Route::post('/install', [InstallController::class, 'install'])->middleware('superadmin');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->middleware('superadmin');
        Route::get('/install/update', [InstallController::class, 'update'])->middleware('superadmin');
    });

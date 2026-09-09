<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\AuthController;
use Modules\LoanManagement\Http\Controllers\LoanAbaPaywayController;
use Modules\LoanManagement\Http\Controllers\LoanFileUploadController;
use Modules\LoanManagement\Http\Controllers\LoanTelegramChatController;
use Modules\LoanManagement\Http\Controllers\CustomerAppAuthController;
use Modules\LoanManagement\Http\Controllers\CustomerAppDashboardController;
use Modules\LoanManagement\Http\Controllers\CustomerAppLoanController;
use Modules\LoanManagement\Http\Controllers\CustomerAppPaymentController;
use Modules\LoanManagement\Http\Controllers\CustomerAppProfileController;
use Modules\LoanManagement\Http\Controllers\CustomerChatController;
use Modules\LoanManagement\Http\Controllers\CustomerLocationTrackingController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\LoanCreateController;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Modules\LoanManagement\Http\Controllers\StaffMobileActionController;
use Modules\LoanManagement\Http\Controllers\StaffMobileController;
use Modules\LoanManagement\Http\Controllers\StaffMobileLoanController;

Route::prefix('loan-management')->group(function () {
    Route::get('/app-settings', [PublicAppController::class, 'appSettings']);
    Route::get('/app-version', [PublicAppController::class, 'appVersion']);
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/customer/login', [CustomerAppAuthController::class, 'login']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/mobile/dashboard', [StaffMobileController::class, 'dashboard'])->middleware('loan.permission:loan_management.dashboard.view|loan_management.view');
        Route::get('/mobile/customers', [StaffMobileController::class, 'customers'])->middleware('loan.permission:loan_management.customers.view|loan_management.view');
        Route::get('/mobile/customers/{id}', [StaffMobileController::class, 'customerShow'])->middleware('loan.permission:loan_management.customers.view|loan_management.view');
        Route::put('/mobile/customers/{id}', [StaffMobileController::class, 'customerUpdate'])->middleware('loan.permission:loan_management.customers.edit|loan_management.edit');
        Route::post('/mobile/customers/{id}/verify', [StaffMobileController::class, 'customerVerify'])->middleware('loan.permission:loan_management.customers.edit|loan_management.approve');
        Route::get('/mobile/late-customers', [StaffMobileController::class, 'lateCustomers'])->middleware('loan.permission:loan_management.overdue.view|loan_management.collection.view|loan_management.view');
        Route::get('/mobile/loan-form-options', [StaffMobileLoanController::class, 'options'])->middleware('loan.permission:loan_management.loans.create|loan_management.create');
        Route::get('/mobile/loans', [StaffMobileLoanController::class, 'index'])->middleware('loan.permission:loan_management.loans.view|loan_management.view');
        Route::post('/mobile/loans', [StaffMobileLoanController::class, 'store'])->middleware('loan.permission:loan_management.loans.create|loan_management.create');
        Route::post('/mobile/loans/preview-schedule', [StaffMobileLoanController::class, 'previewSchedule'])->middleware('loan.permission:loan_management.loans.create|loan_management.create');
        Route::get('/mobile/loans/{loanId}/print', [StaffMobileLoanController::class, 'print'])->middleware('loan.permission:loan_management.loans.view|loan_management.view')->name('loan-management.mobile.loans.print');
        Route::get('/mobile/loans/{loanId}', [StaffMobileLoanController::class, 'show'])->middleware('loan.permission:loan_management.loans.view|loan_management.view');
        Route::put('/mobile/loans/{loanId}', [StaffMobileLoanController::class, 'update'])->middleware('loan.permission:loan_management.loans.edit|loan_management.edit');
        Route::delete('/mobile/loans/{loanId}', [StaffMobileLoanController::class, 'destroy'])->middleware('loan.permission:loan_management.loans.delete|loan_management.delete');
        Route::get('/mobile/loan-customers/search', [StaffMobileLoanController::class, 'searchCustomers'])->middleware('loan.permission:loan_management.loans.create|loan_management.customers.view|loan_management.create');
        Route::get('/mobile/address/{level}', [StaffMobileLoanController::class, 'addressOptions'])->middleware('loan.permission:loan_management.loans.create|loan_management.loans.edit|loan_management.create|loan_management.edit');
        Route::get('/mobile/address-sync', [StaffMobileLoanController::class, 'addressSync'])->middleware('loan.permission:loan_management.loans.create|loan_management.loans.edit|loan_management.create|loan_management.edit');
        Route::post('/mobile/id-card/scan', [LoanCreateController::class, 'scanIdCard'])->middleware('loan.permission:loan_management.loans.create|loan_management.loans.edit|loan_management.create|loan_management.edit');
        Route::post('/mobile/product-photo/scan', [LoanCreateController::class, 'scanProductPhoto'])->middleware('loan.permission:loan_management.loans.create|loan_management.loans.edit|loan_management.create|loan_management.edit');
        Route::post('/mobile/payments', [StaffMobileActionController::class, 'receivePayment'])->middleware('loan.permission:loan_management.payments.create|loan_management.payment');
        Route::get('/mobile/loans/{loanId}/payments', [StaffMobileActionController::class, 'loanPayments'])->middleware('loan.permission:loan_management.payments.view|loan_management.payment|loan_management.view');
        Route::put('/mobile/payments/{paymentId}', [StaffMobileActionController::class, 'updatePayment'])->middleware('loan.permission:loan_management.payment|loan_management.edit');
        Route::delete('/mobile/payments/{paymentId}', [StaffMobileActionController::class, 'deletePayment'])->middleware('loan.permission:loan_management.payment|loan_management.delete');
        Route::post('/mobile/loans/{loanId}/telegram/connect-link', [StaffMobileActionController::class, 'telegramConnectLink'])->middleware('loan.permission:loan_management.customers.edit|loan_management.chat.reply|loan_management.edit');
        Route::post('/mobile/loans/{loanId}/telegram/message', [StaffMobileActionController::class, 'sendTelegramMessage'])->middleware('loan.permission:loan_management.chat.reply');
        Route::get('/telegram/chats', [LoanTelegramChatController::class, 'index'])->middleware('loan.permission:loan_management.chat.view');
        Route::post('/telegram/chats', [LoanTelegramChatController::class, 'store'])->middleware('loan.permission:loan_management.chat.reply');
        Route::get('/telegram/chats/{thread}', [LoanTelegramChatController::class, 'show'])->middleware('loan.permission:loan_management.chat.view');
        Route::post('/telegram/chats/{thread}/messages', [LoanTelegramChatController::class, 'sendMessage'])->middleware('loan.permission:loan_management.chat.reply');
        Route::post('/telegram/chats/{thread}/read', [LoanTelegramChatController::class, 'read'])->middleware('loan.permission:loan_management.chat.view');
        Route::post('/mobile/staff-location', [StaffMobileActionController::class, 'staffLocation'])->middleware('loan.permission:loan_management.customer_gps.manage|loan_management.gps.view');
        Route::post('/mobile/collection-visits', [StaffMobileActionController::class, 'collectionVisit'])->middleware('loan.permission:loan_management.collection_visits.view|loan_management.collection.view');

        Route::post('/files/upload', [LoanFileUploadController::class, 'upload'])->middleware('loan.permission:loan_management.loans.create|loan_management.loans.edit|loan_management.chat.reply|loan_management.payment');
        Route::post('/aba-payway/create', [LoanAbaPaywayController::class, 'create'])->middleware('loan.permission:loan_management.payments.create|loan_management.payment');
        Route::post('/aba-payway/check-status', [LoanAbaPaywayController::class, 'checkStatus'])->middleware('loan.permission:loan_management.payments.view|loan_management.payment|loan_management.view');
    });

    Route::prefix('customer')->middleware('auth:customer_loan_api')->group(function () {
        Route::post('/logout', [CustomerAppAuthController::class, 'logout']);
        Route::get('/profile', [CustomerAppProfileController::class, 'profile']);
        Route::post('/change-password', [CustomerAppAuthController::class, 'changePassword']);
        Route::get('/dashboard', [CustomerAppDashboardController::class, 'dashboard']);
        Route::get('/loans', [CustomerAppLoanController::class, 'loans']);
        Route::get('/loans/{loanId}', [CustomerAppLoanController::class, 'show']);
        Route::get('/loans/{loanId}/schedules', [CustomerAppLoanController::class, 'schedules']);
        Route::get('/payments', [CustomerAppPaymentController::class, 'payments']);
        Route::get('/schedules', [CustomerAppLoanController::class, 'allSchedules']);
        Route::get('/payments/summary', [CustomerAppPaymentController::class, 'summary']);
        Route::post('/payments/{paymentId}/proof', [CustomerAppPaymentController::class, 'uploadProof']);
        Route::post('/upload-payment-proof', [CustomerAppPaymentController::class, 'uploadPaymentProof']);
        Route::post('/location', [CustomerLocationTrackingController::class, 'update']);
        Route::get('/location/status', [CustomerLocationTrackingController::class, 'status']);
        Route::post('/location/enable', [CustomerLocationTrackingController::class, 'enable']);
        Route::post('/location/disable', [CustomerLocationTrackingController::class, 'disable']);
        Route::get('/chats', [CustomerChatController::class, 'index']);
        Route::post('/chats', [CustomerChatController::class, 'store']);
        Route::get('/chats/{thread}', [CustomerChatController::class, 'show']);
        Route::post('/chats/{thread}/messages', [CustomerChatController::class, 'sendMessage']);
        Route::post('/chats/{thread}/read', [CustomerChatController::class, 'read']);
        Route::post('/chats/{thread}/typing', [CustomerChatController::class, 'typing']);

        Route::post('/aba-payway/create', [LoanAbaPaywayController::class, 'create']);
        Route::post('/aba-payway/check-status', [LoanAbaPaywayController::class, 'checkStatus']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('/chats', [LoanChatController::class, 'index']);
        Route::post('/chats', [LoanChatController::class, 'store']);
        Route::get('/chats/{thread}', [LoanChatController::class, 'show']);
        Route::delete('/chats/{thread}', [LoanChatController::class, 'destroy']);
        Route::post('/chats/{thread}/messages', [LoanChatController::class, 'sendMessage']);
        Route::post('/chats/{thread}/assign', [LoanChatController::class, 'assign']);
        Route::post('/chats/{thread}/transfer', [LoanChatController::class, 'transfer']);
        Route::post('/chats/{thread}/read', [LoanChatController::class, 'read']);
        Route::post('/chats/{thread}/typing', [LoanChatController::class, 'typing']);
        Route::post('/chats/{thread}/close', [LoanChatController::class, 'close']);
        Route::post('/chats/{thread}/reopen', [LoanChatController::class, 'reopen']);
        Route::post('/chats/{thread}/pin', [LoanChatController::class, 'pin']);
        Route::post('/chats/{thread}/mute', [LoanChatController::class, 'mute']);
    });
});

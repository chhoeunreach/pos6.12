<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\AuthController;
use Modules\LoanManagement\Http\Controllers\LoanAbaPaywayController;
use Modules\LoanManagement\Http\Controllers\LoanFileUploadController;
use Modules\LoanManagement\Http\Controllers\CustomerAppAuthController;
use Modules\LoanManagement\Http\Controllers\CustomerAppDashboardController;
use Modules\LoanManagement\Http\Controllers\CustomerAppLoanController;
use Modules\LoanManagement\Http\Controllers\CustomerAppPaymentController;
use Modules\LoanManagement\Http\Controllers\CustomerAppProfileController;
use Modules\LoanManagement\Http\Controllers\CustomerChatController;
use Modules\LoanManagement\Http\Controllers\CustomerLocationTrackingController;
use Modules\LoanManagement\Http\Controllers\LoanChatController;
use Modules\LoanManagement\Http\Controllers\PublicAppController;
use Modules\LoanManagement\Http\Controllers\StaffMobileActionController;
use Modules\LoanManagement\Http\Controllers\StaffMobileController;

Route::prefix('loan-management')->group(function () {
    Route::get('/app-settings', [PublicAppController::class, 'appSettings']);
    Route::get('/app-version', [PublicAppController::class, 'appVersion']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/customer/login', [CustomerAppAuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/mobile/dashboard', [StaffMobileController::class, 'dashboard']);
        Route::get('/mobile/customers', [StaffMobileController::class, 'customers']);
        Route::get('/mobile/customers/{id}', [StaffMobileController::class, 'customerShow']);
        Route::get('/mobile/late-customers', [StaffMobileController::class, 'lateCustomers']);
        Route::post('/mobile/payments', [StaffMobileActionController::class, 'receivePayment']);
        Route::post('/mobile/staff-location', [StaffMobileActionController::class, 'staffLocation']);
        Route::post('/mobile/collection-visits', [StaffMobileActionController::class, 'collectionVisit']);

        Route::post('/files/upload', [LoanFileUploadController::class, 'upload']);
        Route::post('/aba-payway/create', [LoanAbaPaywayController::class, 'create']);
        Route::post('/aba-payway/check-status', [LoanAbaPaywayController::class, 'checkStatus']);
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
        Route::post('/chats/{thread}/close', [CustomerChatController::class, 'close']);

        Route::post('/aba-payway/create', [LoanAbaPaywayController::class, 'create']);
        Route::post('/aba-payway/check-status', [LoanAbaPaywayController::class, 'checkStatus']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('/chats', [LoanChatController::class, 'index']);
        Route::post('/chats', [LoanChatController::class, 'store']);
        Route::get('/chats/{thread}', [LoanChatController::class, 'show']);
        Route::post('/chats/{thread}/messages', [LoanChatController::class, 'sendMessage']);
        Route::post('/chats/{thread}/assign', [LoanChatController::class, 'assign']);
        Route::post('/chats/{thread}/read', [LoanChatController::class, 'read']);
        Route::post('/chats/{thread}/close', [LoanChatController::class, 'close']);
        Route::post('/chats/{thread}/reopen', [LoanChatController::class, 'reopen']);
    });
});

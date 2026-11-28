<?php

use Illuminate\Support\Facades\Route;
use Modules\LoanManagement\Http\Controllers\Api\AuthProfileController;
use Modules\LoanManagement\Http\Controllers\Api\CustomerLocationRequestController;
use Modules\LoanManagement\Http\Controllers\Api\CustomerPaymentController;
use Modules\LoanManagement\Http\Controllers\Api\FeedController;
use Modules\LoanManagement\Http\Controllers\Api\NotificationController;
use Modules\LoanManagement\Http\Controllers\Api\StaffLocationRequestController;
use Modules\LoanManagement\Http\Controllers\Api\StaffPaymentProofController;
use Modules\LoanManagement\Http\Controllers\Api\TelegramAuthController;
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
        Route::post('/customer/register', [CustomerAppAuthController::class, 'register']);
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
        Route::get('/telegram/chat-files/{file}', [LoanChatController::class, 'serveFile'])->whereNumber('file');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('/telegram/chat-files/{file}', [LoanChatController::class, 'serveFile'])->whereNumber('file')->middleware('loan.permission:loan_management.chat.view');
        Route::get('/chats', [LoanChatController::class, 'index']);
        Route::post('/chats', [LoanChatController::class, 'store']);
        Route::get('/chats/{thread}', [LoanChatController::class, 'show']);
        Route::delete('/chats/{thread}', [LoanChatController::class, 'destroy']);
        Route::post('/chats/{thread}/messages', [LoanChatController::class, 'sendMessage']);
        Route::post('/chats/{thread}/assign', [LoanChatController::class, 'assign']);
        Route::post('/chats/{thread}/transfer', [LoanChatController::class, 'transfer']);
        Route::post('/chats/{thread}/read', [LoanChatController::class, 'read']);
        Route::post('/chats/{thread}/unread', [LoanChatController::class, 'unread']);
        Route::post('/chats/{thread}/typing', [LoanChatController::class, 'typing']);
        Route::post('/chats/{thread}/close', [LoanChatController::class, 'close']);
        Route::post('/chats/{thread}/reopen', [LoanChatController::class, 'reopen']);
        Route::post('/chats/{thread}/pin', [LoanChatController::class, 'pin']);
        Route::post('/chats/{thread}/mute', [LoanChatController::class, 'mute']);

        // Staff Role, Permission & Accessible Branches
        Route::get('/me', [AuthProfileController::class, 'staffProfile']);

        // Staff Payment Proof Verification (Branch Scoped)
        Route::get('/payments/proofs', [StaffPaymentProofController::class, 'index']);
        Route::post('/payments/proofs/{submission}/review', [StaffPaymentProofController::class, 'review']);

        // Staff Notifications & Device Tokens
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/device-tokens', [NotificationController::class, 'registerToken']);
        Route::delete('/device-tokens', [NotificationController::class, 'deactivateToken']);

        // Staff Live Location Requests (Branch & Permission Scoped)
        Route::post('/location-requests', [StaffLocationRequestController::class, 'store']);
        Route::get('/location-requests/{locationRequest}', [StaffLocationRequestController::class, 'show']);
        Route::post('/location-requests/{locationRequest}/stop', [StaffLocationRequestController::class, 'stop']);
        Route::get('/customers/{customer}/location-requests', [StaffLocationRequestController::class, 'customerHistory']);

        // Staff News Feed Management & Moderation
        Route::get('/feed', [FeedController::class, 'staffIndex']);
        Route::post('/feed', [FeedController::class, 'store']);
        Route::delete('/feed/{post}', [FeedController::class, 'destroy']);
        Route::post('/feed/presign-upload', [FeedController::class, 'presignUpload']);
        Route::get('/feed/reports', [FeedController::class, 'reports']);
        Route::post('/feed/reports/{report}/resolve', [FeedController::class, 'resolveReport']);
    });

    // Public bank/KHQR & Telegram Bot webhooks.
    Route::post('/webhooks/khqr', [CustomerPaymentController::class, 'khqrWebhook']);
    Route::post('/webhooks/telegram-auth', [TelegramAuthController::class, 'webhook']);

    // Public News Feed & Telegram Deep-Link Auth (accessible without login, rate-limited).
    Route::prefix('public')->middleware(['throttle:60,1'])->group(function () {
        Route::get('/feed', [FeedController::class, 'publicIndex']);
        Route::get('/feed/{post}', [FeedController::class, 'publicShow']);
        Route::get('/feed/{post}/comments', [FeedController::class, 'publicComments']);
        Route::post('/feed/{post}/view', [FeedController::class, 'recordView']);

        Route::post('/auth/telegram/init', [TelegramAuthController::class, 'init']);
        Route::get('/auth/telegram/{token}/status', [TelegramAuthController::class, 'status']);
    });

    // Customer Mobile Feed, KHQR/Proof Payments, Notifications & Consent Location Sharing.
    Route::prefix('customer')->middleware('auth:customer_loan_api')->group(function () {
        Route::post('/feed', [FeedController::class, 'customerStore']);
        Route::delete('/feed/{post}', [FeedController::class, 'customerDestroy']);
        Route::post('/feed/{post}/like', [FeedController::class, 'toggleLike']);
        Route::post('/feed/{post}/bookmark', [FeedController::class, 'toggleBookmark']);
        Route::post('/feed/{post}/share', [FeedController::class, 'sharePost']);
        Route::post('/feed/{post}/comments', [FeedController::class, 'storeComment']);
        Route::post('/feed/follow', [FeedController::class, 'toggleFollow']);
        Route::post('/feed/report', [FeedController::class, 'report']);
        Route::post('/feed/block', [FeedController::class, 'blockUser']);
        Route::post('/feed/presign-upload', [FeedController::class, 'presignUpload']);

        Route::get('/payment-submissions', [CustomerPaymentController::class, 'index']);
        Route::post('/payments/khqr', [CustomerPaymentController::class, 'generateKhqr']);
        Route::get('/payments/{reference}/status', [CustomerPaymentController::class, 'status']);
        Route::post('/payments/proof', [CustomerPaymentController::class, 'submitProof']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/device-tokens', [NotificationController::class, 'registerToken']);
        Route::delete('/device-tokens', [NotificationController::class, 'deactivateToken']);

        Route::get('/location-requests/pending', [CustomerLocationRequestController::class, 'pending']);
        Route::post('/location-requests/{locationRequest}/respond', [CustomerLocationRequestController::class, 'respond']);
        Route::post('/location-requests/{locationRequest}/ping', [CustomerLocationRequestController::class, 'ping']);
        Route::post('/location-requests/{locationRequest}/stop', [CustomerLocationRequestController::class, 'stop']);
    });
});

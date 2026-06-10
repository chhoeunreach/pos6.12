<?php

// use App\Http\Controllers\BusinessController;
// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::get('/pricing', ['\Modules\Superadmin\Http\Controllers\PricingController', 'index'])->name('pricing');

Route::middleware('web', 'auth', 'language', 'AdminSidebarMenu', 'superadmin')->prefix('superadmin')->group(function () {
    Route::get('/install', ['\Modules\Superadmin\Http\Controllers\InstallController', 'index']);
    Route::post('/install', ['\Modules\Superadmin\Http\Controllers\InstallController', 'install']);
    Route::get('/install/update', ['\Modules\Superadmin\Http\Controllers\InstallController', 'update']);
    Route::get('/install/uninstall', ['\Modules\Superadmin\Http\Controllers\InstallController', 'uninstall']);
    Route::post('/install/update', ['\Modules\Superadmin\Http\Controllers\InstallController', 'updateExecute']);

    Route::get('/', ['\Modules\Superadmin\Http\Controllers\SuperadminController', 'index']);
    Route::get('/stats', ['\Modules\Superadmin\Http\Controllers\SuperadminController', 'stats']);

    Route::get('/{business_id}/toggle-active/{is_active}', ['\Modules\Superadmin\Http\Controllers\BusinessController', 'toggleActive']);

    Route::get('/users/{business_id}', ['\Modules\Superadmin\Http\Controllers\BusinessController', 'usersList']);
    Route::post('/update-password', ['\Modules\Superadmin\Http\Controllers\BusinessController', 'updatePassword']);

    Route::resource('/business', '\Modules\Superadmin\Http\Controllers\BusinessController');
    Route::get('/business/{id}/destroy', ['\Modules\Superadmin\Http\Controllers\BusinessController', 'destroy']);

    Route::resource('/packages', '\Modules\Superadmin\Http\Controllers\PackagesController');
    Route::get('/packages/{id}/destroy', ['\Modules\Superadmin\Http\Controllers\PackagesController', 'destroy']);

    Route::resource('/coupons', '\Modules\Superadmin\Http\Controllers\CouponController');
    Route::get('/coupons/{id}/destroy', ['\Modules\Superadmin\Http\Controllers\CouponController', 'destroy']);
    
    Route::get('/settings', ['\Modules\Superadmin\Http\Controllers\SuperadminSettingsController', 'edit']);
    Route::put('/settings', ['\Modules\Superadmin\Http\Controllers\SuperadminSettingsController', 'update']);
    Route::get('/edit-subscription/{id}', ['\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController', 'editSubscription']);
    Route::post('/update-subscription', ['\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController', 'updateSubscription']);
    Route::resource('/superadmin-subscription', '\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController');

    Route::get('/communicator', ['\Modules\Superadmin\Http\Controllers\CommunicatorController', 'index']);
    Route::post('/communicator/send', ['\Modules\Superadmin\Http\Controllers\CommunicatorController', 'send']);
    Route::get('/communicator/get-history', ['\Modules\Superadmin\Http\Controllers\CommunicatorController', 'getHistory']);

    Route::resource('/frontend-pages', '\Modules\Superadmin\Http\Controllers\PageController');
});

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    //Routes related to paypal checkout
    Route::post('/paypal-express-checkout', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'paypalExpressCheckout'])->name('paypalExpressCheckout');

    Route::post('/capture-paypal-order', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'capturePaypalOrder'])->name('capturePaypalOrder');


    Route::get('/subscription/post-flutterwave-payment', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'postFlutterwavePaymentCallback']);

    Route::post('/subscription/pay-stack', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'getRedirectToPaystack']);
    Route::get('/subscription/post-payment-pay-stack-callback', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'postPaymentPaystackCallback']);

    //Routes related to pesapal checkout
    Route::get('/subscription/{package_id}/pesapal-callback', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'pesapalCallback'])->name('pesapalCallback');

    Route::get('/subscription/{package_id}/pay', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'pay']);
    Route::any('/subscription/{package_id?}/confirm', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'confirm'])->name('subscription-confirm');
    Route::get('/all-subscriptions', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'allSubscriptions']);

    Route::get('/subscription/{package_id}/register-pay', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'registerPay'])->name('register-pay');

    Route::resource('/subscription', '\Modules\Superadmin\Http\Controllers\SubscriptionController');

    Route::get('/subscription/{subcription_id}/force-active', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'forceActive'])->name('force-active');
    Route::get('/myfatoorah-callback', ['\Modules\Superadmin\Http\Controllers\SubscriptionController', 'myfatoorahcallback'])->name('myfatoorah_callback');

});

Route::get('/page/{slug}', ['\Modules\Superadmin\Http\Controllers\PageController', 'showPage'])->name('frontend-pages');

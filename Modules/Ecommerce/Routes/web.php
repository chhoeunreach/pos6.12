<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\Api\BrandApiController;
use Modules\Ecommerce\Http\Controllers\Api\BusinessApiController;
use Modules\Ecommerce\Http\Controllers\Api\ContactApiController;
use Modules\Ecommerce\Http\Controllers\Api\ProductApiController;
use Modules\Ecommerce\Http\Controllers\Api\SellPosApiController;
use Modules\Ecommerce\Http\Controllers\Api\TaxonomyApiController;
use Modules\Ecommerce\Http\Controllers\EcomApiSettingController;
use Modules\Ecommerce\Http\Middleware\EcomApi;

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->group(function () {
        Route::get('/ecommerce-api-settings', [EcomApiSettingController::class, 'index'])->name('ecom-api-settings.index');
        Route::post('/ecommerce-api-settings', [EcomApiSettingController::class, 'store'])->name('ecom-api-settings.store');
        Route::post('/ecommerce-api-settings/{id}/deactivate', [EcomApiSettingController::class, 'deactivate'])->name('ecom-api-settings.deactivate');
        Route::post('/ecommerce-api-settings/{id}/regenerate', [EcomApiSettingController::class, 'regenerate'])->name('ecom-api-settings.regenerate');
    });

Route::middleware([EcomApi::class])->prefix('api/ecom')->group(function () {
    Route::get('products/{id?}', [ProductApiController::class, 'getProductsApi']);
    Route::get('categories', [TaxonomyApiController::class, 'getCategoriesApi']);
    Route::get('brands', [BrandApiController::class, 'getBrandsApi']);
    Route::post('customers', [ContactApiController::class, 'postCustomersApi']);
    Route::get('settings', [BusinessApiController::class, 'getEcomSettings']);
    Route::get('variations', [ProductApiController::class, 'getVariationsApi']);
    Route::post('orders', [SellPosApiController::class, 'placeOrdersApi']);
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\FixStockMismatch\Http\Controllers\StockMismatchController;

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('stock-mismatch')
    ->group(function () {
        Route::get('/', [StockMismatchController::class, 'index']);
        Route::get('/data', [StockMismatchController::class, 'checkMismatch']);
        Route::get('/products/search', [StockMismatchController::class, 'searchProduct']);
        Route::get('/{variation_id}/{location_id}/detail', [StockMismatchController::class, 'detail']);
        Route::post('/fix', [StockMismatchController::class, 'fix']);
        Route::post('/fix-all', [StockMismatchController::class, 'fixAll']);

        // Install / update / uninstall
        Route::get('/install', [Modules\FixStockMismatch\Http\Controllers\InstallController::class, 'index']);
        Route::post('/install', [Modules\FixStockMismatch\Http\Controllers\InstallController::class, 'install']);
        Route::get('/install/uninstall', [Modules\FixStockMismatch\Http\Controllers\InstallController::class, 'uninstall']);
        Route::get('/install/update', [Modules\FixStockMismatch\Http\Controllers\InstallController::class, 'update']);
    });

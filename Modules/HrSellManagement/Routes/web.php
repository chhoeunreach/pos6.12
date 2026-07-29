<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('hr-sell')->as('hr-sell.')->group(function () {
    Route::get('/install', ['\Modules\HrSellManagement\Http\Controllers\InstallController', 'install'])->name('install');
});

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('hr-sell')
    ->as('hr-sell.')
    ->group(function () {
        Route::get('/', ['\Modules\HrSellManagement\Http\Controllers\DashboardController', 'index'])->name('dashboard');
        Route::get('/sales', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'index'])->name('sales.index');
        Route::post('/sales/link', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'link'])->name('sales.link');
        Route::get('/sales/{hrSell}', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'show'])->name('sales.show');
        Route::post('/sales/{hrSell}', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'update'])->name('sales.update');
        Route::post('/sales/{hrSell}/approve', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'approve'])->name('sales.approve');
        Route::post('/sales/{hrSell}/notes', ['\Modules\HrSellManagement\Http\Controllers\HrSellController', 'storeNote'])->name('sales.notes.store');
        Route::get('/reports', ['\Modules\HrSellManagement\Http\Controllers\ReportController', 'index'])->name('reports.index');
        Route::get('/reports/export', ['\Modules\HrSellManagement\Http\Controllers\ReportController', 'export'])->name('reports.export');
        Route::get('/settings', ['\Modules\HrSellManagement\Http\Controllers\SettingsController', 'index'])->name('settings.index');
        Route::post('/settings', ['\Modules\HrSellManagement\Http\Controllers\SettingsController', 'update'])->name('settings.update');
    });

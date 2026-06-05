<?php

use Illuminate\Support\Facades\Route;
use Modules\LocalCashierReport\Http\Controllers\InstallController;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;

Route::middleware(['web', 'auth'])->prefix('local-cashier-report')->group(function () {
    Route::get('/install', [InstallController::class, 'index']);
    Route::post('/install', [InstallController::class, 'install']);
    Route::get('/install/uninstall', [InstallController::class, 'uninstall']);
    Route::get('/install/update', [InstallController::class, 'update']);
});

Route::middleware([
    'web',
    'auth',
    'SetSessionData',
    'language',
    'timezone',
    'can:local_cashier_report.view',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->prefix('local-cashier-report')->group(function () {
    Route::get('/', [LocalCashierReportController::class, 'index'])->name('local-cashier-report.index');
    Route::get('/export', [LocalCashierReportController::class, 'export'])->name('local-cashier-report.export');
    Route::get('/print', [LocalCashierReportController::class, 'print'])->name('local-cashier-report.print');
});

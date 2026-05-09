<?php

use Illuminate\Support\Facades\Route;
use Modules\LocalCashierReport\Http\Controllers\LocalCashierReportController;

Route::middleware([
    'web',
    'auth',
    'SetSessionData',
    'language',
    'timezone',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->prefix('local-cashier-report')->group(function () {
    Route::get('/', [LocalCashierReportController::class, 'index'])->name('local-cashier-report.index');
    Route::get('/export', [LocalCashierReportController::class, 'export'])->name('local-cashier-report.export');
    Route::get('/print', [LocalCashierReportController::class, 'print'])->name('local-cashier-report.print');
});

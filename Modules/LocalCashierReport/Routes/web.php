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
    Route::get('/datatable', [LocalCashierReportController::class, 'datatable'])->name('local-cashier-report.datatable');
    Route::get('/export/excel', [LocalCashierReportController::class, 'exportExcel'])->name('local-cashier-report.export.excel');
    Route::get('/export/pdf', [LocalCashierReportController::class, 'exportPdf'])->name('local-cashier-report.export.pdf');
});

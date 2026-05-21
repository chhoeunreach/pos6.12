<?php

use Illuminate\Support\Facades\Route;
use Modules\WarrantyCardPrint\Http\Controllers\InstallController;
use Modules\WarrantyCardPrint\Http\Controllers\WarrantyCardPrintController;

Route::middleware(['web', 'auth'])->prefix('warranty-card-print')->group(function () {
    Route::get('/install', [InstallController::class, 'index']);
    Route::post('/install', [InstallController::class, 'install']);
    Route::get('/install/uninstall', [InstallController::class, 'uninstall']);
    Route::get('/install/update', [InstallController::class, 'update']);
});

Route::middleware([
    'web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin',
])->prefix('warranty-card-print')->group(function () {
    Route::get('/', [WarrantyCardPrintController::class, 'create'])->name('warranty-card-print.create');
});

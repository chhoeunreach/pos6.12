<?php

use Illuminate\Support\Facades\Route;

// Install / update / uninstall (available even before "installed" flag is set)
Route::middleware(['web', 'auth'])
    ->group(function () {
        Route::get('/custom-backup/install', [Modules\CustomBackup\Http\Controllers\InstallController::class, 'index']);
        Route::post('/custom-backup/install', [Modules\CustomBackup\Http\Controllers\InstallController::class, 'install']);
        Route::get('/custom-backup/install/uninstall', [Modules\CustomBackup\Http\Controllers\InstallController::class, 'uninstall']);
        Route::get('/custom-backup/install/update', [Modules\CustomBackup\Http\Controllers\InstallController::class, 'update']);
    });

// Feature routes (only after installed)
Route::middleware([
    'web',
    'auth',
    'SetSessionData',
    'language',
    'timezone',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->group(function () {
    Route::get('/custom-backup', [Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'index']);
    Route::post('/custom-backup/export', [Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'export']);
});

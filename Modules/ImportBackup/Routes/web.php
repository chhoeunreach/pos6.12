<?php

use Illuminate\Support\Facades\Route;

// Install / update / uninstall (available even before "installed" flag is set)
Route::middleware(['web', 'auth'])
    ->group(function () {
        Route::get('/import-backup/install', [Modules\ImportBackup\Http\Controllers\InstallController::class, 'index']);
        Route::post('/import-backup/install', [Modules\ImportBackup\Http\Controllers\InstallController::class, 'install']);
        Route::get('/import-backup/install/uninstall', [Modules\ImportBackup\Http\Controllers\InstallController::class, 'uninstall']);
        Route::get('/import-backup/install/update', [Modules\ImportBackup\Http\Controllers\InstallController::class, 'update']);
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
    Route::get('/custom-backup/import', [\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'showImportForm']);
    Route::post('/custom-backup/import', [\Modules\CustomBackup\Http\Controllers\CustomBackupController::class, 'import']);
});

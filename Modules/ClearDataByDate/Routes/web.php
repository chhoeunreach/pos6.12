<?php

use Modules\ClearDataByDate\Http\Controllers\ClearDataByDateController;
use Modules\ClearDataByDate\Http\Controllers\InstallController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('clear-data-by-date')
    ->group(function () {
        Route::get('/', [ClearDataByDateController::class, 'index'])
            ->name('clear_data_by_date.index')
            ->middleware('admin_or_superadmin');

        Route::post('/preview', [ClearDataByDateController::class, 'preview'])
            ->name('clear_data_by_date.preview')
            ->middleware('admin_or_superadmin');

        Route::post('/delete', [ClearDataByDateController::class, 'destroy'])
            ->name('clear_data_by_date.delete')
            ->middleware('admin_or_superadmin');

        // Module install/update/uninstall (used by Install > Modules page)
        Route::get('/install', [InstallController::class, 'index'])->middleware('admin_or_superadmin');
        Route::post('/install', [InstallController::class, 'install'])->middleware('admin_or_superadmin');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->middleware('admin_or_superadmin');
        Route::get('/install/update', [InstallController::class, 'update'])->middleware('admin_or_superadmin');
    });

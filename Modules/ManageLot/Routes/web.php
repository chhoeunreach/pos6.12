<?php

use Modules\ManageLot\Http\Controllers\ManageLotController;
use Modules\ManageLot\Http\Controllers\InstallController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('manage-lot')
    ->group(function () {
        // REPORT/VIEW ONLY: GET pages + DataTables GET ajax reads
        Route::get('/', [ManageLotController::class, 'index'])
            ->name('manage_lot.index');
        Route::get('/data', [ManageLotController::class, 'indexData'])
            ->name('manage_lot.data');

        Route::get('/lot-search', [ManageLotController::class, 'lotSearch'])
            ->name('manage_lot.lot_search');

        Route::get('/{lot_id}/history', [ManageLotController::class, 'history'])
            ->whereNumber('lot_id')
            ->name('manage_lot.history');
        Route::get('/{lot_id}/history/data', [ManageLotController::class, 'historyData'])
            ->whereNumber('lot_id')
            ->name('manage_lot.history_data');

        // Module install/update/uninstall (used by Install > Modules page)
        Route::get('/install', [InstallController::class, 'index'])->middleware('superadmin');
        Route::post('/install', [InstallController::class, 'install'])->middleware('superadmin');
        Route::get('/install/uninstall', [InstallController::class, 'uninstall'])->middleware('superadmin');
        Route::get('/install/update', [InstallController::class, 'update'])->middleware('superadmin');
    });

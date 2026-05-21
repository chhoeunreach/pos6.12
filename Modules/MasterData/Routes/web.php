<?php

use Illuminate\Support\Facades\Route;
use Modules\MasterData\Http\Controllers\InstallController;
use Modules\MasterData\Http\Controllers\MasterDataController;

Route::middleware(['web', 'auth'])->prefix('master-data')->group(function () {
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
    'AdminSidebarMenu',
    'CheckUserLogin',
])->group(function () {
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
    Route::post('/master-data/export', [MasterDataController::class, 'export'])->name('master-data.export');
    Route::post('/master-data/preview', [MasterDataController::class, 'preview'])->name('master-data.preview');
    Route::post('/master-data/import', [MasterDataController::class, 'import'])->name('master-data.import');
});


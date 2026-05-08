<?php

use Illuminate\Support\Facades\Route;
use Modules\UserBackupRestore\Http\Controllers\UserBackupRestoreController;

Route::middleware([
    'web',
    'auth',
    'SetSessionData',
    'language',
    'timezone',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->group(function () {
    Route::get('/user-backup-restore', [UserBackupRestoreController::class, 'index'])->name('user-backup-restore.index');
    Route::post('/user-backup-restore/export', [UserBackupRestoreController::class, 'export'])->name('user-backup-restore.export');
    Route::post('/user-backup-restore/preview', [UserBackupRestoreController::class, 'preview'])->name('user-backup-restore.preview');
    Route::post('/user-backup-restore/import', [UserBackupRestoreController::class, 'import'])->name('user-backup-restore.import');
});


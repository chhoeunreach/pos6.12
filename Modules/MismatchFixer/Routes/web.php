<?php

use Illuminate\Support\Facades\Route;
use Modules\MismatchFixer\Http\Controllers\DataController;
use Modules\MismatchFixer\Http\Controllers\InstallController;
use Modules\MismatchFixer\Http\Controllers\MismatchFixerController;

Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->group(function () {
        Route::get('/mismatch-fixer', [MismatchFixerController::class, 'index'])->name('mismatch-fixer.index');
        Route::post('/mismatch-fixer/scan', [MismatchFixerController::class, 'scan'])->name('mismatch-fixer.scan');
        Route::post('/mismatch-fixer/fix/{purchase_line_id}', [MismatchFixerController::class, 'fix'])->name('mismatch-fixer.fix');
        Route::post('/mismatch-fixer/bulk-fix', [MismatchFixerController::class, 'bulkFix'])->name('mismatch-fixer.bulk-fix');
        Route::get('/mismatch-fixer/logs', [MismatchFixerController::class, 'logs'])->name('mismatch-fixer.logs');
        Route::get('/mismatch-fixer/settings', [MismatchFixerController::class, 'settings'])->name('mismatch-fixer.settings');

        Route::get('/mismatch-fixer/data/menu', [DataController::class, 'modifyAdminMenu']);
        Route::get('/mismatch-fixer/install', [InstallController::class, 'index'])->middleware('superadmin');
        Route::post('/mismatch-fixer/install', [InstallController::class, 'install'])->middleware('superadmin');
        Route::get('/mismatch-fixer/install/uninstall', [InstallController::class, 'uninstall'])->middleware('superadmin');
        Route::get('/mismatch-fixer/install/update', [InstallController::class, 'update'])->middleware('superadmin');
    });

<?php

use Illuminate\Support\Facades\Route;
use Modules\SmartStockInventory\Http\Controllers\DashboardController;
use Modules\SmartStockInventory\Http\Controllers\ImeiController;
use Modules\SmartStockInventory\Http\Controllers\InventoryCountController;
use Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController;
use Modules\SmartStockInventory\Http\Controllers\LotController;
use Modules\SmartStockInventory\Http\Controllers\MismatchController;
use Modules\SmartStockInventory\Http\Controllers\MovementController;
use Modules\SmartStockInventory\Http\Controllers\SettingsController;
use Modules\SmartStockInventory\Http\Controllers\VerificationController;

Route::middleware([
    'web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'ssi.access',
])->prefix('smart-stock-inventory')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('ssi.dashboard');
    Route::get('/dashboard/detail/{metric}', [DashboardController::class, 'detail'])->name('ssi.dashboard.detail');
    Route::get('/dashboard/export', [DashboardController::class, 'export'])->name('ssi.dashboard.export');
    Route::get('/dashboard/print', [DashboardController::class, 'print'])->name('ssi.dashboard.print');
    Route::get('/dashboard/refresh', [DashboardController::class, 'refresh'])->name('ssi.dashboard.refresh');

    Route::get('/count', [InventoryCountController::class, 'index'])->name('ssi.count.index');
    Route::get('/count/enterprise', [InventoryEnterpriseController::class, 'index'])->name('ssi.count.enterprise');
    Route::post('/count/enterprise/session', [InventoryEnterpriseController::class, 'createSession'])->name('ssi.count.enterprise.session');
    Route::post('/count/enterprise/session/{session}/assign', [InventoryEnterpriseController::class, 'assignCounter'])->name('ssi.count.enterprise.assign');
    Route::post('/count/enterprise/session/{session}/line', [InventoryEnterpriseController::class, 'countLine'])->name('ssi.count.enterprise.line');
    Route::post('/count/enterprise/session/{session}/line/{line}/verify', [InventoryEnterpriseController::class, 'verifyLine'])->name('ssi.count.enterprise.verify');
    Route::post('/count/enterprise/session/{session}/approve', [InventoryEnterpriseController::class, 'approve'])->name('ssi.count.enterprise.approve');
    Route::post('/count/enterprise/session/{session}/freeze', [InventoryEnterpriseController::class, 'freeze'])->name('ssi.count.enterprise.freeze');
    Route::get('/count/enterprise/session/{session}/dashboard', [InventoryEnterpriseController::class, 'dashboard'])->name('ssi.count.enterprise.dashboard');
    Route::get('/count/enterprise/session/{session}/adjustment-preview', [InventoryEnterpriseController::class, 'adjustmentPreview'])->name('ssi.count.enterprise.adjustment_preview');
    Route::get('/count/enterprise/session/{session}/mobile', [InventoryEnterpriseController::class, 'mobile'])->name('ssi.count.enterprise.mobile');
    Route::get('/count/reports', [InventoryEnterpriseController::class, 'reports'])->name('ssi.count.reports');
    Route::post('/count/store', [InventoryCountController::class, 'store'])->name('ssi.count.store');
    Route::post('/count/session/update/{session}', [InventoryCountController::class, 'updateSession'])->name('ssi.count.session.update');
    Route::post('/count/line/update/{line}', [InventoryCountController::class, 'updateLine'])->name('ssi.count.line.update');
    Route::delete('/count/session/delete/{session}', [InventoryCountController::class, 'deleteSession'])->name('ssi.count.session.delete');
    Route::delete('/count/line/delete/{line}', [InventoryCountController::class, 'deleteLine'])->name('ssi.count.line.delete');
    Route::delete('/count/imported/delete', [InventoryCountController::class, 'deleteImported'])->name('ssi.count.imported.delete');
    Route::post('/count/complete', [InventoryCountController::class, 'complete'])->name('ssi.count.complete');
    Route::get('/count/export', [InventoryCountController::class, 'export'])->name('ssi.count.export');
    Route::get('/count/print/{session}', [InventoryCountController::class, 'print'])->name('ssi.count.print');
    Route::post('/count/import', [InventoryCountController::class, 'import'])->name('ssi.count.import');

    Route::get('/verification', [VerificationController::class, 'index'])->name('ssi.verification.index');
    Route::post('/verification/approve/{session}', [VerificationController::class, 'approve'])->name('ssi.verification.approve');
    Route::post('/verification/reject/{session}', [VerificationController::class, 'reject'])->name('ssi.verification.reject');
    Route::post('/verification/recount/{session}', [VerificationController::class, 'recount'])->name('ssi.verification.recount');
    Route::get('/verification/export', [VerificationController::class, 'export'])->name('ssi.verification.export');
    Route::get('/verification/print', [VerificationController::class, 'print'])->name('ssi.verification.print');
    Route::get('/mismatch', [MismatchController::class, 'index'])->name('ssi.mismatch.index');
    Route::post('/mismatch/preview-fix', [MismatchController::class, 'previewFix'])->name('ssi.mismatch.preview_fix');
    Route::post('/mismatch/fix-auto', [MismatchController::class, 'fixAuto'])->name('ssi.mismatch.fix_auto');
    Route::post('/mismatch/rollback', [MismatchController::class, 'rollback'])->name('ssi.mismatch.rollback');
    Route::get('/mismatch/logs', [MismatchController::class, 'logs'])->name('ssi.mismatch.logs');
    Route::get('/fix-logs', [MismatchController::class, 'fixLogs'])->name('ssi.fix_logs');
    Route::delete('/fix-logs/delete/{id}', [MismatchController::class, 'deleteLog'])->name('ssi.fix_logs.delete');

    Route::get('/movement', [MovementController::class, 'index'])->name('ssi.movement.index');
    Route::get('/movement/search-sku', [MovementController::class, 'searchSku'])->name('ssi.movement.search_sku');
    Route::get('/movement/export', [MovementController::class, 'export'])->name('ssi.movement.export');
    Route::get('/movement/print', [MovementController::class, 'print'])->name('ssi.movement.print');
    Route::get('/movement/{transaction}/edit-modal', [MovementController::class, 'editModal'])->name('ssi.movement.edit_modal');
    Route::post('/movement/{transaction}/edit-modal', [MovementController::class, 'updateModal'])->name('ssi.movement.update_modal');
    Route::post('/movement/{transaction}/void', [MovementController::class, 'voidTransaction'])->name('ssi.movement.void');
    Route::post('/movement/{transaction}/restore', [MovementController::class, 'restoreTransaction'])->name('ssi.movement.restore');

    Route::get('/imei', [ImeiController::class, 'index'])->name('ssi.imei.index');
    Route::get('/imei/export', [ImeiController::class, 'export'])->name('ssi.imei.export');
    Route::get('/imei/history/{imei}', [ImeiController::class, 'history'])->name('ssi.imei.history');
    Route::post('/imei/update', [ImeiController::class, 'updateStatus'])->name('ssi.imei.update');
    Route::get('/lot', [LotController::class, 'index'])->name('ssi.lot.index');
    Route::get('/lot/export', [LotController::class, 'export'])->name('ssi.lot.export');
    Route::get('/lot/history/{lot}', [LotController::class, 'history'])->name('ssi.lot.history');
    Route::post('/lot/update', [LotController::class, 'updateLot'])->name('ssi.lot.update');

    Route::get('/settings', [SettingsController::class, 'index'])->name('ssi.settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('ssi.settings.update');
    Route::post('/settings/test-telegram', [SettingsController::class, 'testTelegram'])->name('ssi.settings.test_telegram');
    Route::post('/settings/reset-default', [SettingsController::class, 'resetDefault'])->name('ssi.settings.reset_default');
    Route::get('/settings/export', [SettingsController::class, 'export'])->name('ssi.settings.export');
});

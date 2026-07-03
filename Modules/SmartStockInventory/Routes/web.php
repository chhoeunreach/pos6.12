<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('smart-stock-inventory')->group(function () {
    Route::get('/install', ['\Modules\SmartStockInventory\Http\Controllers\InstallController', 'index']);
    Route::post('/install', ['\Modules\SmartStockInventory\Http\Controllers\InstallController', 'install']);
    Route::get('/install/uninstall', ['\Modules\SmartStockInventory\Http\Controllers\InstallController', 'uninstall']);
    Route::get('/install/update', ['\Modules\SmartStockInventory\Http\Controllers\InstallController', 'update']);
});

Route::middleware([
    'web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'ssi.access',
])->prefix('smart-stock-inventory')->group(function () {
    Route::get('/dashboard', ['\Modules\SmartStockInventory\Http\Controllers\DashboardController', 'index'])->name('ssi.dashboard');
    Route::get('/dashboard/detail/{metric}', ['\Modules\SmartStockInventory\Http\Controllers\DashboardController', 'detail'])->name('ssi.dashboard.detail');
    Route::get('/dashboard/export', ['\Modules\SmartStockInventory\Http\Controllers\DashboardController', 'export'])->name('ssi.dashboard.export');
    Route::get('/dashboard/print', ['\Modules\SmartStockInventory\Http\Controllers\DashboardController', 'print'])->name('ssi.dashboard.print');
    Route::get('/dashboard/refresh', ['\Modules\SmartStockInventory\Http\Controllers\DashboardController', 'refresh'])->name('ssi.dashboard.refresh');

    Route::get('/count', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'index'])->name('ssi.count.index');
    Route::get('/count/enterprise', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'index'])->name('ssi.count.enterprise');
    Route::post('/count/enterprise/session', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'createSession'])->name('ssi.count.enterprise.session');
    Route::post('/count/enterprise/session/{session}/assign', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'assignCounter'])->name('ssi.count.enterprise.assign');
    Route::post('/count/enterprise/session/{session}/line', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'countLine'])->name('ssi.count.enterprise.line');
    Route::post('/count/enterprise/session/{session}/line/{line}/verify', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'verifyLine'])->name('ssi.count.enterprise.verify');
    Route::post('/count/enterprise/session/{session}/approve', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'approve'])->name('ssi.count.enterprise.approve');
    Route::post('/count/enterprise/session/{session}/freeze', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'freeze'])->name('ssi.count.enterprise.freeze');
    Route::get('/count/enterprise/session/{session}/dashboard', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'dashboard'])->name('ssi.count.enterprise.dashboard');
    Route::get('/count/enterprise/session/{session}/adjustment-preview', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'adjustmentPreview'])->name('ssi.count.enterprise.adjustment_preview');
    Route::get('/count/enterprise/session/{session}/mobile', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'mobile'])->name('ssi.count.enterprise.mobile');
    Route::get('/count/reports', ['\Modules\SmartStockInventory\Http\Controllers\InventoryEnterpriseController', 'reports'])->name('ssi.count.reports');
    Route::post('/count/store', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'store'])->name('ssi.count.store');
    Route::post('/count/session/update/{session}', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'updateSession'])->name('ssi.count.session.update');
    Route::post('/count/line/update/{line}', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'updateLine'])->name('ssi.count.line.update');
    Route::delete('/count/session/delete/{session}', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'deleteSession'])->name('ssi.count.session.delete');
    Route::delete('/count/line/delete/{line}', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'deleteLine'])->name('ssi.count.line.delete');
    Route::delete('/count/imported/delete', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'deleteImported'])->name('ssi.count.imported.delete');
    Route::post('/count/complete', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'complete'])->name('ssi.count.complete');
    Route::get('/count/export', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'export'])->name('ssi.count.export');
    Route::get('/count/print/{session}', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'print'])->name('ssi.count.print');
    Route::post('/count/import', ['\Modules\SmartStockInventory\Http\Controllers\InventoryCountController', 'import'])->name('ssi.count.import');

    Route::get('/verification', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'index'])->name('ssi.verification.index');
    Route::post('/verification/approve/{session}', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'approve'])->name('ssi.verification.approve');
    Route::post('/verification/reject/{session}', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'reject'])->name('ssi.verification.reject');
    Route::post('/verification/recount/{session}', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'recount'])->name('ssi.verification.recount');
    Route::get('/verification/export', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'export'])->name('ssi.verification.export');
    Route::get('/verification/print', ['\Modules\SmartStockInventory\Http\Controllers\VerificationController', 'print'])->name('ssi.verification.print');
    Route::get('/mismatch', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'index'])->name('ssi.mismatch.index');
    Route::post('/mismatch/preview-fix', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'previewFix'])->name('ssi.mismatch.preview_fix');
    Route::post('/mismatch/fix-auto', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'fixAuto'])->name('ssi.mismatch.fix_auto');
    Route::post('/mismatch/rollback', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'rollback'])->name('ssi.mismatch.rollback');
    Route::get('/mismatch/logs', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'logs'])->name('ssi.mismatch.logs');
    Route::get('/fix-logs', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'fixLogs'])->name('ssi.fix_logs');
    Route::delete('/fix-logs/delete/{id}', ['\Modules\SmartStockInventory\Http\Controllers\MismatchController', 'deleteLog'])->name('ssi.fix_logs.delete');

    Route::get('/movement', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'index'])->name('ssi.movement.index');
    Route::get('/movement/search-sku', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'searchSku'])->name('ssi.movement.search_sku');
    Route::get('/movement/export', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'export'])->name('ssi.movement.export');
    Route::get('/movement/print', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'print'])->name('ssi.movement.print');
    Route::get('/movement/{transaction}/edit-modal', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'editModal'])->name('ssi.movement.edit_modal');
    Route::post('/movement/{transaction}/edit-modal', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'updateModal'])->name('ssi.movement.update_modal');
    Route::post('/movement/{transaction}/void', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'voidTransaction'])->name('ssi.movement.void');
    Route::post('/movement/{transaction}/restore', ['\Modules\SmartStockInventory\Http\Controllers\MovementController', 'restoreTransaction'])->name('ssi.movement.restore');

    Route::get('/imei', ['\Modules\SmartStockInventory\Http\Controllers\ImeiController', 'index'])->name('ssi.imei.index');
    Route::get('/imei/export', ['\Modules\SmartStockInventory\Http\Controllers\ImeiController', 'export'])->name('ssi.imei.export');
    Route::get('/imei/history/{imei}', ['\Modules\SmartStockInventory\Http\Controllers\ImeiController', 'history'])->name('ssi.imei.history');
    Route::post('/imei/update', ['\Modules\SmartStockInventory\Http\Controllers\ImeiController', 'updateStatus'])->name('ssi.imei.update');
    Route::get('/lot', ['\Modules\SmartStockInventory\Http\Controllers\LotController', 'index'])->name('ssi.lot.index');
    Route::get('/lot/export', ['\Modules\SmartStockInventory\Http\Controllers\LotController', 'export'])->name('ssi.lot.export');
    Route::get('/lot/history/{lot}', ['\Modules\SmartStockInventory\Http\Controllers\LotController', 'history'])->name('ssi.lot.history');
    Route::post('/lot/update', ['\Modules\SmartStockInventory\Http\Controllers\LotController', 'updateLot'])->name('ssi.lot.update');

    Route::get('/stock-reports/sell', ['\Modules\SmartStockInventory\Http\Controllers\StockReportController', 'stockSellReport'])->name('ssi.report.stock_sell');
    Route::get('/stock-reports/purchase', ['\Modules\SmartStockInventory\Http\Controllers\StockReportController', 'stockPurchaseReport'])->name('ssi.report.stock_purchase');
    Route::get('/stock-reports/transfer', ['\Modules\SmartStockInventory\Http\Controllers\StockReportController', 'stockTransferReport'])->name('ssi.report.stock_transfer');

    Route::get('/settings', ['\Modules\SmartStockInventory\Http\Controllers\SettingsController', 'index'])->name('ssi.settings.index');
    Route::post('/settings', ['\Modules\SmartStockInventory\Http\Controllers\SettingsController', 'update'])->name('ssi.settings.update');
    Route::post('/settings/test-telegram', ['\Modules\SmartStockInventory\Http\Controllers\SettingsController', 'testTelegram'])->name('ssi.settings.test_telegram');
    Route::post('/settings/reset-default', ['\Modules\SmartStockInventory\Http\Controllers\SettingsController', 'resetDefault'])->name('ssi.settings.reset_default');
    Route::get('/settings/export', ['\Modules\SmartStockInventory\Http\Controllers\SettingsController', 'export'])->name('ssi.settings.export');
});

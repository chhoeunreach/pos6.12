<?php

Route::get('/repair-status', ['\Modules\Repair\Http\Controllers\CustomerRepairStatusController', 'index'])->name('repair-status');
Route::post('/post-repair-status', ['\Modules\Repair\Http\Controllers\CustomerRepairStatusController', 'postRepairStatus'])->name('post-repair-status');
Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('repair')->group(function () {
    Route::get('edit-repair/{id}/status', ['\Modules\Repair\Http\Controllers\RepairController', 'editRepairStatus']);
    Route::post('update-repair-status', ['\Modules\Repair\Http\Controllers\RepairController', 'updateRepairStatus']);
    Route::get('delete-media/{id}', ['\Modules\Repair\Http\Controllers\RepairController', 'deleteMedia']);
    Route::get('print-label/{id}', ['\Modules\Repair\Http\Controllers\RepairController', 'printLabel']);
    Route::get('print-repair/{transaction_id}/customer-copy', ['\Modules\Repair\Http\Controllers\RepairController', 'printCustomerCopy'])->name('repair.customerCopy');
    Route::resource('/repair', '\Modules\Repair\Http\Controllers\RepairController')->except(['create', 'edit']);
    Route::resource('/status', '\Modules\Repair\Http\Controllers\RepairStatusController')->except('show');

    Route::resource('/repair-settings', '\Modules\Repair\Http\Controllers\RepairSettingsController')->only('index', 'store');

    Route::get('/install', ['\Modules\Repair\Http\Controllers\InstallController', 'index'])->name('repair.install.index');
    Route::post('/install', ['\Modules\Repair\Http\Controllers\InstallController', 'install'])->name('repair.install.store');
    Route::get('/install/uninstall', ['\Modules\Repair\Http\Controllers\InstallController', 'uninstall'])->name('repair.install.uninstall');
    Route::get('/install/update', ['\Modules\Repair\Http\Controllers\InstallController', 'update'])->name('repair.install.update');

    Route::get('get-device-models', ['\Modules\Repair\Http\Controllers\DeviceModelController', 'getDeviceModels']);
    Route::get('models-repair-checklist', ['\Modules\Repair\Http\Controllers\DeviceModelController', 'getRepairChecklists']);
    Route::resource('device-models', '\Modules\Repair\Http\Controllers\DeviceModelController')->except(['show']);
    Route::resource('dashboard', '\Modules\Repair\Http\Controllers\DashboardController');

    Route::post('job-sheet-post-upload-docs', ['\Modules\Repair\Http\Controllers\JobSheetController', 'postUploadDocs']);
    Route::get('job-sheet/{id}/upload-docs', ['\Modules\Repair\Http\Controllers\JobSheetController', 'getUploadDocs']);
    Route::get('job-sheet/print/{id}', ['\Modules\Repair\Http\Controllers\JobSheetController', 'print']);
    Route::get('job-sheet/delete/{id}/image', ['\Modules\Repair\Http\Controllers\JobSheetController', 'deleteJobSheetImage']);
    Route::get('job-sheet/{id}/status', ['\Modules\Repair\Http\Controllers\JobSheetController', 'editStatus']);
    Route::put('job-sheet-update/{id}/status', ['\Modules\Repair\Http\Controllers\JobSheetController', 'updateStatus']);
    Route::get('job-sheet/add-parts/{id}', ['\Modules\Repair\Http\Controllers\JobSheetController', 'addParts']);
    Route::post('job-sheet/save-parts/{id}', ['\Modules\Repair\Http\Controllers\JobSheetController', 'saveParts']);
    Route::post('job-sheet/get-part-row', ['\Modules\Repair\Http\Controllers\JobSheetController', 'jobsheetPartRow']);
    Route::resource('job-sheet', '\Modules\Repair\Http\Controllers\JobSheetController');
    Route::post('update-repair-jobsheet-settings', ['\Modules\Repair\Http\Controllers\RepairSettingsController', 'updateJobsheetSettings']);
    Route::get('job-sheet/print-label/{id}', ['\Modules\Repair\Http\Controllers\JobSheetController', 'printLabel']);
});

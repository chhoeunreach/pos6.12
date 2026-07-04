<?php

namespace Modules\KneayerngLinks\Routes;

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth', 'can:manage_modules']], function () {
    Route::match(['get', 'post'], '/install', [Http\Controllers\InstallController::class, 'install']);
    Route::get('/uninstall', [Http\Controllers\InstallController::class, 'uninstall']);
});

Route::group(['middleware' => ['auth', 'can:manage_modules']], function () {
    Route::get('/install', [InstallController::class, 'index']);
    Route::post('/install', [InstallController::class, 'install']);
    Route::get('/install/uninstall', [InstallController::class, 'uninstall']);
    Route::get('/install/update', [InstallController::class, 'update']);
});

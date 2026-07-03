Route::group(['middleware' => ['auth', 'can:manage_modules']], function () {
    Route::match(['get', 'post'], '/install', [InstallController::class, 'install']);
    Route::get('/uninstall', [InstallController::class, 'uninstall']);
});

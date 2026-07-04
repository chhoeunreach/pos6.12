Route::group(['middleware' => ['auth', 'can:manage_modules']], function () {
    Route::match(['get', 'post'], '/install/kneayernglinks', [Modules\\KneayerngLinks\\Http\\Controllers\\InstallController::class, 'install'])
        ->name('kneayernglinks.install');
    Route::get('/uninstall/kneayernglinks', [Modules\\KneayerngLinks\\Http\\Controllers\\InstallController::class, 'uninstall'])
        ->name('kneayernglinks.uninstall');
});

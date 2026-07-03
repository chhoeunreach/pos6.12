Route::group(['middleware' => ['web']], function () {
    Route::match(['get', 'post'], '/install/kneayernglinks', [InstallController::class, 'install'])
        ->name('kneayernglinks.install');
});

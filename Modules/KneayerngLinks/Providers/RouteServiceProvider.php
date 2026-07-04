<?php

namespace Modules\KneayerngLinks\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\KneayerngLinks\Http\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::group(['middleware' => ['auth', 'can:manage_modules']], function () {
            Route::match(['get', 'post'], '/install/kneayernglinks', ['Modules\KneayerngLinks\Http\Controllers\InstallController', 'install'])
                ->name('kneayernglinks.install');
            Route::get('/uninstall/kneayernglinks', ['Modules\KneayerngLinks\Http\Controllers\InstallController', 'uninstall'])
                ->name('kneayernglinks.uninstall');
        });
    }
}
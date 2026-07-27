<?php

namespace Modules\Ecommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Modules\Ecommerce\Http\Controllers\DataController;

class EcommerceServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'ecommerce');

        View::composer('layouts.partials.sidebar', function () {
            (new DataController())->modifyAdminMenu();
        });
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }
}

<?php

namespace Modules\NotificationCenter\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;

class NotificationCenterServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('notificationcenter.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'notificationcenter'
        );
    }

    public function registerViews()
    {
        $viewPath = resource_path('views/modules/notificationcenter');
        $sourcePath = __DIR__.'/../Resources/views';
        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');
        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/notificationcenter';
        }, config('view.paths')), [$sourcePath]), 'notificationcenter');
    }

    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/notificationcenter');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'notificationcenter');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'notificationcenter');
        }
    }

    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__.'/../Database/factories');
        }
    }

    public function provides()
    {
        return [];
    }
}

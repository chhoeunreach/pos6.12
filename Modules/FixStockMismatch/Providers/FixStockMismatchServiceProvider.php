<?php

namespace Modules\FixStockMismatch\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;

class FixStockMismatchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('fixstockmismatch.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'fixstockmismatch');
    }

    private function registerViews(): void
    {
        $viewPath = resource_path('views/modules/fixstockmismatch');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/fixstockmismatch';
        }, config('view.paths')), [$sourcePath]), 'fixstockmismatch');
    }

    private function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/fixstockmismatch');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'fixstockmismatch');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'fixstockmismatch');
        }
    }

    public function registerFactories(): void
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }
}

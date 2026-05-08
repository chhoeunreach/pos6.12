<?php

namespace Modules\ManageLot\Providers;

use Illuminate\Support\ServiceProvider;

class ManageLotServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('manage_lot.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'manage_lot');
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/manage_lot');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/manage_lot';
        }, config('view.paths')), [$sourcePath]), 'manage_lot');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/manage_lot');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'manage_lot');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'manage_lot');
        }
    }
}

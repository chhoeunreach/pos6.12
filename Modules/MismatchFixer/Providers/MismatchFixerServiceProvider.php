<?php

namespace Modules\MismatchFixer\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MismatchFixer\Console\FixMismatchCommand;
use Modules\MismatchFixer\Console\ScanMismatchCommand;

class MismatchFixerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanMismatchCommand::class,
                FixMismatchCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    private function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('mismatchfixer.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'mismatchfixer');
    }

    private function registerViews(): void
    {
        $viewPath = resource_path('views/modules/mismatchfixer');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/mismatchfixer';
        }, config('view.paths')), [$sourcePath]), 'mismatchfixer');
    }

    private function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/mismatchfixer');
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'mismatchfixer');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'mismatchfixer');
        }
    }
}

<?php

namespace Modules\DailyFinancialManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\DailyFinancialManagement\Console\DailyFinancialClosingCommand;
use Modules\DailyFinancialManagement\Console\SyncPosFinancialDataCommand;

class DailyFinancialManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'DailyFinancialManagement';
    protected string $moduleNameLower = 'dailyfinancialmanagement';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPosFinancialDataCommand::class,
                DailyFinancialClosingCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            $this->moduleNameLower
        );
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', $this->moduleNameLower);
        }
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }
}


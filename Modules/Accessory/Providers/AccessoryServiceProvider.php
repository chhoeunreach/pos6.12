<?php

namespace Modules\Accessory\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Accessory\Http\Middleware\UseAccessoryDatabase;
use Modules\Accessory\Http\Middleware\InjectAccessoryTopNav;

class AccessoryServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Accessory';

    protected string $moduleNameLower = 'accessory';

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('accessory.database', UseAccessoryDatabase::class);
        $this->registerTranslations();
        $this->registerViews();
        $this->registerAssetRoutes();
        $this->registerTopNavigation();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

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
            $this->loadJsonTranslationsFrom($langPath);
        }

        $sourcePath = module_path($this->moduleName, 'Resources/lang');
        if (is_dir($sourcePath)) {
            $this->loadTranslationsFrom($sourcePath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($sourcePath);
        }
    }

    protected function registerAssetRoutes(): void
    {
        $sourcePath = module_path($this->moduleName, 'Public/v7');

        if (! is_dir($sourcePath)) {
            return;
        }

        $serveAsset = function (string $path) use ($sourcePath) {
            $basePath = realpath($sourcePath);
            $filePath = realpath($sourcePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

            if (! $basePath || ! $filePath || strpos($filePath, $basePath . DIRECTORY_SEPARATOR) !== 0 || ! is_file($filePath)) {
                abort(404);
            }

            return response()->file($filePath);
        };

        Route::get('modules/accessory/v7/{path}', $serveAsset)->where('path', '.*');
        Route::get('modules/accessoryV7/{path}', $serveAsset)->where('path', '.*');
    }

    protected function registerTopNavigation(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', InjectAccessoryTopNav::class);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }
}

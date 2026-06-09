<?php

namespace Modules\Service\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Service\Http\Middleware\UseServiceDatabase;
use Modules\Service\Http\Middleware\InjectServiceTopNav;

class ServiceServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Service';

    protected string $moduleNameLower = 'service';

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('service.database', UseServiceDatabase::class);
        $this->registerTranslations();
        $this->registerViews();
        $this->registerAssetRoutes();
        $this->registerTopNavigation();
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

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'mjs' => 'application/javascript',
                'map' => 'application/json',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon',
                'eot' => 'application/vnd.ms-fontobject',
                'ttf' => 'font/ttf',
                'otf' => 'font/otf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
            ];

            return response()->file($filePath, [
                'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        };

        Route::get('modules/service/v7/{path}', $serveAsset)->where('path', '.*');
        Route::get('modules/serviceV7/{path}', $serveAsset)->where('path', '.*');
    }

    protected function registerTopNavigation(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', InjectServiceTopNav::class);
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

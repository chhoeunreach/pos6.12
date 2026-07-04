<?php

namespace Modules\KneayerngLinks\Providers;

use Illuminate\Support\ServiceProvider;

class KneayerngLinksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerViews();
        $this->registerViewComposers();
    }

    public function register(): void
    {
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'kneayernglinks');
    }

    private function registerViewComposers(): void
    {
        view()->composer(
            'layouts.partials.header',
            'Modules\\KneayerngLinks\\Providers\\ViewComposers\\KneayerngLinksComposer@compose'
        );
    }
}
<?php

namespace Modules\ManageLot\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $moduleNamespace = 'Modules\\ManageLot\\Http\\Controllers';

    public function map(): void
    {
        if (! \Nwidart\Modules\Facades\Module::has('ManageLot')
            || ! \Nwidart\Modules\Facades\Module::isEnabled('ManageLot')) {
            return;
        }

        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');
    }
}


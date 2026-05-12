<?php

namespace Modules\LoanManagement\Providers;

use App\Transaction;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\LoanManagement\Console\InstallLoanManagementCommand;
use Modules\LoanManagement\Console\UninstallLoanManagementCommand;
use Modules\LoanManagement\Http\Middleware\LoanPermissionMiddleware;
use Modules\LoanManagement\Observers\TransactionInvoicePrefixObserver;

class LoanManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRouteMiddlewareAlias();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        Transaction::observe(TransactionInvoicePrefixObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallLoanManagementCommand::class,
                UninstallLoanManagementCommand::class,
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
            __DIR__ . '/../Config/config.php' => config_path('loanmanagement.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'loanmanagement');
    }

    private function registerViews(): void
    {
        $sourcePath = __DIR__ . '/../Resources/views';
        $this->loadViewsFrom($sourcePath, 'loanmanagement');
    }

    private function registerRouteMiddlewareAlias(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('loan.permission', LoanPermissionMiddleware::class);
    }
}

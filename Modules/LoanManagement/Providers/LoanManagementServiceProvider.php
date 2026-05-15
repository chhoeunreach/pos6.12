<?php

namespace Modules\LoanManagement\Providers;

use App\Transaction;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Modules\LoanManagement\Console\InstallLoanManagementCommand;
use Modules\LoanManagement\Console\TestChatSchemaCommand;
use Modules\LoanManagement\Console\UninstallLoanManagementCommand;
use Modules\LoanManagement\Http\Middleware\LoanPermissionMiddleware;
use Modules\LoanManagement\Observers\TransactionInvoicePrefixObserver;

class LoanManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerCustomerLoanAuth();
        $this->registerRouteMiddlewareAlias();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        Transaction::observe(TransactionInvoicePrefixObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallLoanManagementCommand::class,
                TestChatSchemaCommand::class,
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
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'loan_management');
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

    private function registerCustomerLoanAuth(): void
    {
        $guard = (string) config('loanmanagement.customer_api_guard', 'customer_loan_api');
        $provider = (string) config('loanmanagement.customer_api_provider', 'loan_customers');
        $configuredDriver = (string) config('loanmanagement.customer_api_driver', 'auto');

        $driver = $configuredDriver;
        if ($configuredDriver === 'auto') {
            $driver = class_exists(\Laravel\Sanctum\Sanctum::class) ? 'sanctum' : 'passport';
        }

        Config::set("auth.providers.{$provider}", [
            'driver' => 'eloquent',
            'model' => \Modules\LoanManagement\Entities\LoanCustomer::class,
        ]);

        Config::set("auth.guards.{$guard}", [
            'driver' => $driver,
            'provider' => $provider,
        ]);
    }
}

<?php

namespace Modules\LoanManagement\Providers;

use App\Transaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Modules\LoanManagement\Console\Commands\FlushLoanFeedViews;
use Modules\LoanManagement\Console\InstallLoanManagementCommand;
use Modules\LoanManagement\Console\RunCollectionAutomationCommand;
use Modules\LoanManagement\Console\SystemCheckCommand;
use Modules\LoanManagement\Console\TestChatSchemaCommand;
use Modules\LoanManagement\Console\UninstallLoanManagementCommand;
use Modules\LoanManagement\Helpers\LoanMenuHelper;
use Modules\LoanManagement\Http\Middleware\EnsureLoanUserExists;
use Modules\LoanManagement\Http\Middleware\LoanPermissionMiddleware;
use Modules\LoanManagement\Http\Middleware\RecordLoanActivity;
use Modules\LoanManagement\Observers\TransactionInvoicePrefixObserver;

class LoanManagementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerFeedConnection();
        $this->registerConfig();
        $this->registerCustomerLoanAuth();
        $this->registerRouteMiddlewareAlias();
        $this->registerViews();
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'loanmanagement');
        Transaction::observe(TransactionInvoicePrefixObserver::class);
        $this->registerGlobalHelpers();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallLoanManagementCommand::class,
                SystemCheckCommand::class,
                \Modules\LoanManagement\Console\UpgradeLoanFeaturesCommand::class,
                \Modules\LoanManagement\Console\AccrueLoanPenaltiesCommand::class,
                RunCollectionAutomationCommand::class,
                TestChatSchemaCommand::class,
                UninstallLoanManagementCommand::class,
                FlushLoanFeedViews::class,
            ]);

            $this->app->booted(function () {
                if (\Module::find('Social')?->isEnabled()) {
                    return;
                }

                try {
                    $schedule = $this->app->make(Schedule::class);
                    $schedule->command('loan-feed:flush-views')
                        ->everyMinute()
                        ->withoutOverlapping();
                } catch (\Throwable $e) {
                    // Scheduler unavailable in this host context.
                }
            });
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
        $router->aliasMiddleware('loan.user.sync', EnsureLoanUserExists::class);
        $router->aliasMiddleware('loan.activity', RecordLoanActivity::class);
    }

    private function registerCustomerLoanAuth(): void
    {
        $webGuard = (string) config('loanmanagement.customer_web_guard', 'customer_loan');
        $apiGuard = (string) config('loanmanagement.customer_api_guard', 'customer_loan_api');
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

        Config::set("auth.guards.{$webGuard}", [
            'driver' => 'session',
            'provider' => $provider,
        ]);

        Config::set("auth.guards.{$apiGuard}", [
            'driver' => $driver,
            'provider' => $provider,
        ]);
    }

    private function registerGlobalHelpers(): void
    {
        require_once __DIR__.'/../Helpers/global_helpers.php';
    }

    /**
     * Registers dedicated `mysql_feed` connection when not explicitly defined
     * in config/database.php, allowing zero-downtime split to a separate DB server.
     */
    private function registerFeedConnection(): void
    {
        if (config('database.connections.mysql_feed')) {
            return;
        }

        $base = config('database.connections.mysql_loan')
            ?? config('database.connections.mysql')
            ?? [];

        if (empty($base)) {
            return;
        }

        config([
            'database.connections.mysql_feed' => array_merge($base, [
                'host' => env('DB_FEED_HOST') ?: ($base['host'] ?? '127.0.0.1'),
                'port' => env('DB_FEED_PORT') ?: ($base['port'] ?? '3306'),
                'database' => env('DB_FEED_DATABASE') ?: 'social',
                'username' => env('DB_FEED_USERNAME') ?: ($base['username'] ?? 'root'),
                'password' => env('DB_FEED_PASSWORD') ?: ($base['password'] ?? ''),
                'unix_socket' => env('DB_FEED_SOCKET') ?: ($base['unix_socket'] ?? ''),
            ]),
        ]);
    }
}

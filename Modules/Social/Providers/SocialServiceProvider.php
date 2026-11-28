<?php

namespace Modules\Social\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Social\Console\Commands\FlushSocialFeedViews;

class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerFeedConnection();
        $this->registerConfig();
        $this->registerMigrations();
        $this->registerConsoleCommands();
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('social.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'social');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    protected function registerConsoleCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([FlushSocialFeedViews::class]);

        $this->app->booted(function () {
            try {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('social:flush-views')
                    ->everyMinute()
                    ->withoutOverlapping();
            } catch (\Throwable $e) {
                // Scheduler unavailable in this host context.
            }
        });
    }

    protected function registerFeedConnection(): void
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

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $env = config('app.env');
        $email = config('mail.username');

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

            $schedule->command('pos:generateRecurringExpense')->dailyAt('02:00');

            //Pre-compute stock values for today's reports (warms cache + DB snapshot)
            $schedule->command('pos:recalculate-stock-values')->dailyAt('03:00');

            //Keep LoanManagement collection workflow statuses (collection_status, risk_level, DPD) fresh for reports
            $schedule->command('loan-management:collection-automation')->dailyAt('02:30');

        }

        //Keep LoanManagement collection workflow statuses fresh regardless of environment
        $schedule->command('loan-management:collection-automation')->dailyAt('02:30');

        // POS database backup to Telegram:
        // 02:00, 09:00, 14:00, 17:00, 21:00 every day
        $schedule->command('backup:database-telegram')
            ->cron('0 2,9,14,17,21 * * *')
            ->withoutOverlapping();

        // Daily Local Cashier Branch Income & Expenses PDF to Telegram group at 08:00 AM
        $schedule->command('local-cashier-report:send-telegram-pdf')
            ->dailyAt('08:00')
            ->withoutOverlapping();

        if ($env === 'demo') {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}

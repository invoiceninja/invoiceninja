<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console;

use App\Jobs\Cron\AutoBillCron;
use App\Jobs\Cron\RecurringExpensesCron;
use App\Jobs\Cron\RecurringInvoicesCron;
use App\Jobs\Cron\SubscriptionCron;
use App\Jobs\Ledger\LedgerBalanceUpdate;
use App\Jobs\Ninja\AdjustEmailQuota;
use App\Jobs\Ninja\BankTransactionSync;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Ninja\QueueSize;
use App\Jobs\Ninja\SystemMaintenance;
use App\Jobs\Ninja\TaskScheduler;
use App\Jobs\Util\DiskCleanup;
use App\Jobs\Util\ReminderJob;
use App\Jobs\Util\SchedulerCheck;
use App\Jobs\Util\SendFailedEmails;
use App\Jobs\Util\UpdateExchangeRates;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /* Check for the latest version of Invoice Ninja */
        $schedule->job(new VersionCheck)->daily();

        /* Checks and cleans redundant files */
        $schedule->job(new DiskCleanup)->daily()->withoutOverlapping();

        /* Send reminders */
        $schedule->job(new ReminderJob)->hourly()->withoutOverlapping();

        /* Returns the number of jobs in the queue */
        $schedule->job(new QueueSize)->everyFiveMinutes()->withoutOverlapping();

        /* Checks for large companies and marked them as is_large */
        $schedule->job(new CompanySizeCheck)->daily()->withoutOverlapping();

        /* Pulls in the latest exchange rates */
        $schedule->job(new UpdateExchangeRates)->daily()->withoutOverlapping();

        /* Runs cleanup code for subscriptions */
        $schedule->job(new SubscriptionCron)->daily()->withoutOverlapping();

        /* Sends recurring invoices*/
        $schedule->job(new RecurringInvoicesCron)->hourly()->withoutOverlapping();

        /* Sends recurring invoices*/
        $schedule->job(new RecurringExpensesCron)->dailyAt('00:10')->withoutOverlapping();

        /* Performs auto billing */
        $schedule->job(new AutoBillCron)->dailyAt('06:00')->withoutOverlapping();

        /* Checks the status of the scheduler */
        $schedule->job(new SchedulerCheck)->daily()->withoutOverlapping();

        /* Checks for scheduled tasks */
        $schedule->job(new TaskScheduler())->daily()->withoutOverlapping();

        /* Performs system maintenance such as pruning the backup table */
        $schedule->job(new SystemMaintenance)->weekly()->withoutOverlapping();

        /* Pulls in bank transactions from third party services */
        $schedule->job(new BankTransactionSync)->dailyAt('04:00')->withoutOverlapping();

        if (Ninja::isSelfHost()) {

            $schedule->call(function () {
                Account::whereNotNull('id')->update(['is_scheduler_running' => true]);
            })->everyFiveMinutes();

        }

        /* Run hosted specific jobs */
        if (Ninja::isHosted()) {

            $schedule->job(new AdjustEmailQuota)->dailyAt('23:30')->withoutOverlapping();

            $schedule->job(new SendFailedEmails)->daily()->withoutOverlapping();

            $schedule->command('ninja:check-data --database=db-ninja-01')->daily('02:00')->withoutOverlapping();

            $schedule->command('ninja:check-data --database=db-ninja-02')->dailyAt('02:05')->withoutOverlapping();

            $schedule->command('ninja:s3-cleanup')->dailyAt('23:15')->withoutOverlapping();

        }

        if (config('queue.default') == 'database' && Ninja::isSelfHost() && config('ninja.internal_queue_enabled') && ! config('ninja.is_docker')) {

            $schedule->command('queue:work database --stop-when-empty --memory=256')->everyMinute()->withoutOverlapping();

            $schedule->command('queue:restart')->everyFiveMinutes()->withoutOverlapping();
            
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

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

namespace App\Jobs\Util;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SchedulerCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);

        if (config('ninja.app_version') != base_path('VERSION.txt')) {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $e) {
                nlog("I wasn't able to migrate the data.");
                nlog($e->getMessage());
            }

            try {
                Artisan::call('clear-compiled');
                Artisan::call('route:clear');
                Artisan::call('optimize');
            } catch (\Exception $e) {
                nlog("I wasn't able to optimize.");
                nlog($e->getMessage());
            }

            try {
                Artisan::call('view:clear');
            } catch (\Exception $e) {
                nlog("I wasn't able to clear the views.");
                nlog($e->getMessage());
            }

            VersionCheck::dispatch();
        }
    }
}

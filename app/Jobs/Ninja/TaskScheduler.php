<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use App\Models\Scheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class TaskScheduler implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {

        if (! config('ninja.db.multi_db_enabled')) {
           
            $this->run(null);
            return;
        }

        foreach (MultiDB::$dbs as $db) {
            $this->run($db);
        }
    }

    
    /**
     * run
     *
     * @param  mixed $db
     * @return void
     */
    private function run(?string $db): void
    {

        if($db) {
            MultiDB::setDB($db);
        }

        nlog("TaskScheduler running .... {$db}" . now()->toDateTimeString());

        $company_ids = Scheduler::query()
                        ->where('is_paused', false)
                        ->where('is_deleted', false)
                        ->whereNotNull('next_run')
                        ->where('next_run', '<=', now())
                        ->where(function ($q) {
                            $q->where('remaining_cycles', '!=', 0)
                              ->orWhereNull('remaining_cycles');
                        })
                        ->distinct()
                        ->pluck('company_id');

        foreach ($company_ids as $company_id) {
            CompanyTaskRunner::dispatch((int) $company_id, $db);
        }

    }

}

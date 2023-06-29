<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Ninja;

use App\Models\Scheduler;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

//@rebuild it
class TaskScheduler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
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
        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            Scheduler::with('company')
                ->where('is_paused', false)
                ->where('is_deleted', false)
                ->whereNotNull('next_run')
                ->where('next_run', '<=', now())
                ->cursor()
                ->each(function ($scheduler) {
                    $this->doJob($scheduler);
                });


            return;
        }

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            Scheduler::with('company')
                ->where('is_paused', false)
                ->where('is_deleted', false)
                ->whereNotNull('next_run')
                ->where('next_run', '<=', now())
                ->cursor()
                ->each(function ($scheduler) {
                    $this->doJob($scheduler);
                });
        }
    }

    private function doJob(Scheduler $scheduler)
    {
        nlog("Doing job {$scheduler->name}");
    
        try {
            $scheduler->service()->runTask();
        } catch(\Exception $e) {
            nlog($e->getMessage());
        }
    }
}

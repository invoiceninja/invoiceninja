<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdjustEmailQuota implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const FREE_PLAN_DAILY_QUOTA = 10;
    const PRO_PLAN_DAILY_QUOTA = 50;
    const ENTERPRISE_PLAN_DAILY_QUOTA = 200;

    const FREE_PLAN_DAILY_CAP = 20;
    const PRO_PLAN_DAILY_CAP = 100;
    const ENTERPRISE_PLAN_DAILY_CAP = 300;

    const DAILY_MULTIPLIER = 1.1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('ninja.db.multi_db_enabled')) {
            $this->adjust();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->adjust();
            }
        }
    }

    public function adjust()
    {
        foreach (Account::cursor() as $account) {
            //@TODO once we add in the two columns daily_emails_quota daily_emails_sent_
        }
    }
}

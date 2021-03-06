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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class BillingSubscriptionCron
{
    use Dispatchable;

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
    public function handle() : void
    {
        /* Get all invoices where the send date is less than NOW + 30 minutes() */

        if (! config('ninja.db.multi_db_enabled')) {

        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {

                MultiDB::setDB($db);


            }
        }
    }

    /* Our daily cron should check

        1. Is the subscription still in trial phase?
        2. Check the recurring invoice and its remaining_cycles to see whether we need to cancel or perform any other function.
    */
    private function processSubscription($client_subscription)
    {

    }
}

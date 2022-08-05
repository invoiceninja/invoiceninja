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

namespace App\Jobs\Cron;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

/*@not used*/

class CompanyRecurringCron
{
    use Dispatchable;

    public $tries = 1;

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
        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {

            MultiDB::setDB($db);

            Company::where('is_disabled', 0)
                   ->whereHas('recurring_invoices', function ($query){
                        $query->where('next_send_date', '<=', now()->toDateTimeString())
                              ->whereNotNull('next_send_date')
                              ->whereNull('deleted_at')
                              ->where('is_deleted', false)
                              ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                              ->where('remaining_cycles', '!=', '0')
                              ->whereHas('client', function ($query) {
                                 $query->where('is_deleted', 0)
                                       ->where('deleted_at', null);
                            });
                          })
                    ->cursor()->each(function ($company){

                    SendCompanyRecurring::dispatch($company->id, $company->db);

            });
        }
    }
}

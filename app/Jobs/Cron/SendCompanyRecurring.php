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

class SendCompanyRecurring
{
    use Dispatchable;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $company;

    public $db;

    public function __construct($company_id, $db)
    {
    
        $this->company_id = $company_id;

        $this->db = $db;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {

        MultiDB::setDB($this->db);

        $recurring_invoices = Company::where('id', $this->company_id)
                                     ->where('is_disabled', 0)
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
                                      ->cursor()->each(function ($recurring_invoice){

                                nlog("Trying to send {$recurring_invoice->number}");

                                if ($recurring_invoice->company->stop_on_unpaid_recurring) {
                                    if ($recurring_invoice->invoices()->whereIn('status_id', [2, 3])->where('is_deleted', 0)->where('balance', '>', 0)->exists()) {
                                        return;
                                    }
                                }

                                try {
                                    (new SendRecurring($recurring_invoice, $recurring_invoice->company->db))->handle();
                                } catch (\Exception $e) {
                                    nlog("Unable to sending recurring invoice {$recurring_invoice->id} ".$e->getMessage());
                                }

                            });
        
    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Cron;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Models\RecurringInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RecurringInvoicesCron
{

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

        $recurring_invoices = RecurringInvoice::where('next_send_date', '<=' Carbon::now()->addMinutes(30))->get();

        Log::info(Carbon::now()->addMinutes(30) . ' Sending Recurring Invoices. Count = '. $recurring_invoices->count() );

        $recurring_invoices->each(function ($recurring_invoice, $key) {
            
            SendRecurring::dispatch($recurring_invoice);

        });

    }


 
           
    

}

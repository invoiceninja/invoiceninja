<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Cron;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Libraries\MultiDB;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RecurringInvoicesCron
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
        info("Sending recurring invoices ".Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {

            $recurring_invoices = RecurringInvoice::whereDate('next_send_date', '=', now())->cursor();

                Log::info(now()->format('Y-m-d') . ' Sending Recurring Invoices. Count = '.$recurring_invoices->count().' On Database # '.$db);

                $recurring_invoices->each(function ($recurring_invoice, $key) {

                info("Current date = " . now()->format("Y-m-d") . " Recurring date = " .$recurring_invoice->next_send_date);

                SendRecurring::dispatch($recurring_invoice, $recurring_invoice->company->db);

            });

        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {

                MultiDB::setDB($db);

                $recurring_invoices = RecurringInvoice::whereDate('next_send_date', '=', now())->cursor();

                Log::info(now()->format('Y-m-d') . ' Sending Recurring Invoices. Count = '.$recurring_invoices->count().' On Database # '.$db);

                $recurring_invoices->each(function ($recurring_invoice, $key) {

                    info("Current date = " . now()->format("Y-m-d") . " Recurring date = " .$recurring_invoice->next_send_date);

                    SendRecurring::dispatch($recurring_invoice, $recurring_invoice->company->db);
    
                });
            }
        }
    }
}

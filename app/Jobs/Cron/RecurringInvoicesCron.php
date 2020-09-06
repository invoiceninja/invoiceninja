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

        if (! config('ninja.db.multi_db_enabled')) {
            $recurring_invoices = RecurringInvoice::where('next_send_date', '<=', Carbon::now()->addMinutes(30))->get();

            Log::info(Carbon::now()->addMinutes(30).' Sending Recurring Invoices. Count = '.$recurring_invoices->count());

            $recurring_invoices->each(function ($recurring_invoice, $key) {
                SendRecurring::dispatch($recurring_invoice, $recurring_invoice->company->db);
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $recurring_invoices = RecurringInvoice::where('next_send_date', '<=', Carbon::now()->addMinutes(30))->get();

                Log::info(Carbon::now()->addMinutes(30).' Sending Recurring Invoices. Count = '.$recurring_invoices->count().'On Database # '.$db);

                $recurring_invoices->each(function ($recurring_invoice, $key) {
                    SendRecurring::dispatch($recurring_invoice, $recurring_invoice->company->db);
                });
            }
        }
    }
}

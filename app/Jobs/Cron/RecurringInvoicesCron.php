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

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Libraries\MultiDB;
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

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
        nlog("Sending recurring invoices ".Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {
            $recurring_invoices = RecurringInvoice::whereDate('next_send_date', '=', now())
                                                        ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                        ->with('company')
                                                        ->cursor();

            nlog(now()->format('Y-m-d') . ' Sending Recurring Invoices. Count = '.$recurring_invoices->count());

            $recurring_invoices->each(function ($recurring_invoice, $key) {
                nlog("Current date = " . now()->format("Y-m-d") . " Recurring date = " .$recurring_invoice->next_send_date);

                if (!$recurring_invoice->company->is_disabled) {
                    SendRecurring::dispatchNow($recurring_invoice, $recurring_invoice->company->db);
                }
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $recurring_invoices = RecurringInvoice::whereDate('next_send_date', '=', now())
                                                        ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                        ->with('company')
                                                        ->cursor();

                nlog(now()->format('Y-m-d') . ' Sending Recurring Invoices. Count = '.$recurring_invoices->count().' On Database # '.$db);

                $recurring_invoices->each(function ($recurring_invoice, $key) {
                    nlog("Current date = " . now()->format("Y-m-d") . " Recurring date = " .$recurring_invoice->next_send_date);

                    if (!$recurring_invoice->company->is_disabled) {
                        SendRecurring::dispatchNow($recurring_invoice, $recurring_invoice->company->db);
                    }
                });
            }
        }
    }
}

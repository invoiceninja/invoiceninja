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
use App\Models\RecurringInvoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class RecurringInvoicesCron
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
        /* Get all invoices where the send date is less than NOW + 30 minutes() */
        nlog('Sending recurring invoices '.Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {
            $recurring_invoices = RecurringInvoice::where('next_send_date', '<=', now()->toDateTimeString())
                                                        ->whereNotNull('next_send_date')
                                                        ->whereNull('deleted_at')
                                                        ->where('is_deleted', false)
                                                        ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                        ->where('remaining_cycles', '!=', '0')
                                                        ->whereHas('client', function ($query) {
                                                            $query->where('is_deleted', 0)
                                                                   ->where('deleted_at', null);
                                                        })
                                                        ->whereHas('company', function ($query) {
                                                            $query->where('is_disabled', 0);
                                                        })
                                                        ->with('company')
                                                        ->cursor();

            nlog(now()->format('Y-m-d').' Sending Recurring Invoices. Count = '.$recurring_invoices->count());

            $recurring_invoices->each(function ($recurring_invoice, $key) {
                nlog('Current date = '.now()->format('Y-m-d').' Recurring date = '.$recurring_invoice->next_send_date);

                nlog("Trying to send {$recurring_invoice->number}");

                /* Special check if we should generate another invoice is the previous one is yet to be paid */
                if ($recurring_invoice->company->stop_on_unpaid_recurring && $recurring_invoice->invoices()->whereIn('status_id', [2, 3])->where('is_deleted', 0)->where('balance', '>', 0)->exists()) {
                    nlog('Existing invoice exists, skipping');

                    return;
                }

                try {
                    SendRecurring::dispatchSync($recurring_invoice, $recurring_invoice->company->db);
                } catch (\Exception $e) {
                    nlog("Unable to sending recurring invoice {$recurring_invoice->id} ".$e->getMessage());
                }
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $recurring_invoices = RecurringInvoice::where('next_send_date', '<=', now()->toDateTimeString())
                                                        ->whereNotNull('next_send_date')
                                                        ->whereNull('deleted_at')
                                                        ->where('is_deleted', false)
                                                        ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                        ->where('remaining_cycles', '!=', '0')
                                                        ->whereHas('client', function ($query) {
                                                            $query->where('is_deleted', 0)
                                                                   ->where('deleted_at', null);
                                                        })
                                                        ->whereHas('company', function ($query) {
                                                            $query->where('is_disabled', 0);
                                                        })
                                                        ->with('company')
                                                        ->cursor();

                nlog(now()->format('Y-m-d').' Sending Recurring Invoices. Count = '.$recurring_invoices->count());

                $recurring_invoices->each(function ($recurring_invoice, $key) {
                    nlog('Current date = '.now()->format('Y-m-d').' Recurring date = '.$recurring_invoice->next_send_date.' Recurring #id = '.$recurring_invoice->id);

                    nlog("Trying to send {$recurring_invoice->number}");

                    if ($recurring_invoice->company->stop_on_unpaid_recurring) {
                        if ($recurring_invoice->invoices()->whereIn('status_id', [2, 3])->where('is_deleted', 0)->where('balance', '>', 0)->exists()) {
                            return;
                        }
                    }

                    try {
                        SendRecurring::dispatchSync($recurring_invoice, $recurring_invoice->company->db);
                    } catch (\Exception $e) {
                        nlog("Unable to sending recurring invoice {$recurring_invoice->id} ".$e->getMessage());
                    }
                });
            }
        }
    }
}

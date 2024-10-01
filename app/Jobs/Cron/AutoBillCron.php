<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AutoBillCron
{
    use Dispatchable;

    public $tries = 1;

    private $counter = 1;

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
    public function handle(): void
    {
        set_time_limit(0);

        /* Get all invoices where the send date is less than NOW + 30 minutes() */
        info('Performing Autobilling '.Carbon::now()->format('Y-m-d h:i:s'));

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {

            $auto_bill_partial_invoices = Invoice::query()
                                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                                ->where('balance', '>', 0)
                                                ->whereDate('partial_due_date', '<=', now())
                                                ->where('auto_bill_enabled', true)
                                                ->where('auto_bill_tries', '<', 3)
                                                ->whereHas('company', function ($query) {
                                                    $query->where('is_disabled', 0);
                                                })
                                                ->where('is_deleted', false)
                                                ->orderBy('id', 'DESC');

            nlog($auto_bill_partial_invoices->count().' partial invoices to auto bill');

            $auto_bill_partial_invoices->chunk(400, function ($invoices) {
                foreach ($invoices as $invoice) {
                    AutoBill::dispatch($invoice->id, null);
                }

                sleep(1);
            });

            $auto_bill_invoices = Invoice::query()
                                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                        ->where('balance', '>', 0)
                                        ->whereDate('due_date', '<=', now())
                                        ->where('auto_bill_enabled', true)
                                        ->where('auto_bill_tries', '<', 3)
                                        ->whereHas('company', function ($query) {
                                            $query->where('is_disabled', 0);
                                        })
                                        ->whereHas('client', function ($query) {
                                            $query->has('gateway_tokens', '>', 0);
                                        })
                                        ->where('is_deleted', false)
                                        ->orderBy('id', 'DESC');

            nlog($auto_bill_invoices->count().' full invoices to auto bill');

            $auto_bill_invoices->chunk(400, function ($invoices) {
                foreach ($invoices as $invoice) {
                    AutoBill::dispatch($invoice->id, null);
                }

                sleep(1);
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $auto_bill_partial_invoices = Invoice::query()
                                            ->whereDate('partial_due_date', '<=', now())
                                            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                            ->where('auto_bill_enabled', true)
                                            ->where('auto_bill_tries', '<', 3)
                                            ->where('balance', '>', 0)
                                            ->where('is_deleted', false)
                                            ->whereHas('company', function ($query) {
                                                $query->where('is_disabled', 0);
                                            })
                                            ->whereHas('client', function ($query) {
                                                $query->has('gateway_tokens', '>=', 1);
                                            })
                                            ->orderBy('id', 'DESC');

                nlog($auto_bill_partial_invoices->count()." partial invoices to auto bill db = {$db}");

                $auto_bill_partial_invoices->chunk(400, function ($invoices) use ($db) {
                    foreach ($invoices as $invoice) {
                        AutoBill::dispatch($invoice->id, $db);
                    }
                    sleep(1);
                });

                $auto_bill_invoices = Invoice::query()
                                            ->whereDate('due_date', '<=', now())
                                            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                            ->where('auto_bill_enabled', true)
                                            ->where('auto_bill_tries', '<', 3)
                                            ->where('balance', '>', 0)
                                            ->where('is_deleted', false)
                                            ->whereHas('company', function ($query) {
                                                $query->where('is_disabled', 0);
                                            })
                                            ->whereHas('client', function ($query) {
                                                $query->has('gateway_tokens', '>=', 1);
                                            })
                                            ->orderBy('id', 'DESC');

                nlog($auto_bill_invoices->count()." full invoices to auto bill db = {$db}");

                $auto_bill_invoices->chunk(400, function ($invoices) use ($db) {
                    foreach ($invoices as $invoice) {
                        AutoBill::dispatch($invoice->id, $db);
                    }
                    sleep(1);
                });
            }

            nlog('Auto Bill - fine');
        }
    }
}

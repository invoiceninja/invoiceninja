<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Utils\Traits\SubscriptionHooker;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Auth;

class SubscriptionCron
{
    use Dispatchable;
    use SubscriptionHooker;

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
        nlog('Subscription Cron');

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            $invoices = Invoice::where('is_deleted', 0)
                              ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                              ->where('balance', '>', 0)
                              ->where('is_proforma', 0)
                              ->whereDate('due_date', '<=', now()->addDay()->startOfDay())
                              ->whereNull('deleted_at')
                              ->whereNotNull('subscription_id')
                              ->cursor();

            $invoices->each(function (Invoice $invoice) {
                $subscription = $invoice->subscription;

                $body = [
                    'context' => 'plan_expired',
                    'client' => $invoice->client->hashed_id,
                    'invoice' => $invoice->hashed_id,
                    'subscription' => $subscription->hashed_id,
                ];

                $this->sendLoad($subscription, $body);
                //This will send the notification daily.
                //We'll need to handle this by performing some action on the invoice to either archive it or delete it?
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $invoices = Invoice::where('is_deleted', 0)
                                  ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                  ->where('balance', '>', 0)
                                  ->where('is_proforma', 0)
                                  ->whereDate('due_date', '<=', now()->addDay()->startOfDay())
                                  ->whereNull('deleted_at')
                                  ->whereNotNull('subscription_id')
                                  ->cursor();

                $invoices->each(function (Invoice $invoice) {
                    $subscription = $invoice->subscription;

                    $body = [
                        'context' => 'plan_expired',
                        'client' => $invoice->client->hashed_id,
                        'invoice' => $invoice->hashed_id,
                        'subscription' => $subscription->hashed_id,
                    ];

                    $this->sendLoad($subscription, $body);
                    //This will send the notification daily.
                    //We'll need to handle this by performing some action on the invoice to either archive it or delete it?
                });
            }
        }
    }
}

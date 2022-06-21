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

namespace App\Observers;

use App\Jobs\Util\UnlinkFile;
use App\Jobs\Util\WebhookHandler;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Webhook;

class InvoiceObserver
{
    public $afterCommit = true;

    /**
     * Handle the client "created" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function created(Invoice $invoice)
    {
        $subscriptions = Webhook::where('company_id', $invoice->company_id)
                            ->where('event_id', Webhook::EVENT_CREATE_INVOICE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_INVOICE, $invoice, $invoice->company, 'client')->delay(now()->addSeconds(2));
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function updated(Invoice $invoice)
    {
        $subscriptions = Webhook::where('company_id', $invoice->company_id)
                            ->where('event_id', Webhook::EVENT_UPDATE_INVOICE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_UPDATE_INVOICE, $invoice, $invoice->company, 'client')->delay(now()->addSeconds(2));
        }
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function deleted(Invoice $invoice)
    {
        $subscriptions = Webhook::where('company_id', $invoice->company_id)
                            ->where('event_id', Webhook::EVENT_DELETE_INVOICE)
                            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_DELETE_INVOICE, $invoice, $invoice->company, 'client')->delay(now()->addSeconds(2));
        }
    }

    /**
     * Handle the client "restored" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function restored(Invoice $invoice)
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function forceDeleted(Invoice $invoice)
    {
        //
    }
}

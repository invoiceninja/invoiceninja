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

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Webhook;

class InvoiceObserver
{
    /**
     * Handle the client "created" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function created(Invoice $invoice)
    {
        WebhookHandler::dispatch(Webhook::EVENT_CREATE_INVOICE, $invoice, $invoice->company);
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function updated(Invoice $invoice)
    {
        WebhookHandler::dispatch(Webhook::EVENT_UPDATE_INVOICE, $invoice, $invoice->company);
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param Invoice $invoice
     * @return void
     */
    public function deleted(Invoice $invoice)
    {
        WebhookHandler::dispatch(Webhook::EVENT_DELETE_INVOICE, $invoice, $invoice->company);
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

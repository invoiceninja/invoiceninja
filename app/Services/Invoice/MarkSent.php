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

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasUpdated;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Services\AbstractService;
use App\Utils\Ninja;

class MarkSent extends AbstractService
{
    public function __construct(public Client $client, public Invoice $invoice)
    {
    }

    public function run($fire_webhook = false)
    {
        /* Return immediately if status is not draft or invoice has been deleted */
        if ($this->invoice && ($this->invoice->fresh()->status_id != Invoice::STATUS_DRAFT || $this->invoice->is_deleted)) {
            return $this->invoice;
        }

        $adjustment = $this->invoice->amount;

        /*Set status*/
        $this->invoice
             ->service()
             ->setStatus(Invoice::STATUS_SENT)
             ->updateBalance($adjustment, true)
             ->save();

        /*Update ledger*/
        $this->invoice
             ->ledger()
             ->updateInvoiceBalance($adjustment, "Invoice {$this->invoice->number} marked as sent.");

        $this->invoice->client->service()->calculateBalance();

        /* Perform additional actions on invoice */
        $this->invoice
             ->service()
             ->applyNumber()
             ->setDueDate()
             ->setReminder()
             ->save();

        $this->invoice->markInvitationsSent();

        event(new InvoiceWasUpdated($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        if ($fire_webhook) {
            event('eloquent.updated: App\Models\Invoice', $this->invoice);
            $this->invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
        }

        return $this->invoice->fresh();
    }
}

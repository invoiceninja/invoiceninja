<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
    public function __construct(public Client $client, public Invoice $invoice) {}

    public function run($fire_webhook = false)
    {
        /** To prevent race conditions, we ensure that updating the invoice from the draft state to sent state is successful. */
        $claimed = Invoice::withTrashed()
            ->where('id', $this->invoice->id)
            ->where('status_id', Invoice::STATUS_DRAFT)
            ->where('is_deleted', false)
            ->update(['status_id' => Invoice::STATUS_SENT]);

            /** If we did not succeed in updating it, then we simply return NOW, this kills the race condition. */
        if ($claimed === 0) {
            return $this->invoice;
        }

        $adjustment = $this->invoice->amount ?? 0;

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

        $this->invoice->client->service()->updateBalance($adjustment);
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

        if ($this->invoice->company->quickbooks && $this->invoice->company->shouldPushToQuickbooks('invoice')) {
            // Guard handled inside QuickbooksBatchCollector::collect() — skips if importing
            \App\Services\Quickbooks\QuickbooksBatchCollector::collect('invoice', $this->invoice->id, $this->invoice->company->db, $this->invoice->company_id);
        }

        return $this->invoice->fresh();
    }
}

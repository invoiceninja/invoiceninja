<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasUpdated;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;

class MarkSent extends AbstractService
{
    public $client;

    public $invoice;

    public function __construct(Client $client, Invoice $invoice)
    {
        $this->client = $client;
        $this->invoice = $invoice;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->invoice->fresh()->status_id != Invoice::STATUS_DRAFT) {
            return $this->invoice;
        }

        /*Set status*/
        $this->invoice
             ->service()
             ->setStatus(Invoice::STATUS_SENT)
             ->save();

         $this->invoice
             ->service()
             ->applyNumber()
             ->setDueDate()
             ->updateBalance($this->invoice->amount, true)
             ->deletePdf()
             ->setReminder()
             ->save();

        $this->invoice->markInvitationsSent();

        /*Adjust client balance*/
        $this->client
             ->service()
             ->updateBalance($this->invoice->balance)
             ->save();

        /*Update ledger*/
        $this->invoice
             ->ledger()
             ->updateInvoiceBalance($this->invoice->balance, "Invoice {$this->invoice->number} marked as sent.");

        event(new InvoiceWasUpdated($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->invoice->fresh();
    }
}

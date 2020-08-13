<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
        if ($this->invoice->status_id != Invoice::STATUS_DRAFT) {
            return $this->invoice;
        }

        $this->invoice->markInvitationsSent();

        $this->invoice->setReminder();

        $this->invoice
             ->service()
             ->setStatus(Invoice::STATUS_SENT)
             ->applyNumber()
             ->setDueDate()
             ->save();

        $this->client->service()->updateBalance($this->invoice->balance)->save();

        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance);

        event(new InvoiceWasUpdated($this->invoice, $this->invoice->company, Ninja::eventVars()));
        
        return $this->invoice->fresh();
    }
}

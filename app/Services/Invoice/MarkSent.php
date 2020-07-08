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

use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;

class MarkSent extends AbstractService
{
    private $client;

    private $invoice;

    public function __construct($client, $invoice)
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

        event(new InvoiceWasMarkedSent($this->invoice, $this->invoice->company, Ninja::eventVars()));

        $this->invoice
             ->service()
             ->setStatus(Invoice::STATUS_SENT)
             ->applyNumber()
             ->setDueDate()
             ->save();

        $this->client->service()->updateBalance($this->invoice->balance)->save();

        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance);

        return $this->invoice->fresh();
    }
}

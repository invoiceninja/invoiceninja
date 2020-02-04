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
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Models\Invoice;

class MarkSent
{

    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

  	public function __invoke($invoice)
  	{

        /* Return immediately if status is not draft */
        if ($invoice->status_id != Invoice::STATUS_DRAFT) {
            return $invoice;
        }

        $invoice->markInvitationsSent();

        $invoice->setReminder();

        event(new InvoiceWasMarkedSent($invoice, $invoice->company));

        $this->client->service()->updateBalance($invoice->balance)->save();

        $invoice->service()->setStatus(Invoice::STATUS_SENT)->applyNumber()->save();

        UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, $invoice->balance, $invoice->company);

        return $invoice;

  	}
}

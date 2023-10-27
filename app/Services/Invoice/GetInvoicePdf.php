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

use App\Jobs\Entity\CreateRawPdf;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\AbstractService;

class GetInvoicePdf extends AbstractService
{
    public function __construct(public Invoice $invoice, public ?ClientContact $contact = null)
    {
    }

    public function run()
    {
        if (! $this->contact) {
            $this->contact = $this->invoice->client->primary_contact()->first() ?: $this->invoice->client->contacts()->first();
        }

        $invitation = $this->invoice->invitations->where('client_contact_id', $this->contact->id)->first();

        if (! $invitation) {
            $invitation = $this->invoice->invitations->first();
        }

        return (new CreateRawPdf($invitation))->handle();

    }
}

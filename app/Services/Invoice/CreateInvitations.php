<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Factory\InvoiceInvitationFactory;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Services\AbstractService;

class CreateInvitations extends AbstractService
{
    private $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        $this->invoice->client->contacts->each(function ($contact) {
            $invitation = InvoiceInvitation::whereCompanyId($this->invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($this->invoice->id)
                                        ->withTrashed()
                                        ->first();

            if (! $invitation && $contact->send_email) {
                $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
                $ii->invoice_id = $this->invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_email) {
                $invitation->delete();
            }
        });

        return $this->invoice;
    }
}

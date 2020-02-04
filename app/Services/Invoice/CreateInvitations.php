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


use App\Factory\InvoiceInvitationFactory;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;

class CreateInvitations
{

    public function __construct()
    {
    }

  	public function __invoke($invoice)
  	{

        $contacts = $invoice->client->contacts;

        $contacts->each(function ($contact) use($invoice){
            $invitation = InvoiceInvitation::whereCompanyId($invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($invoice->id)
                                        ->first();

            if (!$invitation && $contact->send_invoice) {
                $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
                $ii->invoice_id = $invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_invoice) {
                $invitation->delete();
            }
        });

        return $invoice;
  	}
}	
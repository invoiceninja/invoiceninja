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

use App\Factory\ClientContactFactory;
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

        $contacts = $this->invoice->client->contacts;

        if($contacts->count() == 0){
            $this->createBlankContact();

            $this->invoice->refresh();
            $contacts = $this->invoice->client->contacts;
        }

        $contacts->each(function ($contact) {
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

    private function createBlankContact()
    {
        $new_contact = ClientContactFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $new_contact->client_id = $this->invoice->client_id;
        $new_contact->contact_key = Str::random(40);
        $new_contact->is_primary = true;
        $new_contact->save();
    }
}

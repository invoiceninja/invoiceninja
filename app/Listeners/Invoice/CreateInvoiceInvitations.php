<?php

namespace App\Listeners\Invoice;

use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateInvoiceInvitations
{
    use MakesHash;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the creation of invitations for an invoice.
     * We only ever create one invitation per contact.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $invoice = $event->invoice;
        
        $contacts = ClientContact::whereIn('id', explode(',', $invoice->settings->invoice_email_list))->get();

        $contacts->each(function ($contact) use($invoice) {

            $i = InvoiceInvitation::firstOrCreate([
                    'client_contact_id' => $contact->id,
                    'invoice_id' => $invoice->id
                ],
                [
                    'company_id' => $invoice->company_id,
                    'user_id' => $invoice->user_id,
                    'invitation_key' => $this->createDbHash($invoice->company->db),
                ]);

        });

    }
}

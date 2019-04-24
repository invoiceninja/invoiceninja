<?php

namespace App\Listeners\Invoice;

use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateInvoiceInvitations
{
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
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $invoice = $event->invoice;
        
        $contacts = ClientContact::whereIn('id', explode(',', $invoice->settings->invoice_email_list))->get();

        $contacts->each(function ($contact) use($invoice) {

            InvoiceInvitation::create([

            ]);

        });

    }
}

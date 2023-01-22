<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;

class ClientContactObserver
{
    /**
     * Handle the client contact "created" event.
     *
     * @param ClientContact $clientContact
     * @return void
     */
    public function created(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "updated" event.
     *
     * @param ClientContact $clientContact
     * @return void
     */
    public function updated(ClientContact $clientContact)
    {
        //
    }

    /**
     * Handle the client contact "deleted" event.
     *
     * @param ClientContact $clientContact
     * @return void
     */
    public function deleted(ClientContact $clientContact)
    {
        $client_contact_id = $clientContact->id;

        $clientContact->invoice_invitations()->delete();
        $clientContact->quote_invitations()->delete();
        $clientContact->credit_invitations()->delete();
        $clientContact->recurring_invoice_invitations()->delete();

        //ensure entity state is preserved
        
        InvoiceInvitation::withTrashed()->where('client_contact_id', $client_contact_id)->cursor()->each(function ($invite){

          if($invite->invoice()->doesnthave('invitations'))
            $invite->invoice->service()->createInvitations();

        });


        QuoteInvitation::withTrashed()->where('client_contact_id', $client_contact_id)->cursor()->each(function ($invite){

          if($invite->invoice()->doesnthave('invitations'))
            $invite->quote->service()->createInvitations();

        });

        RecurringInvoiceInvitation::withTrashed()->where('client_contact_id', $client_contact_id)->cursor()->each(function ($invite){

          if($invite->recurring_invoice()->doesnthave('invitations'))
            $invite->quote->service()->createInvitations();

        });

        
    }

    /**
     * Handle the client contact "restored" event.
     *
     * @param ClientContact $clientContact
     * @return void
     */
    public function restored(ClientContact $clientContact)
    {
        // $clientContact->invoice_invitations()->restore();
        // $clientContact->quote_invitations()->restore();
        // $clientContact->credit_invitations()->restore();
    }

    /**
     * Handle the client contact "force deleted" event.
     *
     * @param ClientContact $clientContact
     * @return void
     */
    public function forceDeleted(ClientContact $clientContact)
    {
        //
    }
}

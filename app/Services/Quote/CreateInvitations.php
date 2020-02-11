<?php
namespace App\Services\Quote;

use App\Factory\QuoteInvitationFactory;
use App\Models\QuoteInvitation;

class CreateInvitations
{

    public function __construct()
    {
    }

    public function __invoke($quote)
    {

        $contacts = $quote->client->contacts;

        $contacts->each(function ($contact) use($quote){
            $invitation = QuoteInvitation::whereCompanyId($quote->company_id)
                ->whereClientContactId($contact->id)
                ->whereQuoteId($quote->id)
                ->first();

            if (!$invitation && $contact->send_quote) {
                $ii = QuoteInvitationFactory::create($quote->company_id, $quote->user_id);
                $ii->quote_id = $quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_quote) {
                $invitation->delete();
            }
        });

        return $quote;
    }
}

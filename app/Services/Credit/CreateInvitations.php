<?php
namespace App\Services\Credit;

use App\CreditInvitation;
use App\Factory\CreditInvitationFactory;

class CreateInvitations
{

    public function __construct()
    {
    }

    public function __invoke($credit)
    {

        $contacts = $credit->customer->contacts;

        $contacts->each(function ($contact) use($credit){
            $invitation = CreditInvitation::whereAccountId($credit->account_id)
                ->whereClientContactId($contact->id)
                ->whereQuoteId($credit->id)
                ->first();

            if (!$invitation) {
                $ii = CreditInvitationFactory::create($credit->account_id, $credit->user_id);
                $ii->credit_id = $credit->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_credit) {
                $invitation->delete();
            }
        });

        return $credit;
    }
}

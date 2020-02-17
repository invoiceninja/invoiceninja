<?php
namespace App\Services\Credit;

use App\Factory\CreditInvitationFactory;
use App\Models\CreditInvitation;
use App\Services\AbstractService;

class CreateInvitations extends AbstractService
{

    public function __construct()
    {
    }

    public function run($credit)
    {

        $contacts = $credit->client->contacts;

        $contacts->each(function ($contact) use($credit){
            $invitation = CreditInvitation::whereCompanyId($credit->account_id)
                ->whereClientContactId($contact->id)
                ->whereCreditId($credit->id)
                ->first();

            if (!$invitation) {
                $ii = CreditInvitationFactory::create($credit->company_id, $credit->user_id);
                $ii->credit_id = $credit->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_email) {
                $invitation->delete();
            }
        });

        return $credit;
    }
}

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

namespace App\Repositories;

use App\Factory\CreditInvitationFactory;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * CreditRepository
 */
class CreditRepository extends BaseRepository
{
    use MakesHash;
    
    public function __construct()
    {
    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Credit::class;
    }

    /**
     * Saves the client and its contacts
     *
     * @param      array                           $data    The data
     * @param      \App\Models\Company              $client  The Company
     *
     * @return     Credit|\App\Models\Credit|null  Credit Object
     */
    public function save(array $data, Credit $credit) : ?Credit
    {

        $credit->fill($data);

        $credit->save();

        if(!$credit->number)
            $credit->number = $credit->client->getNextCreditNumber($credit->client);

        if (isset($data['client_contacts'])) {
            foreach ($data['client_contacts'] as $contact) {
                if ($contact['send_email'] == 1 && is_string($contact['id'])) {
                    $client_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));
                    $client_contact->send_email = true;
                    $client_contact->save();
                }
            }
        }


        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keys which have been removed from the invitations array and soft delete each invitation */
            $credit->invitations->pluck('key')->diff($invitations->pluck('key'))->each(function ($invitation) {
                    
                $invite = $this->getInvitationByKey($invitation);

                if($invite)
                    $invite->forceDelete();

            });

            foreach ($data['invitations'] as $invitation) {
                $inv = false;

                if (array_key_exists('key', $invitation)) {
                    $inv = $this->getInvitationByKey($invitation['key']);
                }

                if (!$inv) {

                    if (isset($invitation['id'])) {
                        unset($invitation['id']);
                    }

                    $new_invitation = CreditInvitationFactory::create($credit->company_id, $credit->user_id);
                    $new_invitation->fill($invitation);
                    $new_invitation->credit_id = $credit->id;
                    $new_invitation->client_contact_id = $invitation['client_contact_id'];
                    $new_invitation->save();

                }
            }
        }

        $credit->load('invitations');

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($credit->invitations->count() == 0) {
            $credit->service()->createInvitations();
        }
        /**
         * Perform calculations on the 
         * credit note
         */
        
        $credit = $credit->calc()->getCredit();
        
        $credit->save();

        return $credit->fresh();

    }

    public function getInvitationByKey($key) :?CreditInvitation
    {
        return CreditInvitation::whereRaw("BINARY `key`= ?", [$key])->first();
    }

}
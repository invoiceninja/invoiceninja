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

use App\Models\Credit;
use App\Models\CreditInvitation;
use Illuminate\Http\Request;

/**
 * CreditRepository
 */
class CreditRepository extends BaseRepository
{
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
    public function save(array $data, Credit $credit, $invoice = null) : ?Credit
    {

        $credit->fill($data);

        $credit->save();

        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keyss which have been removed from the invitations array and soft delete each invitation */
            collect($credit->invitations->pluck('key'))->diff($invitations->pluck('key'))->each(function ($invitation) {
                CreditInvitation::destroy($invitation);
            });


            foreach ($data['invitations'] as $invitation) {
                $cred = false;

                if (array_key_exists('key', $invitation)) {
                    $cred = CreditInvitation::whereKey($invitation['key'])->first();
                }

                if (!$cred) {
                    $invitation['client_contact_id'] = $this->decodePrimaryKey($invitation['client_contact_id']);

                    $new_invitation = CreditInvitationFactory::create($invoice->company_id, $invoice->user_id);
                    $new_invitation->fill($invitation);
                    $new_invitation->credit_id = $credit->id;
                    $new_invitation->save();
                }
            }
        }
    }

}
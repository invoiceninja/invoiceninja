<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Credit;

use App\Factory\CreditInvitationFactory;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Services\AbstractService;

class CreateInvitations extends AbstractService
{
    private $credit;

    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }

    public function run()
    {
        $contacts = $this->credit->client->contacts;

        $contacts->each(function ($contact) {
            $invitation = CreditInvitation::whereCompanyId($this->credit->company_id)
                ->whereClientContactId($contact->id)
                ->whereCreditId($this->credit->id)
                ->first();

            if (! $invitation) {
                $ii = CreditInvitationFactory::create($this->credit->company_id, $this->credit->user_id);
                $ii->credit_id = $this->credit->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif (! $contact->send_email) {
                $invitation->delete();
            }
        });

        return $this->credit;
    }
}

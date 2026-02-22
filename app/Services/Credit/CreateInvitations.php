<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Credit;

use App\Factory\ClientContactFactory;
use App\Factory\CreditInvitationFactory;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Services\AbstractService;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class CreateInvitations extends AbstractService
{
    use MakesHash;

    public $credit;

    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }

    public function run()
    {
        $contacts = $this->credit->client->contacts;

        if ($contacts->count() == 0) {
            $this->createBlankContact();

            $this->credit->refresh();
            $contacts = $this->credit->client->contacts;
        }

        $contacts->each(function ($contact) {
            $invitation = CreditInvitation::query()
                ->where('company_id', $this->credit->company_id)
                ->whereClientContactId($contact->id)
                ->whereCreditId($this->credit->id)
                ->withTrashed()
                ->first();

            if (! $invitation) {
                $ii = CreditInvitationFactory::create($this->credit->company_id, $this->credit->user_id);
                $ii->key = $this->createDbHash($this->credit->company->db);
                $ii->credit_id = $this->credit->id;
                $ii->client_contact_id = $contact->id;
                $ii->can_sign = $contact->can_sign;
                $ii->save();
            } elseif (! $contact->send_email) {
                $invitation->delete();
            }
        });

        if ($this->credit->invitations()->count() == 0) {
            if ($contacts->count() == 0) {
                $contact = $this->createBlankContact();
            } else {
                $contact = $contacts->first();

                $invitation = CreditInvitation::query()->where('company_id', $this->credit->company_id)
                                ->where('client_contact_id', $contact->id)
                                ->where('credit_id', $this->credit->id)
                                ->withTrashed()
                                ->first();

                if ($invitation) {
                    $invitation->restore();

                    return $this->credit;
                }
            }

            $ii = CreditInvitationFactory::create($this->credit->company_id, $this->credit->user_id);
            $ii->key = $this->createDbHash($this->credit->company->db);
            $ii->credit_id = $this->credit->id;
            $ii->client_contact_id = $contact->id;
            $ii->can_sign = $contact->can_sign;
            $ii->save();
        }

        if($this->credit->invitations()->where('can_sign', true)->count() == 0){
            
            $ii = $this->credit->invitations()->whereHas('contact', function ($q){
                $q->where('is_primary', true);
            })->first() ?? $this->credit->invitations()->first();

            $ii->can_sign = true;
            $ii->saveQuietly();
        }

        return $this->credit;
    }

    private function createBlankContact()
    {
        $new_contact = ClientContactFactory::create($this->credit->company_id, $this->credit->user_id);
        $new_contact->client_id = $this->credit->client_id;
        $new_contact->contact_key = Str::random(40);
        $new_contact->is_primary = true;
        $new_contact->can_sign = false;
        $new_contact->save();
    }
}

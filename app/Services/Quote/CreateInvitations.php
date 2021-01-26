<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Quote;

use App\Factory\ClientContactFactory;
use App\Factory\QuoteInvitationFactory;
use App\Models\Quote;
use App\Models\QuoteInvitation;

class CreateInvitations
{
    public $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    public function run()
    {

       $contacts = $this->quote->client->contacts;

        if($contacts->count() == 0){
            $this->createBlankContact();

            $this->quote->refresh();
            $contacts = $this->quote->client->contacts;
        }

        $contacts->each(function ($contact){
            $invitation = QuoteInvitation::whereCompanyId($this->quote->company_id)
                ->whereClientContactId($contact->id)
                ->whereQuoteId($this->quote->id)
                ->withTrashed()
                ->first();

            if (! $invitation && $contact->send_email) {
                $ii = QuoteInvitationFactory::create($this->quote->company_id, $this->quote->user_id);
                $ii->quote_id = $this->quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_email) {
                $invitation->delete();
            }
        });

        return $this->quote->fresh();
    }

    private function createBlankContact()
    {
        $new_contact = ClientContacstFactory::create($this->quote->company_id, $this->quote->user_id);
        $new_contact->client_id = $this->quote->client_id;
        $new_contact->contact_key = Str::random(40);
        $new_contact->is_primary = true;
        $new_contact->save();
    }

}

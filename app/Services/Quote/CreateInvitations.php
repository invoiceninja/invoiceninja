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

use App\Factory\QuoteInvitationFactory;
use App\Models\QuoteInvitation;

class CreateInvitations
{
    public function __construct()
    {
    }

    public function run($quote)
    {
        $quote->client->contacts->each(function ($contact) use ($quote) {
            $invitation = QuoteInvitation::whereCompanyId($quote->company_id)
                ->whereClientContactId($contact->id)
                ->whereQuoteId($quote->id)
                ->first();

            if (! $invitation && $contact->send_email) {
                $ii = QuoteInvitationFactory::create($quote->company_id, $quote->user_id);
                $ii->quote_id = $quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_email) {
                $invitation->delete();
            }
        });

        return $quote->fresh();
    }
}

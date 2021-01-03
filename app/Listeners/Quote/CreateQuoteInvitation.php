<?php
/**
 * Invoice Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://creditninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Quote;

use App\Factory\QuoteInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\QuoteInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateQuoteInvitation implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $quote = $event->credit;

        $contacts = $quote->client->contacts;

        $contacts->each(function ($contact) use ($quote) {
            $invitation = QuoteInvitation::whereCompanyId($quote->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereQuoteId($quote->id)
                                        ->first();

            if (! $invitation && $contact->send_credit) {
                $ii = QuoteInvitationFactory::create($quote->company_id, $quote->user_id);
                $ii->quote_id = $quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_credit) {
                $invitation->delete();
            }
        });
    }
}

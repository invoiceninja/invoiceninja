<?php
/**
 * Invoice Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Quote;

use App\Factory\QuoteInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateQuoteInvitation implements ShouldQueue
{
    use MakesHash;

    public $delay = 5;

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
                $ii->key = $this->createDbHash($quote->company->db);
                $ii->quote_id = $quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_credit) {
                $invitation->delete();
            }
        });
    }
}

<?php
/**
 * Invoice Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://creditninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Quote;

use App\Factory\CreditInvitationFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\QuoteInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Debug\Exception\FatalThrowableError;

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

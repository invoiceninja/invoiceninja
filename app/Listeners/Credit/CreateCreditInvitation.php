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

namespace App\Listeners\Credit;

use App\Factory\CreditInvitationFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CreateCreditInvitation implements ShouldQueue
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

        $credit = $event->credit;

        $contacts = $credit->client->contacts;

        $contacts->each(function ($contact) use ($credit) {
            $invitation = CreditInvitation::whereCompanyId($credit->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereCreditId($credit->id)
                                        ->first();

            if (! $invitation && $contact->send_credit) {
                $ii = CreditInvitationFactory::create($credit->company_id, $credit->user_id);
                $ii->credit_id = $credit->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_credit) {
                $invitation->delete();
            }
        });
    }
}

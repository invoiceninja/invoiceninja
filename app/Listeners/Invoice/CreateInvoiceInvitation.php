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

namespace App\Listeners\Invoice;

use App\Factory\InvoiceInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class CreateInvoiceInvitation implements ShouldQueue
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

        $invoice = $event->invoice;

        $contacts = $invoice->client->contacts;

        $contacts->each(function ($contact) use ($invoice) {
            $invitation = InvoiceInvitation::whereCompanyId($invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($invoice->id)
                                        ->first();

            if (! $invitation && $contact->send) {
                $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
                $ii->invoice_id = $invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send) {
                $invitation->delete();
            }
        });
    }
}

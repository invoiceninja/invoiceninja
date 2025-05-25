<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Contact;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateContactLastLogin implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $client_contact = $event->client_contact;

        $contacts = \App\Models\ClientContact::where('company_id', $client_contact->company_id)
                                 ->where('email', $client_contact->email);

        $contacts->update(['last_login' => now()]);

        \App\Models\Client::withTrashed()->whereIn('id', $contacts->pluck('client_id'))->where('is_deleted', false)->update(['last_login' => now()]);

    }
}

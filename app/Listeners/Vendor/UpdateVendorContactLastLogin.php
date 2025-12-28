<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Vendor;

use App\Libraries\MultiDB;

class UpdateVendorContactLastLogin
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

        $contact = $event->contact;

        $contact->last_login = now();
        $contact->vendor->last_login = now();

        $contact->push();
    }
}

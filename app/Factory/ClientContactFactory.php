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

namespace App\Factory;

use App\Models\ClientContact;

class ClientContactFactory
{
    public static function create(int $company_id, int $user_id) :ClientContact
    {
        $client_contact = new ClientContact;
        $client_contact->first_name = '';
        $client_contact->user_id = $user_id;
        $client_contact->company_id = $company_id;
        $client_contact->contact_key = \Illuminate\Support\Str::random(40);
        $client_contact->id = 0;

        return $client_contact;
    }
}

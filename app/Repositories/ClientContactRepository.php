<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\ClientContactFactory;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * ClientContactRepository
 */
class ClientContactRepository extends BaseRepository
{
    public function save(array $data, Client $client) : void
    {

        if (isset($data['contacts'])) {
            $contacts = collect($data['contacts']);
        } else {
            $contacts = collect();
        }

        $client->contacts->pluck('id')->diff($contacts->pluck('id'))->each(function ($contact) {
            ClientContact::destroy($contact);
        });

        $this->is_primary = true;
        /* Set first record to primary - always */
        $contacts = $contacts->sortByDesc('is_primary')->map(function ($contact) {
            $contact['is_primary'] = $this->is_primary;
            $this->is_primary = false;
            return $contact;
        });

        //loop and update/create contacts
        $contacts->each(function ($contact) use ($client) {
            $update_contact = null;

            if (isset($contact['id'])) {
                $update_contact = ClientContact::find($contact['id']);
            }

            if (!$update_contact) {
                $update_contact = ClientContactFactory::create($client->company_id, $client->user_id);
                $update_contact->client_id = $client->id;
            }

            $update_contact->fill($contact);

            if (array_key_exists('password', $contact)) {
                if (strlen($contact['password']) == 0) {
                    $update_contact->password = '';
                } else {
                    $update_contact->password = Hash::make($contact['password']);
                }
            }

            $update_contact->save();

        });

        //need to reload here to shake off stale contacts
        $client->load('contacts');

        //always made sure we have one blank contact to maintain state
        if ($client->contacts->count() == 0) {
        
            $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
            $new_contact->client_id = $client->id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->save();
        
        }

    }
}

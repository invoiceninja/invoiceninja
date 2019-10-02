<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientContact;

/**
 * ClientContactRepository
 */
class ClientContactRepository extends BaseRepository
{

	public function save($contacts, Client $client) : void
	{

		/* Convert array to collection */
		$contacts = collect($contacts);

		/* Get array of IDs which have been removed from the contacts array and soft delete each contact */
		collect($client->contacts->pluck('id'))->diff($contacts->pluck('id'))->each(function($contact){

			ClientContact::destroy($contact);

		});

		/* Set first record to primary - always*/
		$contacts = $contacts->sortBy('is_primary');

		$contacts->first(function($contact){

			$contact['is_primary'] = true;
		//	$contact->save();

		});

		//loop and update/create contacts
		$contacts->each(function ($contact) use ($client){ 
			
			$update_contact = null;

			if(isset($contact['id']))
				$update_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));

			if(!$update_contact){
			
				$update_contact = new ClientContact;
				$update_contact->client_id = $client->id;
				$update_contact->company_id = $client->company_id;
				$update_contact->user_id = $client->user_id;
				$update_contact->contact_key = \Str::random(40);
			}

			$update_contact->fill($contact);

			$update_contact->save();
		});


	}

}
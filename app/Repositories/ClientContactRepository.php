<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\Log;

/**
 * 
 */
class ClientContactRepository extends BaseRepository
{

	public function save(array $contacts, Client $client) : void
	{

		/* Convert array to collection */
		$contacts = collect($contacts);

		/* Get array of IDs which have been removed from the contacts array and soft delete each contact */
		collect($client->contacts->pluck('id'))->diffKeys($contacts->pluck('id'))->each(function($contact){
			ClientContact::destroy($contact);
		});

		/* Set first record to primary - always*/
		$contacts = $contacts->sortBy('is_primary');

		$contacts->first(function($contact){
			$contact['is_primary'] = true;
		});

		//loop and update/create contacts
		$contacts->each(function ($contact) use ($client){ 

			$update_contact = ClientContact::firstOrNew(
				['id' => $contact['id']],
				[
					'client_id' => $client->id, 
					'company_id' => $client->company_id
				]
			);

			$update_contact->fill($contact);
			$update_contact->save();
		});


	}
	



}
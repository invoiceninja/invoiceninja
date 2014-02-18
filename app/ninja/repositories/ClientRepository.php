<?php namespace ninja\repositories;

use Client;
use Contact;

class ClientRepository
{
	public function find($filter = null)
	{
    	$query = \DB::table('clients')
    				->join('contacts', 'contacts.client_id', '=', 'clients.id')
    				->where('clients.account_id', '=', \Auth::user()->account_id)
    				->where('contacts.is_primary', '=', true)
    				->select('clients.public_id','clients.name','contacts.first_name','contacts.last_name','clients.balance','clients.last_login','clients.created_at','clients.work_phone','contacts.email','clients.currency_id');

    	if (!\Session::get('trash_client'))
    	{
    		$query->where('clients.deleted_at', '=', null);
    	}

    	if ($filter)
    	{
    		$query->where(function($query) use ($filter)
            {
            	$query->where('clients.name', 'like', '%'.$filter.'%')
            		  ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
            		  ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
            		  ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
    	}

    	return $query;
	}

	public function save($publicId, $data)
	{			
		if ($publicId == "-1") 
		{
			$client = Client::createNew();
			$contact = Contact::createNew();
			$contact->is_primary = true;			
		}
		else
		{
			$client = Client::scope($publicId)->with('contacts')->firstOrFail();
			$contact = $client->contacts()->where('is_primary', '=', true)->firstOrFail();
		}
		
		$client->name = trim($data['name']);
		$client->work_phone = trim($data['work_phone']);
		$client->address1 = trim($data['address1']);
		$client->address2 = trim($data['address2']);
		$client->city = trim($data['city']);
		$client->state = trim($data['state']);
		$client->postal_code = trim($data['postal_code']);
		$client->country_id = $data['country_id'] ? $data['country_id'] : null;
		$client->private_notes = trim($data['private_notes']);
		$client->size_id = $data['size_id'] ? $data['size_id'] : null;
		$client->industry_id = $data['industry_id'] ? $data['industry_id'] : null;
		$client->currency_id = $data['currency_id'] ? $data['currency_id'] : 1;
		$client->payment_terms = $data['payment_terms'];
		$client->website = trim($data['website']);
		$client->save();
		
		$isPrimary = true;
		$contactIds = [];

		foreach ($data['contacts'] as $record)
		{
			$record = (array) $record;

			if ($publicId != "-1" && isset($record['public_id']) && $record['public_id'])
			{
				$contact = Contact::scope($record['public_id'])->firstOrFail();
			}
			else
			{
				$contact = Contact::createNew();
			}

			$contact->email = trim(strtolower($record['email']));
			$contact->first_name = trim($record['first_name']);
			$contact->last_name = trim($record['last_name']);
			$contact->phone = trim($record['phone']);
			$contact->is_primary = $isPrimary;
			$contact->send_invoice = $record['send_invoice'];
			$isPrimary = false;

			$client->contacts()->save($contact);
			$contactIds[] = $contact->public_id;
		}
		
		foreach ($client->contacts as $contact)
		{
			if (!in_array($contact->public_id, $contactIds))
			{	
				$contact->delete();
			}
		}

		$client->save();
		
		if ($publicId == "-1")
		{
			\Activity::createClient($client);
		}

		return $client;
	}

	public function bulk($ids, $action)
	{
		$clients = Client::scope($ids)->get();

		foreach ($clients as $client) 
		{			
			if ($action == 'delete') 
			{
				$client->is_deleted = true;
				$client->save();
			} 
			
			$client->delete();			
		}

		return count($clients);
	}	
}
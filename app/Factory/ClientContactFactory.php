<?php

namespace App\Factory;

use App\Models\ClientContact;

class ClientContactFactory
{
	public static function create(int $company_id, int $user_id) :ClientContact
	{
		$client_contact = new ClientContact;
        $client_contact->first_name = "";
        $client_contact->user_id = $user_id;
        $client_contact->company_id = $company_id;
        $client_contact->id = 0;

		return $client_contact;
	}
}

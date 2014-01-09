<?php namespace ninja\repositories;

use Client;
use Contact;

class AccountRepository
{
	public function getSearchData()
	{
    	$clients = \DB::table('clients')
			->where('clients.deleted_at', '=', null)
			->where('clients.account_id', '=', \Auth::user()->account_id)			
			->whereRaw("clients.name <> ''")
			->select(\DB::raw("'Clients' as type, clients.public_id, clients.name, '' as token"));

		$contacts = \DB::table('clients')
			->join('contacts', 'contacts.client_id', '=', 'clients.id')
			->where('clients.deleted_at', '=', null)
			->where('clients.account_id', '=', \Auth::user()->account_id)
			->whereRaw("CONCAT(contacts.first_name, contacts.last_name, contacts.email) <> ''")
			->select(\DB::raw("'Contacts' as type, clients.public_id, CONCAT(contacts.first_name, ' ', contacts.last_name, ' ', contacts.email) as name, '' as token"));

		$invoices = \DB::table('clients')
			->join('invoices', 'invoices.client_id', '=', 'clients.id')
			->where('clients.account_id', '=', \Auth::user()->account_id)
			->where('clients.deleted_at', '=', null)
			->where('invoices.deleted_at', '=', null)
			->select(\DB::raw("'Invoices' as type, invoices.public_id, CONCAT(invoices.invoice_number, ': ', clients.name) as name, invoices.invoice_number as token"));			

		$data = [];

		foreach ($clients->union($contacts)->union($invoices)->get() as $row)
		{
			$type = $row->type;

			if (!isset($data[$type]))
			{
				$data[$type] = [];	
			}			

			$tokens = explode(' ', $row->name);
			$tokens[] = $type;

			if ($type == 'Invoices')
			{
				$tokens[] = intVal($row->token) . '';
			}

			$data[$type][] = [
				'value' => $row->name,
				'public_id' => $row->public_id,
				'tokens' => $tokens
			];
		}
		
    	return $data;
	}
}
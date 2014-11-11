<?php namespace ninja\repositories;

use Client;
use Contact;
use Account;
use Request;
use Session;
use Language;
use User;
use Auth;
use Invitation;
use Invoice;
use InvoiceItem;
use AccountGateway;

class AccountRepository
{
	public function create()
	{
		$account = new Account;
		$account->ip = Request::getClientIp();
		$account->account_key = str_random(RANDOM_KEY_LENGTH);

		if (Session::has(SESSION_LOCALE))
		{
			$locale = Session::get(SESSION_LOCALE);
			if ($language = Language::whereLocale($locale)->first())
			{
				$account->language_id = $language->id;
			}
		}

		$account->save();
		
		$random = str_random(RANDOM_KEY_LENGTH);

		$user = new User;
		$user->password = $random;
		$user->password_confirmation = $random;			
		$user->username = $random;
		$account->users()->save($user, []);
		
		return $account;
	}

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


	public function enableProPlan()
	{		
		if (Auth::user()->isPro())
		{
			return false;
		}

		$ninjaAccount = $this->getNinjaAccount();		
		$lastInvoice = Invoice::withTrashed()->whereAccountId($ninjaAccount->id)->orderBy('public_id', 'DESC')->first();
		$publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;

		$ninjaClient = $this->getNinjaClient($ninjaAccount);
		$invoice = $this->createNinjaInvoice($publicId, $ninjaAccount, $ninjaClient);

		return $invoice;
	}

	private function createNinjaInvoice($publicId, $account, $client)
	{
		$invoice = new Invoice();
		$invoice->account_id = $account->id;
		$invoice->user_id = $account->users()->first()->id;
		$invoice->public_id = $publicId;
		$invoice->client_id = $client->id;
		$invoice->invoice_number = $account->getNextInvoiceNumber();
		$invoice->invoice_date = date_create()->format('Y-m-d');
		$invoice->amount = PRO_PLAN_PRICE;
		$invoice->balance = PRO_PLAN_PRICE;
		$invoice->save();

		$item = new InvoiceItem();
		$item->account_id = $account->id;
		$item->user_id = $account->users()->first()->id;
		$item->public_id = $publicId;
		$item->qty = 1;
		$item->cost = PRO_PLAN_PRICE;
		$item->notes = trans('texts.pro_plan_description');
		$item->product_key = trans('texts.pro_plan_product');				
		$invoice->invoice_items()->save($item);

		$invitation = new Invitation();
		$invitation->account_id = $account->id;
		$invitation->user_id = $account->users()->first()->id;
		$invitation->public_id = $publicId;
		$invitation->invoice_id = $invoice->id;
		$invitation->contact_id = $client->contacts()->first()->id;
		$invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
		$invitation->save();

		return $invoice;
	}

	public function getNinjaAccount()
	{
		$account = Account::whereAccountKey(NINJA_ACCOUNT_KEY)->first();

		if ($account)
		{
			return $account;	
		}
		else
		{
			$account = new Account();
			$account->name = 'Invoice Ninja';
			$account->work_email = 'contact@invoiceninja.com';
			$account->work_phone = '(800) 763-1948';
			$account->account_key = NINJA_ACCOUNT_KEY;
			$account->save();

			$random = str_random(RANDOM_KEY_LENGTH);
			$user = new User();
			$user->registered = true;
			$user->confirmed = true;
			$user->email = 'contact@invoiceninja.com';
			$user->password = $random;
			$user->password_confirmation = $random;			
			$user->username = $random;
			$user->first_name = 'Invoice';
			$user->last_name = 'Ninja';
			$user->notify_sent = true;
			$user->notify_paid = true;
			$account->users()->save($user);			

			$accountGateway = new AccountGateway();
			$accountGateway->user_id = $user->id;
			$accountGateway->gateway_id = NINJA_GATEWAY_ID;
			$accountGateway->public_id = 1;
			$accountGateway->config = NINJA_GATEWAY_CONFIG;
			$account->account_gateways()->save($accountGateway);
		}

		return $account;
	}

	private function getNinjaClient($ninjaAccount)
	{
		$client = Client::whereAccountId($ninjaAccount->id)->wherePublicId(Auth::user()->account_id)->first();

		if (!$client)
		{
			$client = new Client;		
			$client->public_id = Auth::user()->account_id;
			$client->user_id = $ninjaAccount->users()->first()->id;
			$client->currency_id = 1;			
			foreach (['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'work_phone'] as $field) 
			{
				$client->$field = Auth::user()->account->$field;
			}		
			$ninjaAccount->clients()->save($client);

			$contact = new Contact;
			$contact->user_id = $ninjaAccount->users()->first()->id;
			$contact->account_id = $ninjaAccount->id;
			$contact->public_id = Auth::user()->account_id;
			$contact->is_primary = true;
			foreach (['first_name', 'last_name', 'email', 'phone'] as $field) 
			{
				$contact->$field = Auth::user()->$field;	
			}		
			$client->contacts()->save($contact);			
		}

		return $client;
	}

}
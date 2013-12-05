<?php

class AccountController extends \BaseController {

	public function getStarted()
	{	
		$user = false;	

		$guestKey = Input::get('guest_key');

		if ($guestKey) 
		{
			//$user = User::where('password', '=', $guestKey)->firstOrFail();
			$user = User::where('password', '=', $guestKey)->first();

			if ($user && $user->registered)
			{
				exit;
			}
		}

		if (!$user)
		{
			$account = new Account;
			$account->ip = Request::getClientIp();
			$account->account_key = str_random(20);
			$account->save();
			
			$random = str_random(20);

			$user = new User;
			$user->password = $random;
			$account->users()->save($user);
		}

		Auth::login($user, true);
		Session::put('tz', 'US/Eastern');

		return Redirect::to('invoices/create');		
	}

	public function showSection($section = ACCOUNT_DETAILS)  
	{
		if ($section == ACCOUNT_DETAILS)
		{			
			$account = Account::with('users')->findOrFail(Auth::user()->account_id);
			$countries = Country::orderBy('name')->get();
			$timezones = Timezone::orderBy('location')->get();

			return View::make('accounts.details', array('account'=>$account, 'countries'=>$countries, 'timezones'=>$timezones));
		}
		else if ($section == ACCOUNT_SETTINGS)
		{
			$account = Account::with('account_gateways')->findOrFail(Auth::user()->account_id);
			$accountGateway = null;
			$config = null;

			if (count($account->account_gateways) > 0)
			{
				$accountGateway = $account->account_gateways[0];
				$config = $accountGateway->config;
			}			

			$data = [
				'account' => $account,
				'accountGateway' => $accountGateway,
				'config' => json_decode($config),
				'gateways' => Gateway::all()
			];
			
			foreach ($data['gateways'] as $gateway)
			{
				$gateway->fields = Omnipay::create($gateway->provider)->getDefaultParameters();				

				if ($accountGateway && $accountGateway->gateway_id == $gateway->id)
				{
					$accountGateway->fields = $gateway->fields;						
				}
			}	

			return View::make('accounts.settings', $data);
		}
		else if ($section == ACCOUNT_IMPORT)
		{
			return View::make('accounts.import');
		}
		else if ($section == ACCOUNT_EXPORT)
		{
			return View::make('accounts.export');	
		}	
	}

	public function doSection($section = ACCOUNT_DETAILS)
	{
		if ($section == ACCOUNT_DETAILS)
		{
			return AccountController::saveDetails();
		}
		else if ($section == ACCOUNT_SETTINGS)
		{
			return AccountController::saveSettings();
		}
		else if ($section == ACCOUNT_IMPORT)
		{
			return AccountController::importFile();
		}
		else if ($section == ACCOUNT_MAP)
		{
			return AccountController::mapFile();
		}
		else if ($section == ACCOUNT_EXPORT)
		{
			return AccountController::export();
		}		
	}

	private function export()
	{
		$output = fopen("php://output",'w') or die("Can't open php://output");
		header("Content-Type:application/csv"); 
		header("Content-Disposition:attachment;filename=export.csv"); 
		
		$clients = Client::where('account_id','=',Auth::user()->account_id)->get();
		AccountController::exportData($output, $clients->toArray());

		$contacts = DB::table('contacts')->whereIn('client_id', function($query){
            $query->select('client_id')->from('clients')->where('account_id','=',Auth::user()->account_id);
	    })->get();
		AccountController::exportData($output, toArray($contacts));
		
		$invoices = Invoice::where('account_id','=',Auth::user()->account_id)->get();
		AccountController::exportData($output, $invoices->toArray());		

		$invoiceItems = DB::table('invoice_items')->whereIn('invoice_id', function($query){
            $query->select('invoice_id')->from('invoices')->where('account_id','=',Auth::user()->account_id);
	    })->get();
		AccountController::exportData($output, toArray($invoiceItems));

		$payments = Payment::where('account_id','=',Auth::user()->account_id)->get();
		AccountController::exportData($output, $payments->toArray());

		fclose($output);
		exit;
	}

	private function exportData($output, $data)
	{
		if (count($data) > 0)
		{
			fputcsv($output, array_keys($data[0]));
		}

		foreach($data as $record) 
		{
		    fputcsv($output, $record);
		}

		fwrite($output, "\n");
	}

	private function importFile()
	{
		$data = Session::get('data');
		Session::forget('data');

		$map = Input::get('map');
		$count = 0;
		$hasHeaders = Input::get('header_checkbox');
		
		$countries = Country::all();
		$countryMap = [];
		foreach ($countries as $country) {
			$countryMap[strtolower($country->name)] = $country->id;
		}		

		foreach ($data as $row)
		{
			if ($hasHeaders)
			{
				$hasHeaders = false;
				continue;
			}

			$client = Client::createNew();		
			$contact = Contact::createNew();
			$count++;

			foreach ($row as $index => $value)
			{
				$field = $map[$index];

				if ($field == Client::$fieldName)
				{
					$client->name = $value;
				}			
				else if ($field == Client::$fieldPhone)
				{
					$client->work_phone = $value;
				}
				else if ($field == Client::$fieldAddress1)
				{
					$client->address1 = $value;
				}
				else if ($field == Client::$fieldAddress2)
				{
					$client->address2 = $value;
				}
				else if ($field == Client::$fieldCity)
				{
					$client->city = $value;
				}
				else if ($field == Client::$fieldState)
				{
					$client->state = $value;
				}
				else if ($field == Client::$fieldPostalCode)
				{
					$client->postal_code = $value;
				}
				else if ($field == Client::$fieldCountry)
				{
					$value = strtolower($value);
					$client->country_id = isset($countryMap[$value]) ? $countryMap[$value] : null;
				}
				else if ($field == Client::$fieldNotes)
				{
					$client->notes = $value;
				}
				else if ($field == Contact::$fieldFirstName)
				{
					$contact->first_name = $value;
				}
				else if ($field == Contact::$fieldLastName)
				{
					$contact->last_name = $value;
				}
				else if ($field == Contact::$fieldPhone)
				{
					$contact->phone = $value;
				}
				else if ($field == Contact::$fieldEmail)
				{
					$contact->email = $value;
				}				
			}

			$client->save();
			$client->contacts()->save($contact);		
		}

		$message = pluralize('Successfully created ? client', $count);
		Session::flash('message', $message);
		return Redirect::to('clients');
	}

	private function mapFile()
	{
		$file = Input::file('file');
		$name = $file->getRealPath();

		require_once(app_path().'/includes/parsecsv.lib.php');
		$csv = new parseCSV();
		$csv->heading = false;
		$csv->auto($name);

		Session::put('data', $csv->data);

		$headers = false;
		$hasHeaders = false;
		$mapped = array();
		$columns = array('',
			Client::$fieldName,
			Client::$fieldPhone,
			Client::$fieldAddress1,
			Client::$fieldAddress2,
			Client::$fieldCity,
			Client::$fieldState,
			Client::$fieldPostalCode,
			Client::$fieldCountry,
			Client::$fieldNotes,
			Contact::$fieldFirstName,
			Contact::$fieldLastName,
			Contact::$fieldPhone,
			Contact::$fieldEmail
		);

		if (count($csv->data) > 0) 
		{
			$headers = $csv->data[0];
			foreach ($headers as $title) 
			{
				if (strpos(strtolower($title),'name') > 0)
				{
					$hasHeaders = true;
					break;
				}
			}

			for ($i=0; $i<count($headers); $i++)
			{
				$title = strtolower($headers[$i]);
				$mapped[$i] = '';

				if ($hasHeaders)
				{
					$map = array(
						'first' => Contact::$fieldFirstName,
						'last' => Contact::$fieldLastName,
						'email' => Contact::$fieldEmail,
						'mobile' => Contact::$fieldPhone,
						'phone' => Client::$fieldPhone,
						'name|organization' => Client::$fieldName,
						'address|address1' => Client::$fieldAddress1,	
						'address2' => Client::$fieldAddress2,						
						'city' => Client::$fieldCity,
						'state' => Client::$fieldState,
						'zip|postal|code' => Client::$fieldPostalCode,
						'country' => Client::$fieldCountry,
						'note' => Client::$fieldNotes,
					);

					foreach ($map as $search => $column)
					{
						foreach(explode("|", $search) as $string)
						{
							if (strpos($title, $string) !== false)
							{
								$mapped[$i] = $column;
								break(2);
							}
						}
					}
				}
			}
		}

		$data = array(
			'data' => $csv->data, 
			'headers' => $headers,
			'hasHeaders' => $hasHeaders,
			'columns' => $columns,
			'mapped' => $mapped
		);

		return View::make('accounts.import_map', $data);
	}

	private function saveSettings()
	{
		$rules = array();

		if ($gatewayId = Input::get('gateway_id')) 
		{
			$gateway = Gateway::findOrFail($gatewayId);
			$fields = Omnipay::create($gateway->provider)->getDefaultParameters();
			
			foreach ($fields as $field => $details)
			{
				if (!in_array($field, ['testMode', 'developerMode', 'headerImageUrl', 'solutionType', 'landingPage']))
				{
					$rules[$gateway->id.'_'.$field] = 'required';
				}				
			}			
		}
		
		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) 
		{
			return Redirect::to('account/settings')
				->withErrors($validator)
				->withInput();
		} 
		else 
		{
			$account = Account::findOrFail(Auth::user()->account_id);			
			$account->account_gateways()->forceDelete();			

			$account->invoice_terms = Input::get('invoice_terms');
			$account->save();

			if ($gatewayId) 
			{
				$accountGateway = new AccountGateway;
				$accountGateway->gateway_id = $gatewayId;

				$config = new stdClass;
				foreach ($fields as $field => $details)
				{
					$config->$field = Input::get($gateway->id.'_'.$field);
				}			
				
				$accountGateway->config = json_encode($config);
				$account->account_gateways()->save($accountGateway);
			}

			Session::flash('message', 'Successfully updated settings');
			return Redirect::to('account/settings');
		}				
	}

	private function saveDetails()
	{
		$rules = array(
			'name' => 'required',
			'email' => 'email|required'
		);

		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) 
		{
			return Redirect::to('account/details')
				->withErrors($validator)
				->withInput();
		} 
		else 
		{
			$account = Account::findOrFail(Auth::user()->account_id);
			$account->name = Input::get('name');
			$account->address1 = Input::get('address1');
			$account->address2 = Input::get('address2');
			$account->city = Input::get('city');
			$account->state = Input::get('state');
			$account->postal_code = Input::get('postal_code');
			$account->country_id = Input::get('country_id') ? Input::get('country_id') : null;
			$account->timezone_id = Input::get('timezone_id') ? Input::get('timezone_id') : null;
			$account->save();

			$user = $account->users()->first();
			$user->first_name = Input::get('first_name');
			$user->last_name = Input::get('last_name');
			$user->email = Input::get('email');
			$user->phone = Input::get('phone');
			$user->save();

			if (Input::get('timezone_id')) {
				$timezone = Timezone::findOrFail(Input::get('timezone_id'));
				Session::put('tz', $timezone->name);
			}

			/* Logo image file */
			if ($file = Input::file('logo'))
			{
				$path = Input::file('logo')->getRealPath();
				File::delete('logo/' . $account->account_key . '.jpg');
				Image::make($path)->resize(150, 100, true, false)->save('logo/' . $account->account_key . '.jpg');
			}

			Session::flash('message', 'Successfully updated details');
			return Redirect::to('account/details');
		}
	}

	public function checkEmail()
	{		
		$email = User::where('email', '=', Input::get('email'))->where('id', '<>', Auth::user()->id)->first();

		if ($email) {
			return "taken";
		} else {
			return "available";
		}
	}

	public function submitSignup()
	{
		$rules = array(
			'first_name' => 'required',
			'last_name' => 'required',
			'password' => 'required|min:6',
			'email' => 'email|required'
		);

		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) 
		{
			return Redirect::to(Input::get('path'));
		} 

		$user = Auth::user();
		$user->first_name = Input::get('first_name');
		$user->last_name = Input::get('last_name');
		$user->email = Input::get('email');
		$user->password = Input::get('password');
		$user->registered = true;
		$user->save();

		$activities = Activity::scope()->get();
		foreach ($activities as $activity) {
			$activity->message = str_replace('Guest', $user->getFullName(), $activity->message);
			$activity->save();
		}

		/*
		Mail::send(array('html'=>'emails.welcome_html','text'=>'emails.welcome_text'), $data, function($message) use ($user)
		{
		    $message->from('hillelcoren@gmail.com', 'Hillel Coren');
		    $message->to($user->email);
		});
		*/

		Session::flash('message', 'Successfully registered');		
		return Redirect::to(Input::get('path'))->with('clearGuestKey', true);
	}
}
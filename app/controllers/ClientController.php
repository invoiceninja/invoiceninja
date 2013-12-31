<?php

class ClientController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//$clients = Client::orderBy('name')->get();
		//return View::make('clients.index')->with('clients', $clients);

		return View::make('list', array(
			'entityType'=>ENTITY_CLIENT, 
			'title' => '- Clients',
			'columns'=>['checkbox', 'Client', 'Contact', 'Email', 'Date Created', 'Phone', 'Last Login', 'Balance', 'Action']
		));		
	}

	public function getDatatable()
    {    	
    	$query = DB::table('clients')
    				->join('contacts', 'contacts.client_id', '=', 'clients.id')
    				->where('clients.account_id', '=', Auth::user()->account_id)
    				->where('clients.deleted_at', '=', null)
    				->where('contacts.is_primary', '=', true)
    				->select('clients.public_id','clients.name','contacts.first_name','contacts.last_name','clients.balance','clients.last_login','clients.created_at','clients.work_phone','contacts.email','clients.currency_id');

		$filter = Input::get('sSearch');
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

    	//$query->get();
    	//dd(DB::getQueryLog());

        return Datatable::query($query)
    	    ->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; })
    	    ->addColumn('name', function($model) { return link_to('clients/' . $model->public_id, $model->name); })
    	    ->addColumn('first_name', function($model) { return link_to('clients/' . $model->public_id, $model->first_name . ' ' . $model->last_name); })
    	    ->addColumn('email', function($model) { return link_to('clients/' . $model->public_id, $model->email); })
    	    ->addColumn('created_at', function($model) { return Utils::timestampToDateString(strtotime($model->created_at)); })
    	    ->addColumn('work_phone', function($model) { return Utils::formatPhoneNumber($model->work_phone); })    	   
    	    ->addColumn('last_login', function($model) { return Utils::timestampToDateString($model->last_login); })
    	    ->addColumn('balance', function($model) { return Utils::formatMoney($model->balance, $model->currency_id); })    	    
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
  							<li><a href="' . URL::to('clients/'.$model->public_id.'/edit') . '">Edit Client</a></li>
						    <li class="divider"></li>
						    <li><a href="' . URL::to('invoices/create/'.$model->public_id) . '">New Invoice</a></li>						    
						    <li><a href="' . URL::to('payments/create/'.$model->public_id) . '">New Payment</a></li>						    
						    <li><a href="' . URL::to('credits/create/'.$model->public_id) . '">New Credit</a></li>						    
						    <li class="divider"></li>
						    <li><a href="javascript:archiveEntity(' . $model->public_id. ')">Archive Client</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Client</a></li>						    
						  </ul>
						</div>';
    	    })    	   
    	    ->orderColumns('name','first_name','balance','last_login','created_at','email','phone')
    	    ->make();    	    
    }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{		
		$data = array(
			'client' => null, 
			'method' => 'POST', 
			'url' => 'clients', 
			'title' => '- New Client',
			'clientSizes' => ClientSize::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'clientIndustries' => ClientIndustry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get());

		return View::make('clients.edit', $data);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		return $this->save();
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($publicId)
	{
		$client = Client::scope($publicId)->with('contacts', 'client_size', 'client_industry')->firstOrFail();
		Utils::trackViewed($client->name, ENTITY_CLIENT);
		
		$data = array(
			'client' => $client,
			'title' => '- ' . $client->name,
			'hasRecurringInvoices' => Invoice::scope()->where('is_recurring', '=', true)->whereClientId($client->id)->count() > 0
		);

		return View::make('clients.show', $data);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($publicId)
	{
		$client = Client::scope($publicId)->with('contacts')->firstOrFail();
		$data = array(
			'client' => $client, 
			'method' => 'PUT', 
			'url' => 'clients/' . $publicId, 
			'title' => '- ' . $client->name,
			'clientSizes' => ClientSize::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'clientIndustries' => ClientIndustry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get());
		return View::make('clients.edit', $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($publicId)
	{
		return $this->save($publicId);
	}

	private function save($publicId = null)
	{
		$rules = array(
			'email' => 'required'
		);
		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) {
			$url = $publicId ? 'clients/' . $publicId . '/edit' : 'clients/create';
			return Redirect::to($url)
				->withErrors($validator)
				->withInput(Input::except('password'));
		} else {			
			if ($publicId) {
				$client = Client::scope($publicId)->firstOrFail();
			} else {
				$client = Client::createNew();
			}

			$client->name = trim(Input::get('name'));
			$client->work_phone = trim(Input::get('work_phone'));
			$client->address1 = trim(Input::get('address1'));
			$client->address2 = trim(Input::get('address2'));
			$client->city = trim(Input::get('city'));
			$client->state = trim(Input::get('state'));
			$client->postal_code = trim(Input::get('postal_code'));			
			$client->country_id = Input::get('country_id') ? Input::get('country_id') : null;
			$client->private_notes = trim(Input::get('private_notes'));
			$client->client_size_id = Input::get('client_size_id') ? Input::get('client_size_id') : null;
			$client->client_industry_id = Input::get('client_industry_id') ? Input::get('client_industry_id') : null;
			$client->currency_id = Input::get('currency_id') ? Input::get('currency_id') : null;
			$client->payment_terms = Input::get('payment_terms');
			$client->website = trim(Input::get('website'));

			$client->save();

			$data = json_decode(Input::get('data'));
			$contactIds = [];
			$isPrimary = true;
			
			foreach ($data->contacts as $contact)
			{
				if (isset($contact->public_id) && $contact->public_id)
				{
					$record = Contact::scope($contact->public_id)->firstOrFail();
				}
				else
				{
					$record = Contact::createNew();
				}

				$record->email = trim($contact->email);
				$record->first_name = trim($contact->first_name);
				$record->last_name = trim($contact->last_name);
				$record->phone = trim($contact->phone);
				$record->is_primary = $isPrimary;
				$isPrimary = false;

				$client->contacts()->save($record);
				$contactIds[] = $record->public_id;					
			}

			foreach ($client->contacts as $contact)
			{
				if (!in_array($contact->public_id, $contactIds))
				{	
					$contact->forceDelete();
				}
			}
			
			if ($publicId) {
				Session::flash('message', 'Successfully updated client');
			} else {
				Session::flash('message', 'Successfully created client');
			}

			return Redirect::to('clients/' . $client->public_id);
		}

	}

	public function bulk()
	{
		$action = Input::get('action');
		$ids = Input::get('id') ? Input::get('id') : Input::get('ids');		
		$clients = Client::scope($ids)->get();

		foreach ($clients as $client) {			
			if ($action == 'delete') {
				$client->is_deleted = true;
				$client->save();
			} 
			$client->delete();			
		}

		$message = Utils::pluralize('Successfully '.$action.'d ? client', count($clients));
		Session::flash('message', $message);

		return Redirect::to('clients');
	}
}
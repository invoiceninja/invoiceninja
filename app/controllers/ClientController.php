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
			'columns'=>['checkbox', 'Client', 'Contact', 'Balance', 'Last Login', 'Date Created', 'Email', 'Phone', 'Action']
		));		
	}

	public function getDatatable()
    {
    	$clients = Client::with('contacts')->where('account_id','=',Auth::user()->account_id)->get();

        return Datatable::collection($clients)
    	    ->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->id . '">'; })
    	    ->addColumn('name', function($model) { return link_to('clients/' . $model->id, $model->name); })
    	    ->addColumn('contact', function($model) { return $model->contacts[0]->getFullName(); })
    	    ->addColumn('balance', function($model) { return '$' . $model->balance; })    	    
    	    ->addColumn('last_login', function($model) { return $model->contacts[0]->getLastLogin(); })
    	    ->addColumn('date_created', function($model) { return $model->created_at->toFormattedDateString(); })
    	    ->addColumn('email', function($model) { return HTML::mailto($model->contacts[0]->email, $model->contacts[0]->email); })
    	    ->addColumn('phone', function($model) { return $model->contacts[0]->phone; })    	   
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="display:none">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('clients/'.$model->id.'/edit') . '">Edit</a></li>
						    <li class="divider"></li>
						    <li><a href="' . URL::to('clients/'.$model->id.'/archive') . '">Archive</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->id. ')">Delete</a></li>						    
						  </ul>
						</div>';
    	    })    	   
    	    ->orderColumns('name')
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
			'title' => 'New',
			'countries' => Country::orderBy('name')->get());

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
	public function show($id)
	{
		$client = Client::with('contacts')->find($id);
		trackViewed($client->name);

		
		return View::make('clients.show')->with('client', $client);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$client = Client::with('contacts')->find($id);
		$data = array(
			'client' => $client, 
			'method' => 'PUT', 
			'url' => 'clients/' . $id, 
			'title' => 'Edit',
			'countries' => Country::orderBy('name')->get());
		return View::make('clients.edit', $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		return $this->save($id);
	}

	private function save($id = null)
	{
		$rules = array(
			'name'       => 'required'
		);
		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) {
			return Redirect::to('clients/' . $id . '/edit')
				->withErrors($validator)
				->withInput(Input::except('password'));
		} else {			
			if ($id) {
				$client = Client::find($id);
			} else {
				$client = new Client;
				$client->account_id = Auth::user()->account_id;
			}

			$client->name = Input::get('name');
			$client->work_phone = Input::get('work_phone');
			$client->address1 = Input::get('address1');
			$client->address2 = Input::get('address2');
			$client->city = Input::get('city');
			$client->state = Input::get('state');
			$client->notes = Input::get('notes');
			$client->postal_code = Input::get('postal_code');
			if (Input::get('country_id')) {
				$client->country_id = Input::get('country_id');
			}
			$client->save();

			$data = json_decode(Input::get('data'));
			$contactIds = [];

			foreach ($data->contacts as $contact)
			{
				if (isset($contact->id) && $contact->id)
				{
					$record = Contact::find($contact->id);
				}
				else
				{
					$record = new Contact;
				}

				$record->email = $contact->email;
				$record->first_name = $contact->first_name;
				$record->last_name = $contact->last_name;
				$record->phone = $contact->phone;

				$client->contacts()->save($record);
				$contactIds[] = $record->id;					
			}

			foreach ($client->contacts as $contact)
			{
				if (!in_array($contact->id, $contactIds))
				{	
					$contact->forceDelete();
				}
			}
			
			Session::flash('message', 'Successfully updated client');
			return Redirect::to('clients/' . $client->id);
		}

	}

	public function bulk()
	{
		$action = Input::get('action');
		$ids = Input::get('ids');
		$clients = Client::find($ids);

		foreach ($clients as $client) {
			if ($action == 'archive') {
				$client->delete();
			} else if ($action == 'delete') {
				$client->forceDelete();
			} 
		}

		$message = pluralize('Successfully '.$action.'d ? client', count($ids));
		Session::flash('message', $message);

		return Redirect::to('clients');
	}

	public function archive($id)
	{
		$client = Client::find($id);
		$client->delete();

		foreach ($client->invoices as $invoice)
		{
			$invoice->delete();
		}

		Session::flash('message', 'Successfully archived ' . $client->name);
		return Redirect::to('clients');		
	}

	public function delete($id)
	{
		$client = Client::find($id);
		$client->forceDelete();

		Session::flash('message', 'Successfully deleted ' . $client->name);
		return Redirect::to('clients');		
	}
}
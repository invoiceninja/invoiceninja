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
			'columns'=>['checkbox', 'Client', 'Contact', 'Balance', 'Last Login', 'Date Created', 'Email', 'Phone', 'Action']
		));		
	}

	public function getDatatable()
    {
    	$query = DB::table('clients')->join('contacts', 'clients.id', '=','contacts.client_id')->where('contacts.is_primary', '=', true);

        return Datatable::query($query)
    	    ->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; })
    	    ->addColumn('name', function($model) { return link_to('clients/' . $model->public_id, $model->name); })
    	    ->addColumn('first_name', function($model) { return $model->first_name . ' ' . $model->last_name; })
    	    ->addColumn('balance', function($model) { return '$' . $model->balance; })    	    
    	    ->addColumn('clients.last_login', function($model) { return timestampToDateString($model->last_login); })
    	    ->addColumn('clients.created_at', function($model) { return timestampToDateString($model->created_at); })
    	    ->addColumn('email', function($model) { return HTML::mailto($model->email, $model->email); })
    	    ->addColumn('phone', function($model) { return $model->phone; })    	   
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
  							<li><a href="' . URL::to('invoices/create/'.$model->public_id) . '">New Invoice</a></li>						    
						    <li><a href="' . URL::to('clients/'.$model->public_id.'/edit') . '">Edit Client</a></li>
						    <li class="divider"></li>
						    <li><a href="javascript:archiveEntity(' . $model->public_id. ')">Archive Client</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Client</a></li>						    
						  </ul>
						</div>';
    	    })    	   
    	    ->orderColumns('name','first_name','balance','clients.last_login','clients.created_at','email','phone')
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
	public function show($publicId)
	{
		$client = Client::scope($publicId)->with('contacts')->firstOrFail();
		trackViewed($client->name);
		
		$data = array(
			'client' => $client,
			'title' => '- ' . $client->name);

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
			'countries' => Country::orderBy('name')->get());
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
			'name'       => 'required'
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

				$record->email = $contact->email;
				$record->first_name = $contact->first_name;
				$record->last_name = $contact->last_name;
				$record->phone = $contact->phone;
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
			
			Session::flash('message', 'Successfully updated client');
			return Redirect::to('clients/' . $client->public_id);
		}

	}

	public function bulk()
	{
		$action = Input::get('action');
		$ids = Input::get('ids') ? Input::get('ids') : [Input::get('id')];
		
		$clients = Client::scope($ids)->get();

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
}
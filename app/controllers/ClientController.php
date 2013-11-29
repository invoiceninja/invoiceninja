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

		return View::make('clients.index');
	}

	public function getDatatable()
    {
        return Datatable::collection(Client::with('contacts')->where('account_id','=',Auth::user()->account_id)->get())
    	    ->addColumn('name', function($model)
    	    	{
    	    		//return $model->name;
    	    		return link_to('clients/' . $model->id, $model->name);
    	    	})
    	    ->addColumn('contact', function($model)
    	    	{
    	    		return $model->contacts[0]->getFullName();
    	    	})
    	    ->addColumn('last_login', function($model)
    	    	{
    	    		return $model->contacts[0]->lastLogin();
    	    	})
    	    ->addColumn('email', function($model)
    	    	{
    	    		//return $model->contacts[0]->email;
    	    		return HTML::mailto($model->contacts[0]->email, $model->contacts[0]->email);
    	    	})
    	    ->addColumn('phone', function($model)
    	    	{
    	    		return $model->contacts[0]->phone;
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
		trackViewed(Request::url(), $client->name);

		
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
			$client->country_id = Input::get('country_id');
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

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$client = Client::find($id);
		$client->delete();

		// redirect
		Session::flash('message', 'Successfully deleted the client');
		return Redirect::to('clients');
	}
}
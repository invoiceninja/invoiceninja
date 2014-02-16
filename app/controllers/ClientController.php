<?php

use ninja\repositories\ClientRepository;

class ClientController extends \BaseController {

	protected $clientRepo;

	public function __construct(ClientRepository $clientRepo)
	{
		parent::__construct();

		$this->clientRepo = $clientRepo;
	}	

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		return View::make('list', array(
			'entityType'=>ENTITY_CLIENT, 
			'title' => '- Clients',
			'columns'=>['checkbox', 'Client', 'Contact', 'Email', 'Date Created', 'Last Login', 'Balance', 'Action']
		));		
	}

	public function getDatatable()
    {    	
    	$clients = $this->clientRepo->find(Input::get('sSearch'));

        return Datatable::query($clients)
    	    ->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; })
    	    ->addColumn('name', function($model) { return link_to('clients/' . $model->public_id, $model->name); })
    	    ->addColumn('first_name', function($model) { return link_to('clients/' . $model->public_id, $model->first_name . ' ' . $model->last_name); })
    	    ->addColumn('email', function($model) { return link_to('clients/' . $model->public_id, $model->email); })
    	    ->addColumn('created_at', function($model) { return Utils::timestampToDateString(strtotime($model->created_at)); })
    	    ->addColumn('last_login', function($model) { return Utils::timestampToDateString(strtotime($model->last_login)); })
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
    	    ->make();    	    
    }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{		
		if (Client::scope()->count() > MAX_NUM_CLIENTS)
		{
			return View::make('error', ['error' => "Sorry, you've exceeded the limit of " . MAX_NUM_CLIENTS . " clients"]);
		}

		$data = array(
			'client' => null, 
			'method' => 'POST', 
			'url' => 'clients', 
			'title' => '- New Client',
			'sizes' => Size::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'industries' => Industry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
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
		$client = Client::scope($publicId)->with('contacts', 'size', 'industry')->firstOrFail();
		Utils::trackViewed($client->getDisplayName(), ENTITY_CLIENT);
		
		$data = array(
			'showBreadcrumbs' => false,
			'client' => $client,
			'credit' => $client->getTotalCredit(),
			'title' => '- ' . $client->getDisplayName(),
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
			'sizes' => Size::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'industries' => Industry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
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

		if ($validator->fails()) 
		{
			$url = $publicId ? 'clients/' . $publicId . '/edit' : 'clients/create';
			return Redirect::to($url)
				->withErrors($validator)
				->withInput(Input::except('password'));
		} 
		else 
		{			
			if ($publicId) 
			{
				$client = Client::scope($publicId)->firstOrFail();
			} 
			else 
			{
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
			$client->size_id = Input::get('size_id') ? Input::get('size_id') : null;
			$client->industry_id = Input::get('industry_id') ? Input::get('industry_id') : null;
			$client->currency_id = Input::get('currency_id') ? Input::get('currency_id') : 1;
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

				$record->email = trim(strtolower($contact->email));
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
					$contact->delete();
				}
			}
						
			if ($publicId) 
			{
				Session::flash('message', 'Successfully updated client');
			} 
			else 
			{
				Activity::createClient($client);
				Session::flash('message', 'Successfully created client');
			}

			return Redirect::to('clients/' . $client->public_id);
		}

	}

	public function bulk()
	{
		$action = Input::get('action');
		$ids = Input::get('id') ? Input::get('id') : Input::get('ids');		
		$count = $this->clientRepo->bulk($ids, $action);

		$message = Utils::pluralize('Successfully '.$action.'d ? client', $count);
		Session::flash('message', $message);

		return Redirect::to('clients');
	}
}
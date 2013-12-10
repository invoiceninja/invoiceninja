<?php

class RecurringInvoiceController extends \BaseController {

	public function index()
	{
		return View::make('list', array(
			'entityType'=>ENTITY_RECURRING_INVOICE, 
			'title' => '- Invoices',
			'columns'=>['checkbox', 'Client', 'Total', 'How Often', 'Start Date', 'End Date', 'Action']
		));
	}

	public function getDatatable($clientPublicId = null)
    {
    	$query = DB::table('recurring_invoices')
    				->join('clients', 'clients.id', '=', 'recurring_invoices.client_id')
    				->join('frequencies', 'recurring_invoices.frequency_id', '=', 'frequencies.id')
					->where('recurring_invoices.account_id', '=', Auth::user()->account_id)
    				->where('recurring_invoices.deleted_at', '=', null)
					->select('clients.public_id as client_public_id', 'clients.name as client_name', 'recurring_invoices.public_id', 'total', 'frequencies.name as frequency', 'start_date', 'end_date');

    	if ($clientPublicId) {
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	//$table->addColumn('invoice_number', function($model) { return link_to('invoices/' . $model->public_id . '/edit', $model->invoice_number); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, $model->client_name); });
    	}
    	
    	return $table->addColumn('total', function($model) { return '$' . money_format('%i', $model->total); })
    		->addColumn('frequency', function($model) { return $model->frequency; })
    	    ->addColumn('start_date', function($model) { return Utils::fromSqlDate($model->start_date); })
    	    ->addColumn('end_date', function($model) { return Utils::fromSqlDate($model->end_date); })
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/edit') . '">Edit Recurring Invoice</a></li>
						    <li class="divider"></li>
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/archive') . '">Archive Recurring Invoice</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">Delete Recurring Invoice</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
    	    ->orderColumns('client','total','how_often','start_date','end_date')
    	    ->make();    	
    }

	public function edit($publicId)
	{
		$invoice = RecurringInvoice::scope($publicId)->with('account.country', 'client', 'invoice_items')->firstOrFail();
		Utils::trackViewed($invoice->getName() . ' - ' . $invoice->client->name, ENTITY_RECURRING_INVOICE);
		
		$data = array(
				'account' => $invoice->account,
				'invoice' => $invoice, 
				'method' => 'PUT', 
				'url' => 'recurring_invoices/' . $publicId, 
				'title' => '- ' . $invoice->getName(),
				'client' => $invoice->client);
		$data = array_merge($data, InvoiceController::getViewModel(true));
		return View::make('invoices.edit', $data);
	}

	public function create($clientPublicId = 0)
	{		
		$client = null;
		$invoiceNumber = Auth::user()->account->getNextInvoiceNumber();
		$account = Account::with('country')->findOrFail(Auth::user()->account_id);

		if ($clientPublicId) {
			$client = Client::scope($clientPublicId)->firstOrFail();
        }

		$data = array(
				'account' => $account,
				'invoice' => null, 
				'invoiceNumber' => $invoiceNumber,
				'method' => 'POST', 
				'url' => 'recurring_invoices', 
				'title' => '- New Recurring Invoice',
				'client' => $client,
				'items' => json_decode(Input::old('items')));
		$data = array_merge($data, InvoiceController::getViewModel(true));				
		return View::make('invoices.edit', $data);
	}

	public function store()
	{		
		return RecurringInvoiceController::save();
	}

	private function save($publicId = null)
	{	
		$action = Input::get('action');
		
		if ($action == 'archive' || $action == 'delete')
		{
			return RecurringInvoiceController::bulk();
		}

		$rules = array(
			'client' => 'required',
		);
		$validator = Validator::make(Input::all(), $rules);

		if ($validator->fails()) {
			return Redirect::to('invoices/create')
				->withInput()
				->withErrors($validator);
		} else {			

			$clientPublicId = Input::get('client');

			if ($clientPublicId == "-1") 
			{
				$client = Client::createNew();
				$client->name = trim(Input::get('name'));
				$client->work_phone = trim(Input::get('work_phone'));
				$client->address1 = trim(Input::get('address1'));
				$client->address2 = trim(Input::get('address2'));
				$client->city = trim(Input::get('city'));
				$client->state = trim(Input::get('state'));
				$client->postal_code = trim(Input::get('postal_code'));
				if (Input::get('country_id')) {
					$client->country_id = Input::get('country_id');
				}
				$client->save();				
				$clientId = $client->id;	

				$contact = Contact::createNew();
				$contact->is_primary = true;
				$contact->first_name = trim(Input::get('first_name'));
				$contact->last_name = trim(Input::get('last_name'));
				$contact->phone = trim(Input::get('phone'));
				$contact->email = trim(Input::get('email'));
				$client->contacts()->save($contact);
			}
			else
			{
				$client = Client::scope($clientPublicId)->with('contacts')->firstOrFail();
				$contact = $client->contacts()->first();
			}

			if ($publicId) {
				$invoice = RecurringInvoice::scope($publicId)->firstOrFail();
				$invoice->invoice_items()->forceDelete();
			} else {				
				$invoice = RecurringInvoice::createNew();			
			}			
			
			$invoice->client_id = $client->id;
			$invoice->discount = 0;
			$invoice->frequency_id = Input::get('frequency');
			$invoice->start_date = Utils::toSqlDate(Input::get('start_date', null));
			$invoice->end_date = Utils::toSqlDate(Input::get('end_date', null));			

			$invoice->notes = Input::get('notes');
			$client->invoices()->save($invoice);
			
			$items = json_decode(Input::get('items'));
			$total = 0;

			foreach ($items as $item) 
			{
				if (!isset($item->cost)) {
					$item->cost = 0;
				}
				if (!isset($item->qty)) {
					$item->qty = 0;
				}

				$total += floatval($item->qty) * floatval($item->cost);
			}
			
			$invoice->total = $total;
			$invoice->save();

			foreach ($items as $item) 
			{
				if (!$item->cost && !$item->qty && !$item->product_key && !$item->notes)
				{
					continue;
				}

				if ($item->product_key)
				{
					$product = Product::findProductByKey(trim($item->product_key));

					if (!$product)
					{
						$product = Product::createNew();						
						$product->product_key = trim($item->product_key);
					}

					/*
					$product->notes = $item->notes;
					$product->cost = $item->cost;
					$product->qty = $item->qty;
					*/
					
					$product->save();
				}

				$invoiceItem = RecurringInvoiceItem::createNew();
				$invoiceItem->product_id = isset($product) ? $product->id : null;
				$invoiceItem->product_key = trim($item->product_key);
				$invoiceItem->notes = trim($item->notes);
				$invoiceItem->cost = floatval($item->cost);
				$invoiceItem->qty = floatval($item->qty);

				$invoice->invoice_items()->save($invoiceItem);
			}

			Session::flash('message', 'Successfully saved recurring invoice');		
			$url = 'recurring_invoices/' . $invoice->public_id . '/edit';
			return Redirect::to($url);
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($publicId)
	{
		return Redirect::to('recurring_invoices/'.$publicId.'/edit');
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($publicId)
	{
		return RecurringInvoiceController::save($publicId);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function bulk()
	{
		$action = Input::get('action');
		$ids = Input::get('id') ? Input::get('id') : Input::get('ids');
		$invoices = RecurringInvoice::scope($ids)->get();

		foreach ($invoices as $invoice) {
			if ($action == 'archive') {
				$invoice->delete();
			} else if ($action == 'delete') {
				$invoice->forceDelete();
			} 
		}

		$message = Utils::pluralize('Successfully '.$action.'d ? recurring invoice', count($ids));
		Session::flash('message', $message);

		return Redirect::to('invoices');
	}
}
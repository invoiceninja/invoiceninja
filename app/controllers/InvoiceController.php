<?php

class InvoiceController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		return View::make('list', array(
			'entityType'=>ENTITY_INVOICE, 
			'title' => '- Invoices',
			'columns'=>['checkbox', 'Invoice Number', 'Client', 'Total', 'Amount Due', 'Invoice Date', 'Due Date', 'Status', 'Action']
		));
	}

	public function getDatatable($clientPublicId = null)
    {
    	$query = DB::table('invoices')
    				->join('clients', 'clients.id', '=','invoices.client_id')
					->join('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
					->where('invoices.account_id', '=', Auth::user()->account_id)
    				->where('invoices.deleted_at', '=', null)
					->select('clients.public_id as client_public_id', 'invoice_number', 'clients.name as client_name', 'invoices.public_id', 'total', 'invoices.balance', 'invoice_date', 'due_date', 'invoice_statuses.name as invoice_status_name');

    	if ($clientPublicId) {
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	$table->addColumn('invoice_number', function($model) { return link_to('invoices/' . $model->public_id . '/edit', $model->invoice_number); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, $model->client_name); });
    	}
    	
    	return $table->addColumn('total', function($model){ return '$' . money_format('%i', $model->total); })
    		->addColumn('balance', function($model) { return '$' . money_format('%i', $model->balance); })
    	    ->addColumn('invoice_date', function($model) { return Utils::fromSqlDate($model->invoice_date); })
    	    ->addColumn('due_date', function($model) { return Utils::fromSqlDate($model->due_date); })
    	    ->addColumn('invoice_status_name', function($model) { return $model->invoice_status_name; })
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/edit') . '">Edit Invoice</a></li>
						    <li class="divider"></li>
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/archive') . '">Archive Invoice</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">Delete Invoice</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
    	    ->orderColumns('invoice_number','client','total','balance','invoice_date','due_date','invoice_status_name')
    	    ->make();    	
    }


	public function view($invitationKey)
	{
		$invitation = Invitation::with('user', 'invoice.account', 'invoice.invoice_items', 'invoice.client.account.account_gateways')
			->where('invitation_key', '=', $invitationKey)->firstOrFail();				
		
		$user = $invitation->user;		
		$invoice = $invitation->invoice;
		
		if ($invoice->invoice_status_id < INVOICE_STATUS_VIEWED) {
			$invoice->invoice_status_id = INVOICE_STATUS_VIEWED;
			$invoice->save();
		}
		
		$now = Carbon::now()->toDateTimeString();

		$invitation->viewed_date = $now;
		$invitation->save();

		$client = $invoice->client;
		$client->last_login = $now;
		$client->save();

		Activity::viewInvoice($invitation);

		$data = array(
			'invoice' => $invoice,
			'invitation' => $invitation
		);

		return View::make('invoices.view', $data);
	}

	private function createGateway($accountGateway)
	{
        $gateway = Omnipay::create($accountGateway->gateway->provider);	
        $config = json_decode($accountGateway->config);
        
        /*
        $gateway->setSolutionType ("Sole");
        $gateway->setLandingPage("Billing");
        */
		
		foreach ($config as $key => $val)
		{
			if (!$val)
			{
				continue;
			}

			$function = "set" . ucfirst($key);
			$gateway->$function($val);
		}
		
		return $gateway;		
	}

	private function getPaymentDetails($invoice)
	{
		$data = array(
		    'firstName' => '',
		    'lastName' => '',
		);

		$card = new CreditCard($data);
		
		return [
			    'amount' => $invoice->total,
			    'card' => $card,
			    'currency' => 'USD',
			    'returnUrl' => URL::to('complete'),
			    'cancelUrl' => URL::to('/'),
		];
	}

	public function show_payment($invitationKey)
	{
		$invitation = Invitation::with('invoice.invoice_items', 'invoice.client.account.account_gateways.gateway')->where('invitation_key', '=', $invitationKey)->firstOrFail();
		$invoice = $invitation->invoice;		
		$accountGateway = $invoice->client->account->account_gateways[0];
		$gateway = InvoiceController::createGateway($accountGateway);

		try
		{
			$details = InvoiceController::getPaymentDetails($invoice);			
			$response = $gateway->purchase($details)->send();			
			$ref = $response->getTransactionReference();

			if (!$ref)
			{
				var_dump($response);
				exit('Sorry, there was an error processing your payment. Please try again later.');
			}

			$payment = Payment::createNew();
			$payment->invitation_id = $invitation->id;
			$payment->invoice_id = $invoice->id;
			$payment->amount = $invoice->total;
			$payment->client_id = $invoice->client_id;
			//$payment->contact_id = 0; // TODO_FIX
			$payment->transaction_reference = $ref;
			$payment->save();

			$invoice->balance = floatval($invoice->total) - floatval($paymount->amount);

			if ($response->isSuccessful())
			{
				
			}
			else if ($response->isRedirect()) 
			{
	    		$response->redirect();	    	
	    	}
	    	else
	    	{

	    	}
	    } 
	    catch (\Exception $e) 
	    {
			exit('Sorry, there was an error processing your payment. Please try again later.<p>'.$e);
		}

		exit;
	}

	public function do_payment()
	{
		$payerId = Request::query('PayerID');
		$token = Request::query('token');				

		$payment = Payment::with('invitation', 'invoice.invoice_items')->where('transaction_reference','=',$token)->firstOrFail();
		$invoice = Invoice::with('client.account.account_gateways.gateway')->where('id', '=', $payment->invoice_id)->firstOrFail();
		$accountGateway = $invoice->client->account->account_gateways[0];
		$gateway = InvoiceController::createGateway($accountGateway);
	
		try
		{
			$details = InvoiceController::getPaymentDetails($payment->invoice);
			$response = $gateway->completePurchase($details)->send();
			$ref = $response->getTransactionReference();

			if ($response->isSuccessful())
			{
				$payment->payer_id = $payerId;
				$payment->transaction_reference = $ref;
				$payment->save();

				if ($payment->amount >= $invoice->amount) {
					$invoice->invoice_status_id = INVOICE_STATUS_PAID;
				} else {
					$invoice->invoice_status_id = INVOICE_STATUS_PARTIAL;
				}
				$invoice->save();
				
				Session::flash('message', 'Successfully applied payment');	
				return Redirect::to('view/' . $payment->invitation->invitation_key);				
			}
			else
			{
				exit($response->getMessage());
			}
	    } 
	    catch (\Exception $e) 
	    {
			exit('Sorry, there was an error processing your payment. Please try again later.' . $e);
		}
	}


	public function edit($publicId)
	{
		$invoice = Invoice::scope($publicId)->with('account.country', 'client', 'invoice_items')->firstOrFail();
		Utils::trackViewed($invoice->invoice_number . ' - ' . $invoice->client->name, ENTITY_INVOICE);
		
		$data = array(
				'account' => $invoice->account,
				'invoice' => $invoice, 
				'method' => 'PUT', 
				'url' => 'invoices/' . $publicId, 
				'title' => '- ' . $invoice->invoice_number,
				'client' => $invoice->client);
		$data = array_merge($data, InvoiceController::getViewModel());
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
				'url' => 'invoices', 
				'title' => '- New Invoice',
				'client' => $client,
				'items' => json_decode(Input::old('items')));
		$data = array_merge($data, InvoiceController::getViewModel());				
		return View::make('invoices.edit', $data);
	}

	private static function getViewModel()
	{
		return [
			'account' => Auth::user()->account,
			'products' => Product::scope()->get(array('product_key','notes','cost','qty')),
			'countries' => Country::orderBy('name')->get(),
			'clients' => Client::scope()->orderBy('name')->get(),
			'frequencies' => array(
				0 => '',
				1 => 'Weekly',
				2 => 'Two weeks',
				3 => 'Four weeks',
				4 => 'Monthly',
				5 => 'Three months',
				6 => 'Six months',
				7 => 'Annually'
			)
		];
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{		
		return InvoiceController::save();
	}

	private function save($publicId = null)
	{	
		$action = Input::get('action');
		
		if ($action == 'archive' || $action == 'delete')
		{
			return InvoiceController::bulk();
		}

		$rules = array(
			'client' => 'required',
			'invoice_number' => 'required',
			'invoice_date' => 'required'
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
				$invoice = Invoice::scope($publicId)->firstOrFail();
				$invoice->invoice_items()->forceDelete();
			} else {
				$invoice = Invoice::createNew();			
			}			
			
			$invoice->invoice_number = trim(Input::get('invoice_number'));
			$invoice->discount = 0;
			$invoice->invoice_date = Utils::toSqlDate(Input::get('invoice_date'));
			$invoice->due_date = Utils::toSqlDate(Input::get('due_date', null));
			$invoice->notes = Input::get('notes');

			$invoice->how_often = Input::get('how_often');
			$invoice->start_date = Utils::toSqlDate(Input::get('start_date', null));
			$invoice->end_date = Utils::toSqlDate(Input::get('end_date', null));			

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
						
			if ($action == 'email' && $invoice->invoice_status_id == INVOICE_STATUS_DRAFT)
			{
				$invoice->invoice_status_id = INVOICE_STATUS_SENT;
				
				$client->balance = $invoice->client->balance + $invoice->total;
				$client->save();
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

				$invoiceItem = InvoiceItem::createNew();
				$invoiceItem->product_id = isset($product) ? $product->id : null;
				$invoiceItem->product_key = trim($item->product_key);
				$invoiceItem->notes = trim($item->notes);
				$invoiceItem->cost = floatval($item->cost);
				$invoiceItem->qty = floatval($item->qty);

				$invoice->invoice_items()->save($invoiceItem);
			}

			/*
			*/

			if ($action == 'email') 
			{
				$data = array('link' => URL::to('view') . '/' . $invoice->invoice_key);
				/*
				Mail::send(array('html'=>'emails.invoice_html','text'=>'emails.invoice_text'), $data, function($message) use ($contact)
				{
				    $message->from('hillelcoren@gmail.com', 'Hillel Coren');
				    $message->to($contact->email);
				});
				*/

				$invitation = Invitation::createNew();
				$invitation->invoice_id = $invoice->id;
				$invitation->user_id = Auth::user()->id;
				$invitation->contact_id = $contact->id;
				$invitation->invitation_key = str_random(20);				
				$invitation->save();

				Session::flash('message', 'Successfully emailed invoice');
			} else {				
				Session::flash('message', 'Successfully saved invoice');
			}

			$url = 'invoices/' . $invoice->public_id . '/edit';
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
		return Redirect::to('invoices/'.$publicId.'/edit');
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($publicId)
	{
		return InvoiceController::save($publicId);
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
		$invoices = Invoice::scope($ids)->get();

		foreach ($invoices as $invoice) {
			if ($action == 'archive') {
				$invoice->delete();
			} else if ($action == 'delete') {
				$invoice->forceDelete();
			} 
		}

		$message = Utils::pluralize('Successfully '.$action.'d ? invoice', count($ids));
		Session::flash('message', $message);

		return Redirect::to('invoices');
	}
}
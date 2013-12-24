<?php

use Ninja\Mailers\ContactMailer as Mailer;

class InvoiceController extends \BaseController {

	protected $mailer;

	public function __construct(Mailer $mailer)
	{
		parent::__construct();

		$this->mailer = $mailer;
	}	

	public function index()
	{
		$data = [
			'title' => '- Invoices',
			'entityType'=>ENTITY_INVOICE, 
			'columns'=>['checkbox', 'Invoice Number', 'Client', 'Invoice Date', 'Invoice Total', 'Balance Due', 'Due Date', 'Status', 'Action']
		];

		if (Invoice::scope()->where('is_recurring', '=', true)->count() > 0)
		{
			$data['secEntityType'] = ENTITY_RECURRING_INVOICE;
			$data['secColumns'] = ['checkbox', 'Frequency', 'Client', 'Start Date', 'End Date', 'Invoice Total', 'Action'];
		}

		return View::make('list', $data);
	}

	public function getDatatable($clientPublicId = null)
    {
    	$query = DB::table('invoices')
    				->join('clients', 'clients.id', '=','invoices.client_id')
					->join('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
					->where('invoices.account_id', '=', Auth::user()->account_id)
    				->where('invoices.deleted_at', '=', null)
    				->where('clients.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', false)    				
					->select('clients.public_id as client_public_id', 'invoice_number', 'clients.name as client_name', 'invoices.public_id', 'amount', 'invoices.balance', 'invoice_date', 'due_date', 'invoice_statuses.name as invoice_status_name');

    	if ($clientPublicId) {
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

		$filter = Input::get('sSearch');
    	if ($filter)
    	{
    		$query->where(function($query) use ($filter)
            {
            	$query->where('clients.name', 'like', '%'.$filter.'%')
            		  ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
            		  ->orWhere('invoice_statuses.name', 'like', '%'.$filter.'%');
            });
    	}

    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	$table->addColumn('invoice_number', function($model) { return link_to('invoices/' . $model->public_id . '/edit', $model->invoice_number); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, $model->client_name); });
    	}
    	
    	return $table->addColumn('invoice_date', function($model) { return Utils::fromSqlDate($model->invoice_date); })    	    
    		->addColumn('total', function($model) { return '$' . money_format('%i', $model->amount); })
    		->addColumn('balance', function($model) { return '$' . money_format('%i', $model->balance); })
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
						    <li><a href="javascript:archiveEntity(' . $model->public_id . ')">Archive Invoice</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">Delete Invoice</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
    	    ->orderColumns('invoice_number','client','total','balance','invoice_date','due_date','invoice_status_name')
    	    ->make();    	
    }

	public function getRecurringDatatable($clientPublicId = null)
    {
    	$query = DB::table('invoices')
    				->join('clients', 'clients.id', '=','invoices.client_id')
					->join('frequencies', 'frequencies.id', '=', 'invoices.frequency_id')
					->where('invoices.account_id', '=', Auth::user()->account_id)
    				->where('invoices.deleted_at', '=', null)
    				->where('invoices.is_recurring', '=', true)
					->select('clients.public_id as client_public_id', 'clients.name as client_name', 'invoices.public_id', 'amount', 'frequencies.name as frequency', 'start_date', 'end_date');

    	if ($clientPublicId) {
    		$query->where('clients.public_id', '=', $clientPublicId);
    	}

		$filter = Input::get('sSearch');
    	if ($filter)
    	{
    		$query->where(function($query) use ($filter)
            {
            	$query->where('clients.name', 'like', '%'.$filter.'%')
            		  ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%');
            });
    	}
    	
    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	$table->addColumn('frequency', function($model) { return link_to('invoices/' . $model->public_id, $model->frequency); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, $model->client_name); });
    	}
    	
    	return $table->addColumn('start_date', function($model) { return Utils::fromSqlDate($model->start_date); })
    	    ->addColumn('end_date', function($model) { return Utils::fromSqlDate($model->end_date); })    	    
    	    ->addColumn('total', function($model) { return '$' . money_format('%i', $model->amount); })
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							Select <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/edit') . '">Edit Invoice</a></li>
						    <li class="divider"></li>
						    <li><a href="javascript:archiveEntity(' . $model->public_id . ')">Archive Invoice</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">Delete Invoice</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
    	    ->orderColumns('client','total','frequency','start_date','end_date')
    	    ->make();    	
    }


	public function view($invitationKey)
	{
		$invitation = Invitation::with('user', 'invoice.account', 'invoice.client', 'invoice.invoice_items', 'invoice.client.account.account_gateways')
			->where('invitation_key', '=', $invitationKey)->firstOrFail();				
		
		$user = $invitation->user;		
		$invoice = $invitation->invoice;
		
		if (!$invoice || $invoice->is_deleted) {
			return View::make('invoices.deleted');
		}

		$client = $invoice->client;

		if (!$client || $client->is_deleted) {
			return View::make('invoices.deleted');
		}

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
			    'amount' => $invoice->amount,
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
			$payment->amount = $invoice->amount;
			$payment->client_id = $invoice->client_id;
			//$payment->contact_id = 0; // TODO_FIX
			$payment->transaction_reference = $ref;
			$payment->save();

			$invoice->balance = floatval($invoice->amount) - floatval($paymount->amount);

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
		
    	$contactIds = DB::table('invitations')
    				->join('contacts', 'contacts.id', '=','invitations.contact_id')
    				->where('invitations.invoice_id', '=', $invoice->id)
					->where('invitations.account_id', '=', Auth::user()->account_id)
    				->where('invitations.deleted_at', '=', null)
    				->select('contacts.public_id')->lists('public_id');
    	
		$data = array(
				'account' => $invoice->account,
				'invoice' => $invoice, 
				'method' => 'PUT', 
				'invitationContactIds' => $contactIds,
				'clientSizes' => ClientSize::orderBy('id')->get(),
				'clientIndustries' => ClientIndustry::orderBy('name')->get(),
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
				'clientSizes' => ClientSize::orderBy('id')->get(),
				'clientIndustries' => ClientIndustry::orderBy('name')->get(),
				'title' => '- New Invoice',
				'client' => $client,
				'items' => json_decode(Input::old('items')));
		$data = array_merge($data, InvoiceController::getViewModel());				
		return View::make('invoices.edit', $data);
	}

	public static function getViewModel()
	{
		return [
			'account' => Auth::user()->account,
			'products' => Product::scope()->get(array('product_key','notes','cost','qty')),
			'countries' => Country::orderBy('name')->get(),
			'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
			'frequencies' => array(
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

		$input = json_decode(Input::get('data'));			
		$inputClient = $input->client;
		$inputClient->name = trim($inputClient->name);
		
		if (!$inputClient->name) {
			return Redirect::to('invoices/create')
				->withInput();
		} else {			

			$clientPublicId = $input->client->public_id;
			
			if ($clientPublicId == "-1") 
			{
				$client = Client::createNew();
				$contact = Contact::createNew();
				$contact->is_primary = true;			
			}
			else
			{
				$client = Client::scope($clientPublicId)->with('contacts')->firstOrFail();
				$contact = $client->contacts()->where('is_primary', '=', true)->firstOrFail();
			}
			
			$inputClient = $input->client;			
			$client->name = trim($inputClient->name);
			$client->work_phone = trim($inputClient->work_phone);
			$client->address1 = trim($inputClient->address1);
			$client->address2 = trim($inputClient->address2);
			$client->city = trim($inputClient->city);
			$client->state = trim($inputClient->state);
			$client->postal_code = trim($inputClient->postal_code);
			$client->country_id = $inputClient->country_id ? $inputClient->country_id : null;
			$client->notes = trim($inputClient->notes);
			$client->client_size_id = $inputClient->client_size_id ? $inputClient->client_size_id : null;
			$client->client_industry_id = $inputClient->client_industry_id ? $inputClient->client_industry_id : null;
			$client->website = trim($inputClient->website);
			$client->save();
			
			$isPrimary = true;
			$contacts = [];
			$contactIds = [];
			$sendInvoiceIds = [];

			foreach ($inputClient->contacts as $contact)
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
				$contacts[] = $record;
				$contactIds[] = $record->public_id;	

				if ($contact->send_invoice) 
				{
					$sendInvoiceIds[] = $record->id;
				}
			}
			
			foreach ($client->contacts as $contact)
			{
				if (!in_array($contact->public_id, $contactIds))
				{	
					$contact->forceDelete();
				}
			}

			if ($publicId) {
				$invoice = Invoice::scope($publicId)->firstOrFail();
				$invoice->invoice_items()->forceDelete();
			} else {				
				$invoice = Invoice::createNew();			
			}			
			
			$invoice->client_id = $client->id;
			$invoice->discount = $input->discount;
			$invoice->invoice_number = trim($input->invoice_number);
			$invoice->invoice_date = Utils::toSqlDate($input->invoice_date);
			$invoice->due_date = Utils::toSqlDate($input->due_date);					

			$invoice->is_recurring = $input->is_recurring;
			$invoice->frequency_id = $input->frequency_id ? $input->frequency_id : 0;
			$invoice->start_date = Utils::toSqlDate($input->start_date);
			$invoice->end_date = Utils::toSqlDate($input->end_date);
			$invoice->terms = $input->terms;
			$invoice->po_number = $input->po_number;
			

			$client->invoices()->save($invoice);
			
			$items = $input->invoice_items;
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
				
				$client->balance = $invoice->client->balance + $invoice->amount;
				$client->save();
			}

			$invoice->amount = $total;
			$invoice->save();

			foreach ($contacts as $contact)
			{
				$invitation = Invitation::scope()->whereContactId($contact->id)->whereInvoiceId($invoice->id)->first();
				
				if (in_array($contact->id, $sendInvoiceIds) && !$invitation) 
				{	
					$invitation = Invitation::createNew();
					$invitation->invoice_id = $invoice->id;
					$invitation->contact_id = $contact->id;
					$invitation->invitation_key = str_random(20);				
					$invitation->save();
				}				
				else if (!in_array($contact->id, $sendInvoiceIds) && $invitation)
				{
					$invitation->forceDelete();
				}
			}						

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

			$message = $clientPublicId == "-1" ? ' and created client' : '';
			if ($action == 'clone')
			{
				return InvoiceController::cloneInvoice($publicId);
			}
			else if ($action == 'email') 
			{							
				$this->mailer->sendInvoice($invoice);				
				
				Session::flash('message', 'Successfully emailed invoice'.$message);
			} else {				
				Session::flash('message', 'Successfully saved invoice'.$message);
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
			if ($action == 'delete') {
				$invoice->is_deleted = true;
				$invoice->save();
			} 
			$invoice->delete();
		}

		$message = Utils::pluralize('Successfully '.$action.'d ? invoice', count($invoices));
		Session::flash('message', $message);

		return Redirect::to('invoices');
	}

	public static function cloneInvoice($publicId)
	{
		$invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();		

		$clone = Invoice::createNew();		
		foreach (['client_id', 'discount', 'invoice_date', 'due_date', 'is_recurring', 'frequency_id', 'start_date', 'end_date', 'notes'] as $field) 
		{
			$clone->$field = $invoice->$field;	
		}

		if (!$clone->is_recurring)
		{
			$clone->invoice_number = Auth::user()->account->getNextInvoiceNumber();
		}

		$clone->save();

		foreach ($invoice->invoice_items as $item)
		{
			$cloneItem = InvoiceItem::createNew();
			
			foreach (['product_id', 'product_key', 'notes', 'cost', 'qty'] as $field) 
			{
				$cloneItem->$field = $item->$field;
			}

			$clone->invoice_items()->save($cloneItem);			
		}		

		Session::flash('message', 'Successfully cloned invoice');
		return Redirect::to('invoices/' . $clone->public_id);
	}
}
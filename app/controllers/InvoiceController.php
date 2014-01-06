<?php

use ninja\mailers\ContactMailer as Mailer;
use ninja\repositories\InvoiceRepository;
use ninja\repositories\ClientRepository;
use ninja\repositories\TaxRateRepository;

class InvoiceController extends \BaseController {

	protected $mailer;
	protected $invoiceRepo;
	protected $clientRepo;
	protected $taxRateRepo;

	public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, TaxRateRepository $taxRateRepo)
	{
		parent::__construct();

		$this->mailer = $mailer;
		$this->invoiceRepo = $invoiceRepo;
		$this->clientRepo = $clientRepo;
		$this->taxRateRepo = $taxRateRepo;
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
    	$query = $this->invoiceRepo->getInvoices(Auth::user()->account_id, $clientPublicId, Input::get('sSearch'));
    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	$table->addColumn('invoice_number', function($model) { return link_to('invoices/' . $model->public_id . '/edit', $model->invoice_number); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
    	}
    	
    	return $table->addColumn('invoice_date', function($model) { return Utils::fromSqlDate($model->invoice_date); })    	    
    		->addColumn('total', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
    		->addColumn('balance', function($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
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
    	$query = $this->invoiceRepo->getRecurringInvoices(Auth::user()->account_id, $clientPublicId, Input::get('sSearch'));
    	$table = Datatable::query($query);			

    	if (!$clientPublicId) {
    		$table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
    	}
    	
    	$table->addColumn('frequency', function($model) { return link_to('invoices/' . $model->public_id, $model->frequency); });

    	if (!$clientPublicId) {
    		$table->addColumn('client', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
    	}
    	
    	return $table->addColumn('start_date', function($model) { return Utils::fromSqlDate($model->start_date); })
    	    ->addColumn('end_date', function($model) { return Utils::fromSqlDate($model->end_date); })    	    
    	    ->addColumn('total', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
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
		$invitation = Invitation::with('user', 'invoice.invoice_items', 'invoice.account.country', 'invoice.client.contacts', 'invoice.client.country')
			->where('invitation_key', '=', $invitationKey)->firstOrFail();

		$invoice = $invitation->invoice;
		
		if (!$invoice || $invoice->is_deleted) 
		{
			return View::make('invoices.deleted');
		}

		$client = $invoice->client;
		
		if (!$client || $client->is_deleted) 
		{
			return View::make('invoices.deleted');
		}

		Activity::viewInvoice($invitation);	

		$client->account->loadLocalizationSettings();		

		$data = array(
			'invoice' => $invoice->hidePrivateFields(),
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
				Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>');
			}

			$payment = Payment::createNew();
			$payment->invitation_id = $invitation->id;
			$payment->invoice_id = $invoice->id;
			$payment->amount = $invoice->amount;			
			$payment->client_id = $invoice->client_id;
			$payment->contact_id = $invitation->contact_id;
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
	    		return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>');
	    	}
	    } 
	    catch (\Exception $e) 
	    {
	    	return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>'.$e);
		}
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

				if ($payment->amount >= $invoice->amount) 
				{
					$invoice->invoice_status_id = INVOICE_STATUS_PAID;
				} 
				else 
				{
					$invoice->invoice_status_id = INVOICE_STATUS_PARTIAL;
				}
				$invoice->save();
				
				Session::flash('message', 'Successfully applied payment');	
				return Redirect::to('view/' . $payment->invitation->invitation_key);				
			}
			else
			{
				return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>'.$response->getMessage());				
			}
	    } 
	    catch (\Exception $e) 
	    {
			return Utils::fatalError('Sorry, there was an error processing your payment. Please try again later.<p>'.$e);
		}
	}


	public function edit($publicId)
	{
		$invoice = Invoice::scope($publicId)->with('account.country', 'client.contacts', 'client.country', 'invoice_items')->firstOrFail();
		Utils::trackViewed($invoice->invoice_number . ' - ' . $invoice->client->getDisplayName(), ENTITY_INVOICE);
			
    	$contactIds = DB::table('invitations')
    				->join('contacts', 'contacts.id', '=','invitations.contact_id')
    				->where('invitations.invoice_id', '=', $invoice->id)
					->where('invitations.account_id', '=', Auth::user()->account_id)
    				->where('invitations.deleted_at', '=', null)
    				->select('contacts.public_id')->lists('public_id');
    	
		$data = array(
				'account' => $invoice->account,
				'invoice' => $invoice, 
				'data' => false,
				'method' => 'PUT', 
				'invitationContactIds' => $contactIds,
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
				'data' => Input::old('data'), 
				'invoiceNumber' => $invoiceNumber,
				'method' => 'POST', 
				'url' => 'invoices', 
				'title' => '- New Invoice',
				'client' => $client);
		$data = array_merge($data, InvoiceController::getViewModel());				
		return View::make('invoices.edit', $data);
	}

	public static function getViewModel()
	{
		return [
			'account' => Auth::user()->account,
			'products' => Product::scope()->get(array('product_key','notes','cost','qty')),
			'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
			'taxRates' => TaxRate::scope()->orderBy('name')->get(),
			'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'sizes' => Size::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'industries' => Industry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),				
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
		$invoice = $input->invoice;

		if ($errors = $this->invoiceRepo->getErrors($invoice))
		{
			return Redirect::to('invoices/create')
				->withInput()->withErrors($errors);
		} 
		else 
		{			
			$this->taxRateRepo->save($input->tax_rates);
						
			$clientData = (array) $invoice->client;			
			$client = $this->clientRepo->save($invoice->client->public_id, $clientData);
						
			$invoiceData = (array) $invoice;
			$invoiceData['client_id'] = $client->id;
			$invoice = $this->invoiceRepo->save($publicId, $invoiceData);

			$account = Auth::user()->account;
			if ($account->invoice_taxes != $input->invoice_taxes || $account->invoice_item_taxes != $input->invoice_item_taxes)
			{
				$account->invoice_taxes = $input->invoice_taxes;
				$account->invoice_item_taxes = $input->invoice_item_taxes;
				$account->save();
			}

			$client->load('contacts');
			$sendInvoiceIds = [];

			foreach ($client->contacts as $contact)
			{
				if ($contact->send_invoice || count($client->contacts) == 1)
				{	
					$sendInvoiceIds[] = $contact->id;
				}
			}
			
			foreach ($client->contacts as $contact)
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
					$invitation->delete();
				}
			}						

			$message = '';
			if ($input->invoice->client->public_id == '-1')
			{
				$message = ' and created client';
				$url = URL::to('clients/' . $client->public_id);

				Utils::trackViewed($client->getDisplayName(), ENTITY_CLIENT, $url);
			}
			
			if ($action == 'clone')
			{
				return InvoiceController::cloneInvoice($publicId);
			}
			else if ($action == 'email') 
			{							
				$this->mailer->sendInvoice($invoice);								
				Session::flash('message', 'Successfully emailed invoice'.$message);
			} 
			else 
			{				
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

		foreach ($invoices as $invoice) 
		{
			if ($action == 'delete') 
			{
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
		foreach (['client_id', 'discount', 'invoice_date', 'due_date', 'is_recurring', 'frequency_id', 'start_date', 'end_date', 'terms', 'currency_id'] as $field) 
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
			
			foreach (['product_id', 'product_key', 'notes', 'cost', 'qty', 'tax_name', 'tax_rate'] as $field) 
			{
				$cloneItem->$field = $item->$field;
			}

			$clone->invoice_items()->save($cloneItem);			
		}		

		Session::flash('message', 'Successfully cloned invoice');
		return Redirect::to('invoices/' . $clone->public_id);
	}
}
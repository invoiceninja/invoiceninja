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
			'columns'=>Utils::trans(['checkbox', 'invoice_number', 'client', 'invoice_date', 'invoice_total', 'balance_due', 'due_date', 'status', 'action'])
		];

		if (Invoice::scope()->where('is_recurring', '=', true)->count() > 0)
		{
			$data['secEntityType'] = ENTITY_RECURRING_INVOICE;
			$data['secColumns'] = Utils::trans(['checkbox', 'frequency', 'client', 'start_date', 'end_date', 'invoice_total', 'action']);
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
    		$table->addColumn('client_name', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
    	}
    	
    	return $table->addColumn('invoice_date', function($model) { return Utils::fromSqlDate($model->invoice_date); })    	    
    		->addColumn('amount', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
    		->addColumn('balance', function($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
    	    ->addColumn('due_date', function($model) { return Utils::fromSqlDate($model->due_date); })
    	    ->addColumn('invoice_status_name', function($model) { return $model->invoice_status_name; })
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    							'.trans('texts.select').' <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/edit') . '">'.trans('texts.edit_invoice').'</a></li>
						    <li><a href="' . URL::to('payments/create/' . $model->client_public_id . '/' . $model->public_id ) . '">'.trans('texts.enter_payment').'</a></li>
						    <li class="divider"></li>
						    <li><a href="javascript:archiveEntity(' . $model->public_id . ')">'.trans('texts.archive_invoice').'</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">'.trans('texts.delete_invoice').'</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
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
    		$table->addColumn('client_name', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
    	}
    	
    	return $table->addColumn('start_date', function($model) { return Utils::fromSqlDate($model->start_date); })
    	    ->addColumn('end_date', function($model) { return Utils::fromSqlDate($model->end_date); })    	    
    	    ->addColumn('amount', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
    	    ->addColumn('dropdown', function($model) 
    	    { 
    	    	return '<div class="btn-group tr-action" style="visibility:hidden;">
  							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
    						'.trans('texts.select').' <span class="caret"></span>
  							</button>
  							<ul class="dropdown-menu" role="menu">
						    <li><a href="' . URL::to('invoices/'.$model->public_id.'/edit') . '">'.trans('texts.edit_invoice').'</a></li>
						    <li class="divider"></li>
						    <li><a href="javascript:archiveEntity(' . $model->public_id . ')">'.trans('texts.archive_invoice').'</a></li>
						    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">'.trans('texts.delete_invoice').'</a></li>						    
						  </ul>
						</div>';
    	    })    	       	    
    	    ->make();    	
    }


	public function view($invitationKey)
	{
		$invitation = Invitation::withTrashed()->with('user', 'invoice.invoice_items', 'invoice.account.country', 'invoice.client.contacts', 'invoice.client.country')
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

		if (!Auth::check() || Auth::user()->account_id != $invoice->account_id)
		{
			Activity::viewInvoice($invitation);	
			Event::fire('invoice.viewed', $invoice);
		}

		$client->account->loadLocalizationSettings();		

		$invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
		$invoice->due_date = Utils::fromSqlDate($invoice->due_date);
		$invoice->is_pro = $client->account->isPro();
		
		$data = array(
			'hideHeader' => true,
			'showBreadcrumbs' => false,
			'invoice' => $invoice->hidePrivateFields(),
			'invitation' => $invitation,
			'invoiceLabels' => $client->account->getInvoiceLabels(),
		);

		return View::make('invoices.view', $data);
	}

	public function edit($publicId)
	{
		$invoice = Invoice::scope($publicId)->withTrashed()->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')->firstOrFail();
		Utils::trackViewed($invoice->invoice_number . ' - ' . $invoice->client->getDisplayName(), ENTITY_INVOICE);
	
		$invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
		$invoice->due_date = Utils::fromSqlDate($invoice->due_date);
		$invoice->start_date = Utils::fromSqlDate($invoice->start_date);
		$invoice->end_date = Utils::fromSqlDate($invoice->end_date);
		$invoice->is_pro = Auth::user()->isPro();

  	$contactIds = DB::table('invitations')
			->join('contacts', 'contacts.id', '=','invitations.contact_id')
			->where('invitations.invoice_id', '=', $invoice->id)
			->where('invitations.account_id', '=', Auth::user()->account_id)
			->where('invitations.deleted_at', '=', null)
			->select('contacts.public_id')->lists('public_id');
	
		$data = array(
				'showBreadcrumbs' => false,
				'account' => $invoice->account,
				'invoice' => $invoice, 
				'data' => false,
				'method' => 'PUT', 
				'invitationContactIds' => $contactIds,
				'url' => 'invoices/' . $publicId, 
				'title' => '- ' . $invoice->invoice_number,
				'client' => $invoice->client);
		$data = array_merge($data, self::getViewModel());

		// Set the invitation link on the client's contacts
		$clients = $data['clients'];
		foreach ($clients as $client)
		{
			if ($client->id == $invoice->client->id)
			{
				foreach ($invoice->invitations as $invitation)
				{
					foreach ($client->contacts as $contact)
					{
						if ($invitation->contact_id == $contact->id)
						{
							$contact->invitation_link = $invitation->getLink();
						}
					}				
				}
				break;
			}
		}
	
		return View::make('invoices.edit', $data);
	}

	public function create($clientPublicId = 0)
	{	
		$client = null;
		$invoiceNumber = Auth::user()->account->getNextInvoiceNumber();
		$account = Account::with('country')->findOrFail(Auth::user()->account_id);

		if ($clientPublicId) 
		{
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
		$data = array_merge($data, self::getViewModel());				

		return View::make('invoices.edit', $data);
	}

	private static function getViewModel()
	{
		return [
			'account' => Auth::user()->account,
			'products' => Product::scope()->orderBy('id')->get(array('product_key','notes','cost','qty')),
			'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
			'taxRates' => TaxRate::scope()->orderBy('name')->get(),
			'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'sizes' => Size::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'industries' => Industry::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),				
			'invoiceDesigns' => InvoiceDesign::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'invoiceLabels' => Auth::user()->account->getInvoiceLabels(),
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
			Session::flash('error', trans('texts.invoice_error'));

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
			if ($account->invoice_taxes != $input->invoice_taxes 
						|| $account->invoice_item_taxes != $input->invoice_item_taxes
						|| $account->invoice_design_id != $input->invoice->invoice_design_id)
			{
				$account->invoice_taxes = $input->invoice_taxes;
				$account->invoice_item_taxes = $input->invoice_item_taxes;
				$account->invoice_design_id = $input->invoice->invoice_design_id;
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
					$invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
					$invitation->save();
				}				
				else if (!in_array($contact->id, $sendInvoiceIds) && $invitation)
				{
					$invitation->delete();
				}
			}						

			$message = trans($publicId ? 'texts.updated_invoice' : 'texts.created_invoice');
			if ($input->invoice->client->public_id == '-1')
			{
				$message = $message . ' ' . trans('texts.and_created_client');

				$url = URL::to('clients/' . $client->public_id);
				Utils::trackViewed($client->getDisplayName(), ENTITY_CLIENT, $url);
			}
			
			if ($action == 'clone')
			{
				return InvoiceController::cloneInvoice($publicId);
			}
			else if ($action == 'email') 
			{	
				if (Auth::user()->confirmed)
				{
					$message = trans('texts.emailed_invoice');
					$this->mailer->sendInvoice($invoice);
					Session::flash('message', $message);
				}
				else
				{
					$errorMessage = trans(Auth::user()->registered ? 'texts.confirmation_required' : 'texts.registration_required');
					Session::flash('error', $errorMessage);
					Session::flash('message', $message);					
				}
			} 
			else 
			{				
				Session::flash('message', $message);
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
		Session::reflash();
		
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
		$count = $this->invoiceRepo->bulk($ids, $action);

 		if ($count > 0)		
 		{
			$message = Utils::pluralize('Successfully '.$action.'d ? invoice', $count);
			Session::flash('message', $message);
		}

		return Redirect::to('invoices');
	}

	public static function cloneInvoice($publicId)
	{
		$invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();		

		$clone = Invoice::createNew();		
		$clone->balance = $invoice->amount;
		foreach (['client_id', 'discount', 'invoice_date', 'due_date', 'is_recurring', 'frequency_id', 'start_date', 'end_date', 'terms', 'public_notes', 'invoice_design_id', 'tax_name', 'tax_rate', 'amount'] as $field) 
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

		Session::flash('message', trans('texts.cloned_invoice'));
		return Redirect::to('invoices/' . $clone->public_id);
	}
}
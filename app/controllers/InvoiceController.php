<?php

class InvoiceController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//$invoices = Invoice::with('client')->orderBy('created_at', 'DESC')->get();
		return View::make('invoices.index');
	}

	public function getDatatable()
    {
        return Datatable::collection(Invoice::with('client','invoice_items')->where('account_id','=',Auth::user()->account_id)->get())
    	    ->addColumn('number', function($model)
    	    	{
    	    		return link_to('invoices/' . $model->id . '/edit', $model->invoice_number);
    	    	})
    	    ->addColumn('client', function($model)
    	    	{
    	    		return link_to('clients/' . $model->client->id, $model->client->name);
    	    	})
    	    ->addColumn('amount', function($model)
    	    	{
    	    		return '$' . money_format('%i', $model->getTotal());
    	    	})
    	    ->addColumn('date', function($model)
    	    	{
    	    		return $model->created_at->format('m/d/y h:i a');
    	    	})
    	    ->orderColumns('number')
    	    ->make();
    }


	public function view($invoiceKey)
	{
		$invoice = Invoice::with('invoice_items', 'client.account.account_gateways')->where('invoice_key', '=', $invoiceKey)->firstOrFail();				
		$contact = null;

		Activity::viewInvoice($invoice, $contact);

		return View::make('invoices.view')->with('invoice', $invoice);	
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
			    'amount' => $invoice->getTotal(),
			    'card' => $card,
			    'currency' => 'USD',
			    'returnUrl' => URL::to('complete'),
			    'cancelUrl' => URL::to('/'),
		];
	}

	public function show_payment($invoiceKey)
	{
		$invoice = Invoice::with('invoice_items', 'client.account.account_gateways.gateway')->where('invoice_key', '=', $invoiceKey)->firstOrFail();
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

			$payment = new Payment;
			$payment->invoice_id = $invoice->id;
			$payment->account_id = $invoice->account_id;
			$payment->contact_id = 0; // TODO_FIX
			$payment->transaction_reference = $ref;
			$payment->save();

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

		$payment = Payment::with('invoice.invoice_items')->where('transaction_reference','=',$token)->firstOrFail();
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
				$payment->amount = $payment->invoice->getTotal();
				$payment->save();
				
				Session::flash('message', 'Successfully applied payment');	
				return Redirect::to('view/' . $payment->invoice->invoice_key);				
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

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create($clientId = 0)
	{		
		$client = null;
		if ($clientId) {
			$client = Client::find($clientId);
		}

		$data = array(
				'invoice' => null, 
				'method' => 'POST', 
				'url' => 'invoices', 
				'title' => 'New',
				'client' => $client,
				'items' => json_decode(Input::old('items')),
				'account' => Auth::user()->account,
				'products' => Product::getProducts()->get(),
				'clients' => Client::where('account_id','=',Auth::user()->account_id)->get());
		return View::make('invoices.edit', $data);
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

	private function save($id = null)
	{	
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

			$clientId = Input::get('client');

			if ($clientId == "-1") 
			{
				$client = new Client;
				$client->name = Input::get('client_name');
				$client->account_id = Auth::user()->account_id;
				$client->save();				
				$clientId = $client->id;	

				$contact = new Contact;
				$contact->email = Input::get('client_email');
				$client->contacts()->save($contact);
			}
			else
			{
				$client = Client::with('contacts')->find($clientId);
				$contact = $client->contacts[0];
			}

			if ($id) {
				$invoice = Invoice::find($id);
				$invoice->invoice_items()->forceDelete();
			} else {
				$invoice = new Invoice;
				$invoice->invoice_key = str_random(20);
				$invoice->account_id = Auth::user()->account_id;
			}

			$date = DateTime::createFromFormat('m/d/Y', Input::get('invoice_date'));
		
			$invoice->client_id = $clientId;
			$invoice->invoice_number = Input::get('invoice_number');
			$invoice->discount = 0;
			$invoice->invoice_date = $date->format('Y-m-d');
			$invoice->save();

			$items = json_decode(Input::get('items'));
			foreach ($items as $item) {

				if (!isset($item->cost))
				{
					$item->cost = 0;
				}
				if (!isset($item->qty))
				{
					$item->qty = 0;
				}				

				$product = Product::findProduct($item->product_key);

				if (!$product)
				{
					$product = new Product;
					$product->account_id = Auth::user()->account_id;
					$product->product_key = $item->product_key;
				}

				$product->notes = $item->notes;
				$product->cost = $item->cost;
				$product->qty = $item->qty;
				$product->save();

				$invoiceItem = new InvoiceItem;
				$invoiceItem->product_id = $product->id;
				$invoiceItem->product_key = $item->product_key;
				$invoiceItem->notes = $item->notes;
				$invoiceItem->cost = $item->cost;
				$invoiceItem->qty = $item->qty;

				$invoice->invoice_items()->save($invoiceItem);
			}

			if (Input::get('send_email_checkBox')) 
			{
				$data = array('link' => URL::to('view') . '/' . $invoice->invoice_key);
				Mail::send(array('html'=>'emails.invoice_html','text'=>'emails.invoice_text'), $data, function($message) use ($contact)
				{
				    $message->from('hillelcoren@gmail.com', 'Hillel Coren');
				    $message->to($contact->email);
				});

				Activity::emailInvoice($invoice, $contact);

				Session::flash('message', 'Successfully emailed invoice');
			} else {				
				Session::flash('message', 'Successfully saved invoice');
			}

			return Redirect::to('invoices/' . $invoice->id . '/edit');
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$invoice = Invoice::find($id);
		return View::make('invoices.show')->with('invoice', $invoice);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$invoice = Invoice::with('client', 'invoice_items')->find($id);

		$data = array(
				'invoice' => $invoice, 
				'method' => 'PUT', 
				'url' => 'invoices/' . $id, 
				'title' => 'Edit',
				'account' => Auth::user()->account,
				'products' => Product::getProducts()->get(),
				'client' => $invoice->client,
				'clients' => Client::where('account_id','=',Auth::user()->account_id)->get());
		return View::make('invoices.edit', $data);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		return InvoiceController::save($id);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$invoice = Invoice::find($id);
		$invoice->delete();

		// redirect
		Session::flash('message', 'Successfully deleted the invoice');
		return Redirect::to('invoices');
	}
}
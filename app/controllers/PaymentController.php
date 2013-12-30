<?php

class PaymentController extends \BaseController 
{
	public function index()
	{
        return View::make('list', array(
            'entityType'=>ENTITY_PAYMENT, 
            'title' => '- Payments',
            'columns'=>['checkbox', 'Transaction Reference', 'Client', 'Invoice', 'Payment Amount', 'Payment Date', 'Action']
        ));
	}

	public function getDatatable($clientPublicId = null)
    {
        $query = DB::table('payments')
                    ->join('clients', 'clients.id', '=','payments.client_id')
                    ->leftJoin('invoices', 'invoices.id', '=','payments.invoice_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('payments.account_id', '=', Auth::user()->account_id)
                    ->where('payments.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)   
                    ->select('payments.public_id', 'payments.transaction_reference', 'clients.name as client_name', 'clients.public_id as client_public_id', 'payments.amount', 'payments.payment_date', 'invoices.public_id as invoice_public_id', 'invoices.invoice_number', 'payments.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email');        

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        $filter = Input::get('sSearch');
        if ($filter)
        {
            $query->where(function($query) use ($filter)
            {
                $query->where('clients.name', 'like', '%'.$filter.'%');
            });
        }

        $table = Datatable::query($query);        

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
        }

        $table->addColumn('transaction_reference', function($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; });

        if (!$clientPublicId) {
            $table->addColumn('client_name', function($model) { return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model)); });
        }

        return $table->addColumn('invoice_number', function($model) { return $model->invoice_public_id ? link_to('invoices/' . $model->invoice_public_id . '/edit', $model->invoice_number) : ''; })
            ->addColumn('amount', function($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
    	    ->addColumn('payment_date', function($model) { return Utils::dateToString($model->payment_date); })
            ->addColumn('dropdown', function($model) 
            { 
                return '<div class="btn-group tr-action" style="visibility:hidden;">
                            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                                Select <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu">
                            <li><a href="' . URL::to('payments/'.$model->public_id.'/edit') . '">Edit Payment</a></li>
                            <li class="divider"></li>
                            <li><a href="javascript:archiveEntity(' . $model->public_id. ')">Archive Payment</a></li>
                            <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Payment</a></li>                          
                          </ul>
                        </div>';
            })         
    	    ->orderColumns('transaction_reference', 'client_name', 'invoice_number', 'amount', 'payment_date')
    	    ->make();
    }


    public function create($clientPublicId = 0)
    {       
        $data = array(
            'clientPublicId' => $clientPublicId,
            'invoice' => null,
            'invoices' => Invoice::scope()->with('client')->orderBy('invoice_number')->get(),
            'payment' => null, 
            'method' => 'POST', 
            'url' => 'payments', 
            'title' => '- New Payment',
            'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get());

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();        
        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->with('client')->orderBy('invoice_number')->get(array('public_id','invoice_number')),
            'payment' => $payment, 
            'method' => 'PUT', 
            'url' => 'payments/' . $publicId, 
            'title' => '- Edit Payment',
            'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get());
        return View::make('payments.edit', $data);
    }

    public function store()
    {
        return $this->save();
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private function save($publicId = null)
    {
        $rules = array(
            'client' => 'required',
            'amount' => 'required'
        );
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            $url = $publicId ? 'payments/' . $publicId . '/edit' : 'payments/create';
            return Redirect::to($url)
                ->withErrors($validator)
                ->withInput();
        } else {            
            if ($publicId) {
                $payment = Payment::scope($publicId)->firstOrFail();
            } else {
                $payment = Payment::createNew();
            }

            $invoiceId = Input::get('invoice') && Input::get('invoice') != "-1" ? Input::get('invoice') : null;

            $payment->client_id = Input::get('client');
            $payment->invoice_id = $invoiceId;
            $payment->currency_id = Input::get('currency_id') ? Input::get('currency_id') : null;
            $payment->payment_date = Utils::toSqlDate(Input::get('payment_date'));
            $payment->amount = floatval(Input::get('amount'));
            $payment->save();

            $message = $publicId ? 'Successfully updated payment' : 'Successfully created payment';
            Session::flash('message', $message);
            return Redirect::to('clients/' . $payment->client_id);
        }
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');
        $payments = Payment::scope($ids)->get();

        foreach ($payments as $payment) {            
            if ($action == 'delete') {
                $payment->is_deleted = true;
                $payment->save();
            } 
            $payment->delete();
        }

        $message = Utils::pluralize('Successfully '.$action.'d ? payment', count($payments));
        Session::flash('message', $message);

        return Redirect::to('payments');
    }

}
<?php

class PaymentController extends \BaseController 
{
	public function index()
	{
        return View::make('list', array(
            'entityType'=>ENTITY_PAYMENT, 
            'title' => '- Payments',
            'columns'=>['checkbox', 'Transaction Reference', 'Client', 'Invoice', 'Amount', 'Payment Date', 'Action']
        ));
	}

	public function getDatatable($clientPublicId = null)
    {
        $collection = Payment::scope()->with('invoice', 'client');

        if ($clientPublicId) {
            $clientId = Client::getPrivateId($clientPublicId);
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
        }

        $table->addColumn('transaction_reference', function($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; });

        if (!$clientPublicId) {
            $table->addColumn('client', function($model) { return link_to('clients/' . $model->client->public_id, $model->client->name); });
        }

        return $table->addColumn('invoice_number', function($model) { return $model->invoice ? link_to('invoices/' . $model->invoice->public_id . '/edit', $model->invoice->invoice_number) : ''; })
            ->addColumn('amount', function($model) { return '$' . $model->amount; })
    	    ->addColumn('date', function($model) { return timestampToDateTimeString($model->created_at); })
            ->addColumn('dropdown', function($model) 
            { 
                return '<div class="btn-group tr-action" style="display:none">
                            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                                Select <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu">
                            <li><a href="' . URL::to('payments/'.$model->public_id.'/edit') . '">Edit Payment</a></li>
                            <li class="divider"></li>
                            <li><a href="' . URL::to('payments/'.$model->public_id.'/archive') . '">Archive Payment</a></li>
                            <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Payment</a></li>                          
                          </ul>
                        </div>';
            })         
    	    ->orderColumns('client')
    	    ->make();
    }


    public function create()
    {       
        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::with('client')->scope()->orderBy('invoice_number')->get(),
            'payment' => null, 
            'method' => 'POST', 
            'url' => 'payments', 
            'title' => '- New Payment',
            'clients' => Client::scope()->orderBy('name')->get());

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();        
        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->orderBy('invoice_number')->get(array('public_id','invoice_number')),
            'payment' => $payment, 
            'method' => 'PUT', 
            'url' => 'payments/' . $publicId, 
            'title' => '- Edit Payment',
            'clients' => Client::scope()->orderBy('name')->get());
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
            $payment->payment_date = toSqlDate(Input::get('payment_date'));
            $payment->amount = Input::get('amount');
            $payment->save();

            $message = $publicId ? 'Successfully updated payment' : 'Successfully created payment';
            Session::flash('message', $message);
            return Redirect::to('clients/' . $payment->client_id);
        }
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('ids');
        $payments = Payment::scope($ids)->get();

        foreach ($payments as $payment) {
            if ($action == 'archive') {
                $payment->delete();
            } else if ($action == 'delete') {
                $payment->forceDelete();
            } 
        }

        $message = pluralize('Successfully '.$action.'d ? payment', count($ids));
        Session::flash('message', $message);

        return Redirect::to('payments');
    }

}
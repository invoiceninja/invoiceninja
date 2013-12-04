<?php

class PaymentController extends \BaseController 
{
	public function index()
	{
        return View::make('list', array(
            'entityType'=>ENTITY_PAYMENT, 
            'title' => '- Payments',
            'columns'=>['checkbox', 'Transaction Reference', 'Client', 'Amount', 'Payment Date']
        ));
	}

	public function getDatatable($clientPublicId = null)
    {
        $collection = Payment::scope()->with('invoice.client');

        if ($clientPublicId) {
            $clientId = Client::getPrivateId($clientPublicId);
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
        }

        $table->addColumn('transaction_reference', function($model) { return $model->transaction_reference; });

        if (!$clientPublicId) {
            $table->addColumn('client', function($model) { return link_to('clients/' . $model->client->public_id, $model->client->name); });
        }

        return $table->addColumn('amount', function($model) { return '$' . $model->amount; })
    	    ->addColumn('date', function($model) { return timestampToDateTimeString($model->created_at); })
    	    ->orderColumns('client')
    	    ->make();
    }


    public function create()
    {       
        $data = array(
            'payment' => null, 
            'method' => 'POST', 
            'url' => 'payments', 
            'title' => '- New Payment');

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();        
        $data = array(
            'payment' => $payment, 
            'method' => 'PUT', 
            'url' => 'payments/' . $publicId, 
            'title' => '- Edit Payment');
        return View::make('payments.edit', $data);
    }


    public function archive($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();
        $payment->delete();

        Session::flash('message', 'Successfully archived payment');
        return Redirect::to('payments');     
    }

    public function delete($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();
        $payment->forceDelete();

        Session::flash('message', 'Successfully deleted payment');
        return Redirect::to('payments');     
    }
}
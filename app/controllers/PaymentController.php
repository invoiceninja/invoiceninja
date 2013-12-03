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

	public function getDatatable($clientId = null)
    {
        $collection = Payment::scope()->with('invoice.client');

        if ($clientId) {
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->id . '">'; });
        }

        $table->addColumn('transaction_reference', function($model) { return $model->transaction_reference; });

        if (!$clientId) {
            $table->addColumn('client', function($model) { return link_to('clients/' . $model->client->id, $model->client->name); });
        }

        return $table->addColumn('amount', function($model) { return '$' . $model->amount; })
    	    ->addColumn('date', function($model) { return timestampToDateTimeString($model->created_at); })
    	    ->orderColumns('client')
    	    ->make();
    }

    public function archive($id)
    {
        $payment = Payment::scope()->findOrFail($id);
        $payment->delete();

        Session::flash('message', 'Successfully archived payment');
        return Redirect::to('payments');     
    }

    public function delete($id)
    {
        $payment = Payment::scope()->findOrFail($id);
        $payment->forceDelete();

        Session::flash('message', 'Successfully deleted payment');
        return Redirect::to('payments');     
    }
}
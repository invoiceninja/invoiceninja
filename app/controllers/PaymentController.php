<?php

class PaymentController extends \BaseController 
{
	public function index()
	{
		return View::make('payments.index');
	}

	public function getDatatable($clientId = null)
    {
        $collection = Payment::scope()->with('invoice.client');

        if ($clientId) {
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientId) {
            $table->addColumn('client', function($model)
    	    	{
    	    		return link_to('clients/' . $model->invoice->client->id, $model->invoice->client->name);
    	    	});
        }

        return $table->addColumn('invoice', function($model)
    	    	{
                    return link_to('invoices/' . $model->invoice->id . '/edit', $model->invoice->number);
    	    	})
    	    ->addColumn('amount', function($model)
    	    	{
    	    		return '$' . $model->amount;
    	    	})
    	    ->addColumn('date', function($model)
    	    	{
    	    		return $model->created_at->format('m/d/y h:i a');
    	    	})
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
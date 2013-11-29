<?php

class PaymentController extends \BaseController 
{
	public function index()
	{
		return View::make('payments.index');
	}

	public function getDatatable($clientId = null)
    {
        $collection = Payment::with('invoice.client')->where('account_id', '=', Auth::user()->account_id);

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

}
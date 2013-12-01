<?php

class CreditController extends \BaseController {

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType'=>ENTITY_CREDIT, 
            'columns'=>['checkbox', 'Credit Number', 'Client', 'Amount', 'Credit Date']
        ));
    }

    public function getDatatable($clientId = null)
    {
        $collection = Credit::with('client')->where('account_id','=',Auth::user()->account_id);

        if ($clientId) {
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->id . '">'; });
        }
        
        $table->addColumn('credit_number', function($model) { return $model->credit_number; });

        if (!$clientId) {
            $table->addColumn('client', function($model) { return link_to('clients/' . $model->client->id, $model->client->name); });
        }
        
        return $table->addColumn('amount', function($model){ return '$' . money_format('%i', $model->amount); })
            ->addColumn('credit_date', function($model) { return (new Carbon($model->credit_date))->toFormattedDateString(); })
            ->orderColumns('number')
            ->make();       
    }

    public function archive($id)
    {
        $credit = Credit::find($id);
        $creidt->delete();

        Session::flash('message', 'Successfully archived credit ' . $credit->credit_number);
        return Redirect::to('credits');     
    }

    public function delete($id)
    {
        $credit = Credit::find($id);
        $credit->forceDelete();

        Session::flash('message', 'Successfully deleted credit ' . $credit->credit_number);
        return Redirect::to('credits');     
    }
}
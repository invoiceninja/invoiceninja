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
            'title' => '- Credits',
            'columns'=>['checkbox', 'Credit Number', 'Client', 'Amount', 'Credit Date']
        ));
    }

    public function getDatatable($clientPublicId = null)
    {
        $collection = Credit::scope()->with('client');

        if ($clientPublicId) {
            $clientId = Client::getPrivateId($clientPublicId);
            $collection->where('client_id','=',$clientId);
        }

        $table = Datatable::collection($collection->get());

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; });
        }
        
        $table->addColumn('credit_number', function($model) { return $model->credit_number; });

        if (!$clientPublicId) {
            $table->addColumn('client', function($model) { return link_to('clients/' . $model->client->public_id, $model->client->name); });
        }
        
        return $table->addColumn('amount', function($model){ return '$' . money_format('%i', $model->amount); })
            ->addColumn('credit_date', function($model) { return (new Carbon($model->credit_date))->toFormattedDateString(); })
            ->orderColumns('number')
            ->make();       
    }

    public function archive($publicId)
    {
        $credit = Credit::scope($publicId)->firstOrFail();
        $creidt->delete();

        Session::flash('message', 'Successfully archived credit ' . $credit->credit_number);
        return Redirect::to('credits');     
    }

    public function delete($publicId)
    {
        $credit = Credit::scope($publicId)->firstOrFail();
        $credit->forceDelete();

        Session::flash('message', 'Successfully deleted credit ' . $credit->credit_number);
        return Redirect::to('credits');     
    }
}
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
            'columns'=>['checkbox', 'Client', 'Amount', 'Credit Date', 'Action']
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
            $table->addColumn('checkbox', function($model) { return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '">'; })
                  ->addColumn('client', function($model) { return link_to('clients/' . $model->client->public_id, $model->client->name); });
        }
        
        return $table->addColumn('amount', function($model){ return '$' . money_format('%i', $model->amount); })
            ->addColumn('credit_date', function($model) { return (new Carbon($model->credit_date))->toFormattedDateString(); })
            ->addColumn('dropdown', function($model) 
            { 
                return '<div class="btn-group tr-action" style="display:none">
                            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                                Select <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu">
                            <li><a href="' . URL::to('credits/'.$model->public_id.'/edit') . '">Edit Credit</a></li>
                            <li class="divider"></li>
                            <li><a href="' . URL::to('credits/'.$model->public_id.'/archive') . '">Archive Credit</a></li>
                            <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Credit</a></li>                          
                          </ul>
                        </div>';
            })         
           ->orderColumns('number')
            ->make();       
    }


    public function create()
    {       
        $data = array(
            'client' => null,
            'credit' => null, 
            'method' => 'POST', 
            'url' => 'credits', 
            'title' => '- New Credit',
            'clients' => Client::scope()->orderBy('name')->get());

        return View::make('credits.edit', $data);
    }

    public function edit($publicId)
    {
        $credit = Credit::scope($publicId)->firstOrFail();
        $data = array(
            'client' => null,
            'credit' => $credit, 
            'method' => 'PUT', 
            'url' => 'credits/' . $publicId, 
            'title' => '- Edit Credit',
            'clients' => Client::scope()->orderBy('name')->get());
        return View::make('credit.edit', $data);
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
            $url = $publicId ? 'credits/' . $publicId . '/edit' : 'credits/create';
            return Redirect::to($url)
                ->withErrors($validator)
                ->withInput();
        } else {            
            if ($publicId) {
                $credit = Credit::scope($publicId)->firstOrFail();
            } else {
                $credit = Credit::createNew();
            }

            $credit->client_id = Input::get('client');
            $credit->credit_date = toSqlDate(Input::get('credit_date'));
            $credit->amount = Input::get('amount');
            $credit->save();

            $message = $publicId ? 'Successfully updated credit' : 'Successfully created credit';
            Session::flash('message', $message);
            return Redirect::to('clients/' . $credit->client_id);
        }
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('ids');
        $credits = Credit::scope($ids)->get();

        foreach ($credits as $credit) {
            if ($action == 'archive') {
                $credit->delete();
            } else if ($action == 'delete') {
                $credit->forceDelete();
            } 
        }

        $message = pluralize('Successfully '.$action.'d ? credit', count($ids));
        Session::flash('message', $message);

        return Redirect::to('credits');
    }
}
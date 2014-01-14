<?php

use ninja\repositories\PaymentRepository;

class PaymentController extends \BaseController 
{
    protected $creditRepo;

    public function __construct(PaymentRepository $paymentRepo)
    {
        parent::__construct();

        $this->paymentRepo = $paymentRepo;
    }   

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
        $payments = $this->paymentRepo->find($clientPublicId, Input::get('sSearch'));
        $table = Datatable::query($payments);        

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
                            <li><a href="javascript:archiveEntity(' . $model->public_id. ')">Archive Payment</a></li>
                            <li><a href="javascript:deleteEntity(' . $model->public_id. ')">Delete Payment</a></li>                          
                          </ul>
                        </div>';
            })         
    	    ->make();
    }


    public function create($clientPublicId = 0, $invoicePublicId = 0)
    {       
        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : $clientPublicId,
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : $invoicePublicId,
            'invoice' => null,
            'invoices' => Invoice::scope()->with('client', 'invoice_status')->where('balance','>',0)->orderBy('invoice_number')->get(),
            'payment' => null, 
            'method' => 'POST', 
            'url' => "payments", 
            'title' => '- New Payment',
            'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'paymentTypes' => PaymentType::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get());

        return View::make('payments.edit', $data);
    }

    public function edit($publicId)
    {
        $payment = Payment::scope($publicId)->firstOrFail();        
        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->with('client', 'invoice_status')->orderBy('invoice_number')->get(array('public_id','invoice_number')),
            'payment' => $payment, 
            'method' => 'PUT', 
            'url' => 'payments/' . $publicId, 
            'title' => '- Edit Payment',
            'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
            'paymentTypes' => PaymentType::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
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
            'invoice' => 'required',  
            'amount' => 'required|positive'
        );
        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) 
        {
            $url = $publicId ? 'payments/' . $publicId . '/edit' : 'payments/create';
            return Redirect::to($url)
                ->withErrors($validator)
                ->withInput();
        } 
        else 
        {            
            $this->paymentRepo->save($publicId, Input::all());

            $message = $publicId ? 'Successfully updated payment' : 'Successfully created payment';
            Session::flash('message', $message);
            return Redirect::to('clients/' . Input::get('client'));
        }
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');
        $count = $this->paymentRepo->bulk($ids, $action);

        if ($count > 0)
        {
            $message = Utils::pluralize('Successfully '.$action.'d ? payment', $count);
            Session::flash('message', $message);
        }
        
        return Redirect::to('payments');
    }

}
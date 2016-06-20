<?php namespace App\Http\Controllers;

use Input;
use Session;
use Utils;
use View;
use Omnipay;
use Cache;
use App\Models\Invoice;
use App\Models\Client;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Services\PaymentService;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;

class PaymentController extends BaseController
{
    protected $entityType = ENTITY_PAYMENT;

    public function __construct(PaymentRepository $paymentRepo, ContactMailer $contactMailer, PaymentService $paymentService)
    {
        $this->paymentRepo = $paymentRepo;
        $this->contactMailer = $contactMailer;
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'sortCol' => '6',
            'columns' => Utils::trans([
              'checkbox',
              'invoice',
              'client',
              'transaction_reference',
              'method',
              'source',
              'payment_amount',
              'payment_date',
              'status',
              ''
            ]),
        ));
    }

    public function getDatatable($clientPublicId = null)
    {
        return $this->paymentService->getDatatable($clientPublicId, Input::get('sSearch'));
    }

    public function create(PaymentRequest $request)
    {
        $invoices = Invoice::scope()
                    ->viewable()
                    ->invoiceType(INVOICE_TYPE_STANDARD)
                    ->where('is_recurring', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->with('client', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : ($request->client_id ?: 0),
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : ($request->invoice_id ?: 0),
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => "payments",
            'title' => trans('texts.new_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'paymentTypeId' => Input::get('paymentTypeId'),
            'clients' => Client::scope()->viewable()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    public function edit(PaymentRequest $request)
    {
        $payment = $request->entity();

        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = array(
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()->invoiceType(INVOICE_TYPE_STANDARD)->where('is_recurring', '=', false)
                            ->with('client', 'invoice_status')->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$payment->public_id,
            'title' => trans('texts.edit_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('payments.edit', $data);
    }

    public function store(CreatePaymentRequest $request)
    {
        $input = $request->input();

        $input['invoice_id'] = Invoice::getPrivateId($input['invoice']);
        $input['client_id'] = Client::getPrivateId($input['client']);
        $payment = $this->paymentRepo->save($input);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans('texts.created_payment_emailed_client'));
        } else {
            Session::flash('message', trans('texts.created_payment'));
        }

        return redirect()->to($payment->client->getRoute());
    }

    public function update(UpdatePaymentRequest $request)
    {
        $payment = $this->paymentRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_payment'));

        return redirect()->to($payment->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $amount = Input::get('amount');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->paymentService->bulk($ids, $action, array('amount'=>$amount));

        if ($count > 0) {
            $message = Utils::pluralize($action=='refund'?'refunded_payment':$action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return redirect()->to('payments');
    }

}

<?php namespace App\Http\Controllers;

use Input;
use Session;
use Utils;
use View;
use Cache;
use App\Models\Invoice;
use App\Models\Client;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Services\PaymentService;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Ninja\Datatables\PaymentDatatable;

class PaymentController extends BaseController
{
    /**
     * @var string
     */
    protected $entityType = ENTITY_PAYMENT;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepo;

    /**
     * @var ContactMailer
     */
    protected $contactMailer;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * PaymentController constructor.
     *
     * @param PaymentRepository $paymentRepo
     * @param ContactMailer $contactMailer
     * @param PaymentService $paymentService
     */
    public function __construct(
        PaymentRepository $paymentRepo,
        ContactMailer $contactMailer,
        PaymentService $paymentService
    )
    {
        $this->paymentRepo = $paymentRepo;
        $this->contactMailer = $contactMailer;
        $this->paymentService = $paymentService;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PAYMENT,
            'datatable' => new PaymentDatatable(),
            'title' => trans('texts.payments'),
        ]);
    }

    /**
     * @param null $clientPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($clientPublicId = null)
    {
        return $this->paymentService->getDatatable($clientPublicId, Input::get('sSearch'));
    }

    /**
     * @param PaymentRequest $request
     * @return \Illuminate\Contracts\View\View
     */
    public function create(PaymentRequest $request)
    {
        $invoices = Invoice::scope()
                    ->invoices()
                    ->whereIsPublic(true)
                    ->where('invoices.balance', '>', 0)
                    ->with('client', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = [
            'clientPublicId' => Input::old('client') ? Input::old('client') : ($request->client_id ?: 0),
            'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : ($request->invoice_id ?: 0),
            'invoice' => null,
            'invoices' => $invoices,
            'payment' => null,
            'method' => 'POST',
            'url' => 'payments',
            'title' => trans('texts.new_payment'),
            'paymentTypeId' => Input::get('paymentTypeId'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), ];

        return View::make('payments.edit', $data);
    }

    /**
     * @param $publicId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($publicId)
    {
        Session::reflash();

        return redirect()->to("payments/{$publicId}/edit");
    }

    /**
     * @param PaymentRequest $request
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(PaymentRequest $request)
    {
        $payment = $request->entity();

        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $data = [
            'client' => null,
            'invoice' => null,
            'invoices' => Invoice::scope()
                            ->invoices()
                            ->whereIsPublic(true)
                            ->with('client', 'invoice_status')
                            ->orderBy('invoice_number')->get(),
            'payment' => $payment,
            'entity' => $payment,
            'method' => 'PUT',
            'url' => 'payments/'.$payment->public_id,
            'title' => trans('texts.edit_payment'),
            'paymentTypes' => Cache::get('paymentTypes'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
        ];

        return View::make('payments.edit', $data);
    }

    /**
     * @param CreatePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
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

        return redirect()->to($payment->client->getRoute() . '#payments');
    }

    /**
     * @param UpdatePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdatePaymentRequest $request)
    {
        $payment = $this->paymentRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_payment'));

        return redirect()->to($payment->getRoute());
    }

    /**
     * @return mixed
     */
    public function bulk()
    {
        $action = Input::get('action');
        $amount = Input::get('amount');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->paymentService->bulk($ids, $action, ['amount'=>$amount]);

        if ($count > 0) {
            $message = Utils::pluralize($action=='refund'?'refunded_payment':$action.'d_payment', $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk(ENTITY_PAYMENT, $action, $ids);
    }
}

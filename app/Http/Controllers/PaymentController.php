<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Credit;
use App\Models\Invoice;
use App\Ninja\Datatables\PaymentDatatable;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Repositories\PaymentRepository;
use App\Services\PaymentService;
use Auth;
use Cache;
use DropdownButton;
use Input;
use Session;
use Utils;
use View;

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
     * @param ContactMailer     $contactMailer
     * @param PaymentService    $paymentService
     */
    public function __construct(
        PaymentRepository $paymentRepo,
        ContactMailer $contactMailer,
        PaymentService $paymentService
    ) {
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
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($clientPublicId = null)
    {
        return $this->paymentService->getDatatable($clientPublicId, Input::get('sSearch'));
    }

    /**
     * @param PaymentRequest $request
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(PaymentRequest $request)
    {
        $invoices = Invoice::scope()
                    ->invoices()
                    ->where('invoices.balance', '!=', 0)
                    ->with('client', 'invoice_status')
                    ->orderBy('invoice_number')->get();

        $data = [
            'account' => Auth::user()->account,
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
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($publicId)
    {
        Session::reflash();

        return redirect()->to("payments/{$publicId}/edit");
    }

    /**
     * @param PaymentRequest $request
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(PaymentRequest $request)
    {
        $payment = $request->entity();
        $payment->payment_date = Utils::fromSqlDate($payment->payment_date);

        $actions = [];
        if ($payment->invoiceJsonBackup()) {
            $actions[] = ['url' => url("/invoices/invoice_history/{$payment->invoice->public_id}?payment_id={$payment->public_id}"), 'label' => trans('texts.view_invoice')];
        }

        $actions[] = ['url' => url("/invoices/{$payment->invoice->public_id}/edit"), 'label' => trans('texts.edit_invoice')];
        $actions[] = DropdownButton::DIVIDER;
        $actions[] = ['url' => 'javascript:submitAction("email")', 'label' => trans('texts.email_payment')];

        if ($payment->canBeRefunded()) {
            $actions[] = ['url' => "javascript:showRefundModal({$payment->public_id}, \"{$payment->getCompletedAmount()}\", \"{$payment->present()->completedAmount}\", \"{$payment->present()->currencySymbol}\")", 'label' => trans('texts.refund_payment')];
        }

        $actions[] = DropdownButton::DIVIDER;
        if (! $payment->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_payment')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_payment')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_expense')];
        }

        $data = [
            'account' => Auth::user()->account,
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
            'actions' => $actions,
            'paymentTypes' => Cache::get('paymentTypes'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
        ];

        return View::make('payments.edit', $data);
    }

    /**
     * @param CreatePaymentRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreatePaymentRequest $request)
    {
        // check payment has been marked sent
        $request->invoice->markSentIfUnsent();
        $input = $request->input();
        $amount = Utils::parseFloat($input['amount']);
        $credit = false;

        // if the payment amount is more than the balance create a credit
        if ($amount > $request->invoice->balance) {
            $credit = Credit::createNew();
            $credit->client_id = $request->invoice->client_id;
            $credit->credit_date = date_create()->format('Y-m-d');
            $credit->amount = $credit->balance = $amount - $request->invoice->balance;
            $credit->private_notes = trans('texts.credit_created_by', ['transaction_reference' => $input['transaction_reference']]);
            $credit->save();
            $input['amount'] = $request->invoice->balance;
        }

        $payment = $this->paymentService->save($input);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans($credit ? 'texts.created_payment_and_credit_emailed_client' : 'texts.created_payment_emailed_client'));
        } else {
            Session::flash('message', trans($credit ? 'texts.created_payment_and_credit' : 'texts.created_payment'));
        }

        return redirect()->to($payment->client->getRoute() . '#payments');
    }

    /**
     * @param UpdatePaymentRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdatePaymentRequest $request)
    {
        if (in_array($request->action, ['archive', 'delete', 'restore', 'refund', 'email'])) {
            return self::bulk();
        }

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
        $amount = Input::get('refund_amount');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        if ($action === 'email') {
            $payment = Payment::scope($ids)->first();
            $this->contactMailer->sendPaymentConfirmation($payment);
            Session::flash('message', trans('texts.emailed_payment'));
        } else {
            $count = $this->paymentService->bulk($ids, $action, ['refund_amount' => $amount]);
            if ($count > 0) {
                $message = Utils::pluralize($action == 'refund' ? 'refunded_payment' : $action.'d_payment', $count);
                Session::flash('message', $message);
            }
        }

        return $this->returnBulk(ENTITY_PAYMENT, $action, $ids);
    }
}

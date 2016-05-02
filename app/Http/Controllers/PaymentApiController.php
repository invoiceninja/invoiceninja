<?php namespace App\Http\Controllers;

use App\Ninja\Mailers\ContactMailer;
use Auth;
use Illuminate\Http\Request;
use Input;
use Utils;
use Response;
use App\Models\Payment;
use App\Models\Invoice;
use App\Ninja\Repositories\PaymentRepository;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\PaymentTransformer;
use App\Ninja\Transformers\InvoiceTransformer;

class PaymentApiController extends BaseAPIController
{
    protected $paymentRepo;

    protected $entityType = ENTITY_PAYMENT;

    public function __construct(PaymentRepository $paymentRepo, ContactMailer $contactMailer)
    {
        parent::__construct();

        $this->paymentRepo = $paymentRepo;
        $this->contactMailer = $contactMailer;
    }

    /**
     * @SWG\Get(
     *   path="/payments",
     *   tags={"payment"},
     *   summary="List of payments",
     *   @SWG\Response(
     *     response=200,
     *     description="A list with payments",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Payment"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $payments = Payment::scope()
                        ->withTrashed()
                        ->with(array_merge(['client.contacts', 'invitation', 'user', 'invoice'], $this->getIncluded()))                        
                        ->orderBy('created_at', 'desc');

        return $this->returnList($payments);
    }

    /**
    * @SWG\Put(
    *   path="/payments/{payment_id",
    *   summary="Update a payment",
    *   tags={"payment"},
    *   @SWG\Parameter(
    *     in="body",
    *     name="body",
    *     @SWG\Schema(ref="#/definitions/Payment")
    *   ),
    *   @SWG\Response(
    *     response=200,
    *     description="Update payment",
    *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Payment"))
    *   ),
    *   @SWG\Response(
    *     response="default",
    *     description="an ""unexpected"" error"
    *   )
    * )
    */

    public function update(Request $request, $publicId)
    {
        $data = Input::all();
        $data['public_id'] = $publicId;
        $error = false;

        if ($request->action == ACTION_ARCHIVE) {
            $payment = Payment::scope($publicId)->withTrashed()->firstOrFail();
            $this->paymentRepo->archive($payment);

            $transformer = new PaymentTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($payment, $transformer, 'invoice');

            return $this->response($data);
        }

        $payment = $this->paymentRepo->save($data);

        if ($error) {
            return $error;
        }

        /*
        $invoice = Invoice::scope($data['invoice_id'])->with('client', 'invoice_items', 'invitations')->with(['payments' => function($query) {
            $query->withTrashed();
        }])->withTrashed()->first();
        */

        $transformer = new PaymentTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($payment, $transformer, 'invoice');

        return $this->response($data);

    }


    /**
     * @SWG\Post(
     *   path="/payments",
     *   summary="Create a payment",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Payment")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New payment",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Payment"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store()
    {
        $data = Input::all();
        $error = false;

        if (isset($data['invoice_id'])) {
            $invoice = Invoice::scope($data['invoice_id'])->with('client')->first();

            if ($invoice) {
                $data['invoice_id'] = $invoice->id;
                $data['client_id'] = $invoice->client->id;
            } else {
                $error = trans('validation.not_in', ['attribute' => 'invoice_id']);
            }
        } else {
            $error = trans('validation.not_in', ['attribute' => 'invoice_id']);
        }

        if (!isset($data['transaction_reference'])) {
            $data['transaction_reference'] = '';
        }

        if ($error) {
            return $error;
        }

        $payment = $this->paymentRepo->save($data);

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
        }

        /*
        $invoice = Invoice::scope($invoice->public_id)->with('client', 'invoice_items', 'invitations')->with(['payments' => function($query) {
            $query->withTrashed();
        }])->first();
        */

        $transformer = new PaymentTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($payment, $transformer, 'invoice');

        return $this->response($data);

    }

    /**
    * @SWG\Delete(
    *   path="/payments/{payment_id}",
    *   summary="Delete a payment",
    *   tags={"payment"},
    *   @SWG\Parameter(
    *     in="body",
    *     name="body",
    *     @SWG\Schema(ref="#/definitions/Payment")
    *   ),
    *   @SWG\Response(
    *     response=200,
    *     description="Delete payment",
    *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Payment"))
    *   ),
    *   @SWG\Response(
    *     response="default",
    *     description="an ""unexpected"" error"
    *   )
    * )
    */

    public function destroy($publicId)
    {

        $payment = Payment::scope($publicId)->withTrashed()->first();
        $invoiceId = $payment->invoice->public_id;

        $this->paymentRepo->delete($payment);

        /*
        $invoice = Invoice::scope($invoiceId)->with('client', 'invoice_items', 'invitations')->with(['payments' => function($query) {
            $query->withTrashed();
        }])->first();
        */
        $transformer = new PaymentTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($payment, $transformer, 'invoice');

        return $this->response($data);
    }
}

<?php namespace App\Http\Controllers;

use Auth;
use Input;
use Utils;
use Response;
use App\Models\Payment;
use App\Models\Invoice;
use App\Ninja\Repositories\PaymentRepository;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\PaymentTransformer;

class PaymentApiController extends BaseAPIController
{
    protected $paymentRepo;

    public function __construct(PaymentRepository $paymentRepo)
    {
        parent::__construct();

        $this->paymentRepo = $paymentRepo;
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
        $paginator = Payment::scope();
        $payments = Payment::scope()
                        ->with('client.contacts', 'invitation', 'user', 'invoice');

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $payments->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $payments = $payments->orderBy('created_at', 'desc')->paginate();
        $paginator = $paginator->paginate();
        $transformer = new PaymentTransformer(Auth::user()->account, Input::get('serializer'));
        
        $data = $this->createCollection($payments, $transformer, 'payments', $paginator);

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
        $payment = Payment::scope($payment->public_id)->with('client', 'contact', 'user', 'invoice')->first();

        $transformer = new PaymentTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($payment, $transformer, 'payment');

        return $this->response($data);
    }
}

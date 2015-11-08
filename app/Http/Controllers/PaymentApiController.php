<?php namespace App\Http\Controllers;

use Input;
use Utils;
use Response;
use App\Models\Payment;
use App\Models\Invoice;
use App\Ninja\Repositories\PaymentRepository;

class PaymentApiController extends Controller
{
    protected $paymentRepo;

    public function __construct(PaymentRepository $paymentRepo)
    {
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
    public function index($clientPublicId = false)
    {
        $payments = Payment::scope()
                        ->with('client', 'contact', 'invitation', 'user', 'invoice');

        if ($clientPublicId) {
            $payments->whereHas('client', function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            });
        }

        $payments = $payments->orderBy('created_at', 'desc')->get();
        $payments = Utils::remapPublicIds($payments);
        
        $response = json_encode($payments, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($payments));

        return Response::make($response, 200, $headers);
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
                $data['invoice'] = $invoice->public_id;
                $data['client'] = $invoice->client->public_id;
            } else {
                $error = trans('validation.not_in', ['attribute' => 'invoice_id']);
            }
        } else {
            $error = trans('validation.not_in', ['attribute' => 'invoice_id']);
        }

        if (!isset($data['transaction_reference'])) {
            $data['transaction_reference'] = '';
        }

        if (!$error) {
            $payment = $this->paymentRepo->save($data);
            $payment = Payment::scope($payment->public_id)->with('client', 'contact', 'user', 'invoice')->first();

            $payment = Utils::remapPublicIds([$payment]);
        }

        $response = json_encode($error ?: $payment, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();
        return Response::make($response, 200, $headers);
    }
}

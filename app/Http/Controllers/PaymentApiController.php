<?php namespace App\Http\Controllers;

use App\Ninja\Mailers\ContactMailer;
use Input;
use Response;
use App\Models\Payment;
use App\Models\Invoice;
use App\Ninja\Repositories\PaymentRepository;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Requests\CreatePaymentAPIRequest;

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
                        ->with(['invoice'])
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($payments);
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

    public function update(UpdatePaymentRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $payment = $this->paymentRepo->save($data, $request->entity());

        return $this->itemResponse($payment);
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
    public function store(CreatePaymentAPIRequest $request)
    {
        $payment = $this->paymentRepo->save($request->input());

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
        }

        return $this->itemResponse($payment);
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

    public function destroy(UpdatePaymentRequest $request)
    {
        $payment = $request->entity();
        
        $this->clientRepo->delete($payment);

        return $this->itemResponse($payment);
    }

}

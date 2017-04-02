<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Requests\CreatePaymentAPIRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Repositories\PaymentRepository;
use Input;
use Response;

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
     *   summary="List payments",
     *   operationId="listPayments",
     *   tags={"payment"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of payments",
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
     * @SWG\Get(
     *   path="/payments/{payment_id}",
     *   summary="Retrieve a payment",
     *   operationId="getPayment",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="payment_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single payment",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Payment"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(PaymentRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/payments",
     *   summary="Create a payment",
     *   operationId="createPayment",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="payment",
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
        // check payment has been marked sent
        $request->invoice->markSentIfUnsent();

        $payment = $this->paymentRepo->save($request->input());

        if (Input::get('email_receipt')) {
            $this->contactMailer->sendPaymentConfirmation($payment);
        }

        return $this->itemResponse($payment);
    }

    /**
     * @SWG\Put(
     *   path="/payments/{payment_id}",
     *   summary="Update a payment",
     *   operationId="updatePayment",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="payment_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="payment",
     *     @SWG\Schema(ref="#/definitions/Payment")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated payment",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Payment"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
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
     * @SWG\Delete(
     *   path="/payments/{payment_id}",
     *   summary="Delete a payment",
     *   operationId="deletePayment",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="payment_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted payment",
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

        $this->paymentRepo->delete($payment);

        return $this->itemResponse($payment);
    }
}

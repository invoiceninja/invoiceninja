<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentTermAPIRequest;
use App\Http\Requests\PaymentTermRequest;
use App\Http\Requests\UpdatePaymentTermRequest;
use App\Models\PaymentTerm;
use App\Services\PaymentTermService;

class PaymentTermApiController extends BaseAPIController
{
    /**
     * @var PaymentTermService
     */
    protected $paymentTermService;
    protected $entityType = ENTITY_PAYMENT_TERM;

    /**
     * PaymentTermApiController constructor.
     *
     * @param PaymentTermService $paymentTermService
     */
    public function __construct(PaymentTermService $paymentTermService)
    {
        $this->paymentTermService = $paymentTermService;
    }

    /**
     * @SWG\Get(
     *   path="/paymentTerms",
     *   summary="List payment terms",
     *   operationId="listPaymentTerms",
     *   tags={"payment terms"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of payment terms",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/PaymentTerms"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function index()
    {

        $paymentTerms = PaymentTerm::scope()
            ->orWhere('account_id',0)
            ->withTrashed()
            ->orderBy('num_days', 'asc');

        dd($paymentTerms);
        
        return $this->listResponse($paymentTerms);
    }

        /**
         * @SWG\Get(
         *   path="/paymentTerms/{payment_term_id}",
         *   summary="Retrieve a payment term",
         *   operationId="getPaymentTermId",
         *   tags={"payment term"},
         *   @SWG\Parameter(
         *     in="path",
         *     name="payment_term_id",
         *     type="integer",
         *     required=true
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="A single payment term",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/PaymentTerms"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function show(PaymentTermRequest $request)
    {
        return $this->itemResponse($request->entity());
    }


        /**
         * @SWG\Post(
         *   path="/paymentTerms",
         *   summary="Create a payment Term",
         *   operationId="createPaymentTerm",
         *   tags={"payment term"},
         *   @SWG\Parameter(
         *     in="body",
         *     name="payment term",
         *     @SWG\Schema(ref="#/definitions/PaymentTerm")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="New payment Term",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/PaymentTerm"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */
    public function store(CreatePaymentTermAPIRequest $request)
    {
        //stub

    }

    /**
     * @SWG\Put(
     *   path="/paymentTerm/{payment_term_id}",
     *   summary="Update a payment term",
     *   operationId="updatePaymentTerm",
     *   tags={"payment"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="payment_term_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="payment term",
     *     @SWG\Schema(ref="#/definitions/PaymentTerm")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated payment term",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/PaymentTerm"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */
    public function update(UpdatePaymentTermRequest $request, $publicId)
    {
        //stub
    }

    /**
     * @SWG\Delete(
     *   path="/paymentTerm/{payment_term_id}",
     *   summary="Delete a payment term",
     *   operationId="deletePaymentTerm",
     *   tags={"payment term"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="payment_term_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted payment Term",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/PaymentTerm"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdatePaymentTermRequest $request)
    {
        //stub
    }
}

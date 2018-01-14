<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentTermAPIRequest;
use App\Http\Requests\PaymentTermRequest;
use App\Http\Requests\UpdatePaymentTermRequest;
use App\Libraries\Utils;
use App\Models\PaymentTerm;
use App\Ninja\Repositories\PaymentTermRepository;
use Illuminate\Support\Facades\Input;

class PaymentTermApiController extends BaseAPIController
{
    /**
     * @var PaymentTermRepository
     */
    protected $paymentTermRepo;
    protected $entityType = ENTITY_PAYMENT_TERM;

    /**
     * PaymentTermApiController constructor.
     *
     * @param PaymentTermRepository $paymentTermRepo
     */
    public function __construct(PaymentTermRepository $paymentTermRepo)
    {
        parent::__construct();

        $this->paymentTermRepo = $paymentTermRepo;
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
            ->orderBy('num_days', 'asc');

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

        $paymentTerm = PaymentTerm::createNew();

        $paymentTerm->num_days = Utils::parseInt(Input::get('num_days'));
        $paymentTerm->name = 'Net ' . $paymentTerm->num_days;
        $paymentTerm->save();

        return $this->itemResponse($paymentTerm);
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
     *   path="/paymentTerm/{num_days}",
     *   summary="Delete a payment term",
     *   operationId="deletePaymentTerm",
     *   tags={"payment term"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="num_days",
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
    public function destroy(UpdatePaymentTermRequest $request, $numDays)
    {

        $paymentTerm = PaymentTerm::where('num_days', $numDays)->first();
        $this->entity = $paymentTerm;

        if($paymentTerm->account_id == 0)
            return $this->errorResponse(['message'=>'Cannot delete a default Payment Term'], 400);

        $this->paymentTermRepo->archive($paymentTerm);

        return $this->itemResponse($paymentTerm);
    }
}

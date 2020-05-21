<?php

namespace App\Http\Controllers;

use App\Models\PaymentTerm;
use App\Transformers\PaymentTermTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class PaymentTermController extends BaseController
{
    use MakesHash;

    protected $entity_type = PaymentTerm::class;

    protected $entity_transformer = PaymentTermTransformer::class;

    public function __construct(PaymentTermRepository $payment_term_repo)
    {
        parent::__construct();
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/payment_terms",
     *      operationId="getPaymentTerms",
     *      tags={"payment_terms"},
     *      summary="Gets a list of payment terms",
     *      description="Lists payment terms",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of payment terms",
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/PaymentTerm"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     *
     */
    public function index()
    {
        $payment_terms = PaymentTerm::filter($filters);

        return $this->listResponse($payment_terms);
    }    
}

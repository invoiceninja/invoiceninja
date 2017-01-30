<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Models\TaxRate;
use App\Ninja\Repositories\TaxRateRepository;

class TaxRateApiController extends BaseAPIController
{
    /**
     * @var TaxRateRepository
     */
    protected $taxRateRepo;

    /**
     * @var string
     */
    protected $entityType = ENTITY_TAX_RATE;

    /**
     * TaxRateApiController constructor.
     *
     * @param TaxRateRepository $taxRateRepo
     */
    public function __construct(TaxRateRepository $taxRateRepo)
    {
        parent::__construct();

        $this->taxRateRepo = $taxRateRepo;
    }

    /**
     * @SWG\Get(
     *   path="/tax_rates",
     *   summary="List of tax rates",
     *   tags={"tax_rate"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with tax rates",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/TaxRate"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $taxRates = TaxRate::scope()
                        ->withTrashed()
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($taxRates);
    }

    /**
     * @SWG\Post(
     *   path="/tax_rates",
     *   tags={"tax_rate"},
     *   summary="Create a tax rate",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/TaxRate")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New tax rate",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/TaxRate"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateTaxRateRequest $request)
    {
        $taxRate = $this->taxRateRepo->save($request->input());

        return $this->itemResponse($taxRate);
    }

    /**
     * @SWG\Put(
     *   path="/tax_rates/{tax_rate_id}",
     *   tags={"tax_rate"},
     *   summary="Update a tax rate",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/TaxRate")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update tax rate",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/TaxRate"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function update(UpdateTaxRateRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $taxRate = $this->taxRateRepo->save($data, $request->entity());

        return $this->itemResponse($taxRate);
    }
}

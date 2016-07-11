<?php namespace App\Http\Controllers;

use App\Models\TaxRate;
use App\Ninja\Repositories\TaxRateRepository;
use App\Http\Requests\CreateTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;

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
     * @param TaxRateRepository $taxRateRepo
     */
    public function __construct(TaxRateRepository $taxRateRepo)
    {
        parent::__construct();

        $this->taxRateRepo = $taxRateRepo;
    }

    public function index()
    {
        $taxRates = TaxRate::scope()
                        ->withTrashed()
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($taxRates);
    }

    public function store(CreateTaxRateRequest $request)
    {
        $taxRate = $this->taxRateRepo->save($request->input());

        return $this->itemResponse($taxRate);
    }

    /**
     * @param UpdateTaxRateRequest $request
     * @param $publicId
     * @return \Illuminate\Http\Response
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

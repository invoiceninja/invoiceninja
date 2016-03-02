<?php namespace App\Http\Controllers;

use App\Services\TaxRateService;
use App\Ninja\Repositories\TaxRateRepository;
use App\Ninja\Transformers\TaxRateTransformer;
use Auth;
use App\Models\TaxRate;

use App\Http\Requests\CreateTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;

class TaxRateApiController extends BaseAPIController
{
    protected $taxRateService;
    protected $taxRateRepo;

    public function __construct(TaxRateService $taxRateService, TaxRateRepository $taxRateRepo)
    {
        //parent::__construct();

        $this->taxRateService = $taxRateService;
        $this->taxRateRepo = $taxRateRepo;
    }

    public function index()
    {
        $taxRates = TaxRate::scope()->withTrashed();
        $taxRates = $taxRates->paginate();

        $paginator = TaxRate::scope()->withTrashed()->paginate();

        $transformer = new TaxRateTransformer(Auth::user()->account, $this->serializer);
        $data = $this->createCollection($taxRates, $transformer, 'tax_rates', $paginator);

        return $this->response($data);
    }

    public function store(CreateTaxRateRequest $request)
    {
        return $this->save($request);
    }

    public function update(UpdateTaxRateRequest $request, $taxRatePublicId)
    {
        $taxRate = TaxRate::scope($taxRatePublicId)->firstOrFail();

        if ($request->action == ACTION_ARCHIVE) {
            $this->taxRateRepo->archive($taxRate);

            $transformer = new TaxRateTransformer(Auth::user()->account, $request->serializer);
            $data = $this->createItem($taxRate, $transformer, 'tax_rates');

            return $this->response($data);
        } else {
            return $this->save($request, $taxRate);
        }
    }

    private function save($request, $taxRate = false)
    {
        $taxRate = $this->taxRateRepo->save($request->input(), $taxRate);

        $transformer = new TaxRateTransformer(\Auth::user()->account, $request->serializer);
        $data = $this->createItem($taxRate, $transformer, 'tax_rates');

        return $this->response($data);
    }
}

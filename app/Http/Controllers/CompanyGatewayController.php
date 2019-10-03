<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\CompanyGateway\CreateCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\DestroyCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\EditCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\ShowCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\StoreCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\UpdateCompanyGatewayRequest;
use App\Models\CompanyGateway;
use App\Repositories\CompanyRepository;
use App\Transformers\CompanyGatewayTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;

/**
 * Class CompanyGatewayController
 * @package App\Http\Controllers
 */
class CompanyGatewayController extends BaseController
{
    use DispatchesJobs;
    use MakesHash;

    protected $entity_type = CompanyGateway::class;

    protected $entity_transformer = CompanyGatewayTransformer::class;

    protected $company_repo;

    public $forced_includes = [];

    /**
     * CompanyGatewayController constructor.
     */
    public function __construct(CompanyRepository $company_repo)
    {
    
        parent::__construct();

        $this->company_repo = $company_repo;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $company_gateways = CompanyGateway::whereCompanyId(auth()->user()->company()->id);

        return $this->listResponse($company_gateways);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateCompanyGatewayRequest $request)
    {

        $company_gateway = CompanyGatewayFactory::create(auth()->user()->company()->id, auth()->user()->id);
        
        return $this->itemResponse($company_gateway);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\SignupRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCompanyGatewayRequest $request)
    {

        $company_gateway = CompanyGatewayFactory::create(auth()->user()->company()->id, auth()->user()->id);
        $company_gateway->fill($request->all());
        $company_gateway->save();

        return $this->itemResponse($company_gateway);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        return $this->itemResponse($company_gateway);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        return $this->itemResponse($company_gateway);       

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        $company_gateway->fill($request->all());
        $company_gateway->save();

        return $this->itemResponse($company_gateway);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        $company_gateway->delete();

        return response()->json([], 200);
        
    }
}

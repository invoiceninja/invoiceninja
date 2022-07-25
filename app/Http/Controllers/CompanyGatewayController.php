<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Filters\CompanyGatewayFilters;
use App\Http\Requests\CompanyGateway\CreateCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\DestroyCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\EditCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\ShowCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\StoreCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\UpdateCompanyGatewayRequest;
use App\Jobs\Util\ApplePayDomain;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\PaymentDrivers\Stripe\Jobs\StripeWebhook;
use App\Repositories\CompanyRepository;
use App\Transformers\CompanyGatewayTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class CompanyGatewayController.
 */
class CompanyGatewayController extends BaseController
{
    use DispatchesJobs;
    use MakesHash;

    protected $entity_type = CompanyGateway::class;

    protected $entity_transformer = CompanyGatewayTransformer::class;

    protected $company_repo;

    public $forced_includes = [];

    private array $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    /**
     * CompanyGatewayController constructor.
     * @param CompanyRepository $company_repo
     */
    public function __construct(CompanyRepository $company_repo)
    {
        parent::__construct();

        $this->company_repo = $company_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways",
     *      operationId="getCompanyGateways",
     *      tags={"company_gateways"},
     *      summary="Gets a list of company_gateways",
     *      description="Lists company_gateways, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the company_gateways, these are handled by the CompanyGatewayFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of company_gateways",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
     */
    public function index(CompanyGatewayFilters $filters)
    {
        $company_gateways = CompanyGateway::filter($filters);

        return $this->listResponse($company_gateways);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateCompanyGatewayRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/create",
     *      operationId="getCompanyGatewaysCreate",
     *      tags={"company_gateways"},
     *      summary="Gets a new blank CompanyGateway object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateCompanyGatewayRequest $request)
    {
        $company_gateway = CompanyGatewayFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($company_gateway);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCompanyGatewayRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/company_gateways",
     *      operationId="storeCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Adds a CompanyGateway",
     *      description="Adds an CompanyGateway to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreCompanyGatewayRequest $request)
    {
        $company_gateway = CompanyGatewayFactory::create(auth()->user()->company()->id, auth()->user()->id);
        $company_gateway->fill($request->all());
        $company_gateway->save();

        /*Always ensure at least one fees and limits object is set per gateway*/
        if (! isset($company_gateway->fees_and_limits)) {
            $gateway_types = $company_gateway->driver(new Client)->gatewayTypes();

            $fees_and_limits = new \stdClass;
            $fees_and_limits->{$gateway_types[0]} = new FeesAndLimits;

            $company_gateway->fees_and_limits = $fees_and_limits;
            $company_gateway->save();
        }

        ApplePayDomain::dispatch($company_gateway, $company_gateway->company->db);

        if (in_array($company_gateway->gateway_key, $this->stripe_keys)) {
            StripeWebhook::dispatch($company_gateway->company->company_key, $company_gateway->id);
        }

        return $this->itemResponse($company_gateway);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="showCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Shows an CompanyGateway",
     *      description="Displays an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        return $this->itemResponse($company_gateway);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/{id}/edit",
     *      operationId="editCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Shows an CompanyGateway for editting",
     *      description="Displays an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        return $this->itemResponse($company_gateway);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="updateCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Updates an CompanyGateway",
     *      description="Handles the updating of an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        $company_gateway->fill($request->all());

        if (! $request->has('fees_and_limits')) {
            $company_gateway->fees_and_limits = '';
        }

        $company_gateway->save();

        // ApplePayDomain::dispatch($company_gateway, $company_gateway->company->db);

        return $this->itemResponse($company_gateway);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="deleteCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Deletes a CompanyGateway",
     *      description="Handles the deletion of an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        $company_gateway->driver(new Client)
                         ->disconnect();

        $company_gateway->delete();

        return $this->itemResponse($company_gateway->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/company_gateways/bulk",
     *      operationId="bulkCompanyGateways",
     *      tags={"company_gateways"},
     *      summary="Performs bulk actions on an array of company_gateways",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Array of company gateway IDs",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Company Gateways response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $company_gateways = CompanyGateway::withTrashed()->find($this->transformKeys($ids));

        $company_gateways->each(function ($company_gateway, $key) use ($action) {
            if (auth()->user()->can('edit', $company_gateway)) {
                $this->company_repo->{$action}($company_gateway);
            }
        });

        return $this->listResponse(CompanyGateway::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}

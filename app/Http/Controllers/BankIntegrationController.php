<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\BankIntegrationFactory;
use App\Filters\BankIntegrationFilters;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Http\Requests\BankIntegration\AdminBankIntegrationRequest;
use App\Http\Requests\BankIntegration\BulkBankIntegrationRequest;
use App\Http\Requests\BankIntegration\CreateBankIntegrationRequest;
use App\Http\Requests\BankIntegration\DestroyBankIntegrationRequest;
use App\Http\Requests\BankIntegration\EditBankIntegrationRequest;
use App\Http\Requests\BankIntegration\ShowBankIntegrationRequest;
use App\Http\Requests\BankIntegration\StoreBankIntegrationRequest;
use App\Http\Requests\BankIntegration\UpdateBankIntegrationRequest;
use App\Jobs\Bank\ProcessBankTransactions;
use App\Models\BankIntegration;
use App\Repositories\BankIntegrationRepository;
use App\Transformers\BankIntegrationTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BankIntegrationController extends BaseController
{
    use MakesHash;

    protected $entity_type = BankIntegration::class;

    protected $entity_transformer = BankIntegrationTransformer::class;

    protected $bank_integration_repo;

    public function __construct(BankIntegrationRepository $bank_integration_repo)
    {
        parent::__construct();

        $this->bank_integration_repo = $bank_integration_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/bank_integrations",
     *      operationId="getBankIntegrations",
     *      tags={"bank_integrations"},
     *      summary="Gets a list of bank_integrations",
     *      description="Lists all bank integrations",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Parameter(
     *          name="rows",
     *          in="query",
     *          description="The number of bank integrations to return",
     *          example="50",
     *          required=false,
     *          @OA\Schema(
     *              type="number",
     *              format="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="A list of bank integrations",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
     * @param Request $request
     * @return Response|mixed
     */
    public function index(BankIntegrationFilters $filters)
    {
        $bank_integrations = BankIntegration::filter($filters);

        return $this->listResponse($bank_integrations);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="showBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Shows a bank_integration",
     *      description="Displays a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function show(ShowBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param EditBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/{id}/edit",
     *      operationId="editBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Shows a bank_integration for editing",
     *      description="Displays a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function edit(EditBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="updateBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Updates a bank_integration",
     *      description="Handles the updating of a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function update(UpdateBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        //stubs for updating the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), $bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBankIntegrationRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/create",
     *      operationId="getBankIntegrationsCreate",
     *      tags={"bank_integrations"},
     *      summary="Gets a new blank bank_integration object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function create(CreateBankIntegrationRequest $request)
    {
        $bank_integration = BankIntegrationFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id);

        return $this->itemResponse($bank_integration);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreBankIntegrationRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations",
     *      operationId="storeBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Adds a bank_integration",
     *      description="Adds an bank_integration to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function store(StoreBankIntegrationRequest $request)
    {
        //stub to store the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), BankIntegrationFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id));

        return $this->itemResponse($bank_integration);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="deleteBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Deletes a bank_integration",
     *      description="Handles the deletion of a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
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
    public function destroy(DestroyBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        $this->bank_integration_repo->delete($bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }


    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations/bulk",
     *      operationId="bulkBankIntegrations",
     *      tags={"bank_integrations"},
     *      summary="Performs bulk actions on an array of bank_integrations",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Action paramters",
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
     *          description="The Bulk Action response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
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
    public function bulk(BulkBankIntegrationRequest $request)
    {
        $action = request()->input('action');

        $ids = request()->input('ids');
            
        $bank_integrations = BankIntegration::withTrashed()->whereIn('id', $this->transformKeys($ids))
                                            ->company()
                                            ->cursor()
                                            ->each(function ($bank_integration, $key) use ($action) {
                                                $this->bank_integration_repo->{$action}($bank_integration);
                                            });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(BankIntegration::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }


    /**
     * Return the remote list of accounts stored on the third party provider.
     *
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations/refresh_accounts",
     *      operationId="getRefreshAccounts",
     *      tags={"bank_integrations"},
     *      summary="Gets the list of accounts from the remote server",
     *      description="Adds an bank_integration to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function refreshAccounts(AdminBankIntegrationRequest $request)
    {
        // As yodlee is the first integration we don't need to perform switches yet, however
        // if we add additional providers we can reuse this class

        $bank_account_id = auth()->user()->account->bank_integration_account_id;

        if (!$bank_account_id) {
            return response()->json(['message' => 'Not yet authenticated with Bank Integration service'], 400);
        }

        $yodlee = new Yodlee($bank_account_id);

        $accounts = $yodlee->getAccounts();

        foreach ($accounts as $account) {
            if (!BankIntegration::where('bank_account_id', $account['id'])->where('company_id', auth()->user()->company()->id)->exists()) {
                $bank_integration = new BankIntegration();
                $bank_integration->company_id = auth()->user()->company()->id;
                $bank_integration->account_id = auth()->user()->account_id;
                $bank_integration->user_id = auth()->user()->id;
                $bank_integration->bank_account_id = $account['id'];
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_status = $account['account_status'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_id = $account['provider_id'];
                $bank_integration->provider_name = $account['provider_name'];
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->balance = $account['current_balance'];
                $bank_integration->currency = $account['account_currency'];
                
                $bank_integration->save();
            }
        }

        $account = auth()->user()->account;
        
        if (Cache::get("throttle_polling:{$account->key}")) {
            return response()->json(BankIntegration::query()->company(), 200);
        }

        $account->bank_integrations->each(function ($bank_integration) use ($account) {
            ProcessBankTransactions::dispatch($account->bank_integration_account_id, $bank_integration);
        });

        Cache::put("throttle_polling:{$account->key}", true, 300);

        return response()->json(BankIntegration::query()->company(), 200);
    }

    /**
     * Return the remote list of accounts stored on the third party provider
     * and update our local cache.
     *
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations/remove_account/account_id",
     *      operationId="getRemoveAccount",
     *      tags={"bank_integrations"},
     *      summary="Removes an account from the integration",
     *      description="Removes an account from the integration",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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

    public function removeAccount(AdminBankIntegrationRequest $request, $acc_id)
    {
        $bank_account_id = auth()->user()->account->bank_integration_account_id;

        if (!$bank_account_id) {
            return response()->json(['message' => 'Not yet authenticated with Bank Integration service'], 400);
        }

        $bi = BankIntegration::withTrashed()->where('bank_account_id', $acc_id)->company()->firstOrFail();

        $yodlee = new Yodlee($bank_account_id);
        $res = $yodlee->deleteAccount($acc_id);

        $this->bank_integration_repo->delete($bi);

        return $this->itemResponse($bi->fresh());
    }


    /**
     * Return the remote list of accounts stored on the third party provider
     * and update our local cache.
     *
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations/get_transactions/account_id",
     *      operationId="getAccountTransactions",
     *      tags={"bank_integrations"},
     *      summary="Retrieve transactions for a account",
     *      description="Retrieve transactions for a account",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Retrieve transactions for a account",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
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
    public function getTransactions(AdminBankIntegrationRequest $request)
    {
        auth()->user()->account->bank_integrations->each(function ($bank_integration) {
            (new ProcessBankTransactions(auth()->user()->account->bank_integration_account_id, $bank_integration))->handle();
        });

        return response()->json(['message' => 'Fetching transactions....'], 200);
    }
}

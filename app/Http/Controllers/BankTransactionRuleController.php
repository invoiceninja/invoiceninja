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

use App\Factory\BankTransactionRuleFactory;
use App\Filters\BankTransactionRuleFilters;
use App\Http\Requests\BankTransactionRule\BulkBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\CreateBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\DestroyBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\EditBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\ShowBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\StoreBankTransactionRuleRequest;
use App\Http\Requests\BankTransactionRule\UpdateBankTransactionRuleRequest;
use App\Models\BankTransactionRule;
use App\Repositories\BankTransactionRuleRepository;
use App\Services\Bank\BankMatchingService;
use App\Transformers\BankTransactionRuleTransformer;
use App\Utils\Traits\MakesHash;

class BankTransactionRuleController extends BaseController
{
    use MakesHash;

    protected $entity_type = BankTransactionRule::class;

    protected $entity_transformer = BankTransactionRuleTransformer::class;

    protected $bank_transaction_repo;

    public function __construct(BankTransactionRuleRepository $bank_transaction_repo)
    {
        parent::__construct();

        $this->bank_transaction_repo = $bank_transaction_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/bank_transaction_rules",
     *      operationId="getBankTransactionRules",
     *      tags={"bank_transaction_rules"},
     *      summary="Gets a list of bank_transaction_rules",
     *      description="Lists all bank transaction rules",
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
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
     * @param BankTransactionRuleFilters $filters
     * @return Response|mixed
     */
    public function index(BankTransactionRuleFilters $filters)
    {
        $bank_transaction_rules = BankTransactionRule::filter($filters);

        return $this->listResponse($bank_transaction_rules);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowBankTransactionRuleRequest $request
     * @param BankTransactionRule $bank_transaction_rule
     * @return \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transaction_rules/{id}",
     *      operationId="showBankTransactionRule",
     *      tags={"bank_transaction_rules"},
     *      summary="Shows a bank_transaction",
     *      description="Displays a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Bank Transaction RuleHashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction rule object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
    public function show(ShowBankTransactionRuleRequest $request, BankTransactionRule $bank_transaction_rule)
    {
        return $this->itemResponse($bank_transaction_rule);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param EditBankTransactionRuleRequest $request
     * @param BankTransactionRule $bank_transaction_rule
     * @return \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transaction_rules/{id}/edit",
     *      operationId="editBankTransactionRule",
     *      tags={"bank_transaction_rules"},
     *      summary="Shows a bank_transaction for editing",
     *      description="Displays a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Bank Transaction Rule Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction rule object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
    public function edit(EditBankTransactionRuleRequest $request, BankTransactionRule $bank_transaction_rule)
    {
        return $this->itemResponse($bank_transaction_rule);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateBankTransactionRuleRequest $request
     * @param BankTransactionRule $bank_transaction_rule
     * @return \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/bank_transaction_rules/{id}",
     *      operationId="updateBankTransactionRule",
     *      tags={"bank_transaction_rules"},
     *      summary="Updates a bank_transaction Rule",
     *      description="Handles the updating of a bank_transaction rule by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Bank Transaction Rule Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction rule object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
    public function update(UpdateBankTransactionRuleRequest $request, BankTransactionRule $bank_transaction_rule)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $bank_transaction_rule = $this->bank_transaction_repo->save($request->all(), $bank_transaction_rule);

        BankMatchingService::dispatch($user->company()->id, $user->company()->db);

        return $this->itemResponse($bank_transaction_rule->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBankTransactionRuleRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transaction_rules/create",
     *      operationId="getBankTransactionRulesCreate",
     *      tags={"bank_transaction_rules"},
     *      summary="Gets a new blank bank_transaction rule object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank bank_transaction rule object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
    public function create(CreateBankTransactionRuleRequest $request)
    {
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $bank_transaction_rule = BankTransactionRuleFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($bank_transaction_rule);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreBankTransactionRuleRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/bank_transaction_rules",
     *      operationId="storeBankTransactionRule",
     *      tags={"bank_transaction_rules"},
     *      summary="Adds a bank_transaction rule",
     *      description="Adds an bank_transaction to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_transaction rule object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransactionRule"),
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
    public function store(StoreBankTransactionRuleRequest $request)
    {

        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $bank_transaction_rule = $this->bank_transaction_repo->save($request->all(), BankTransactionRuleFactory::create($user->company()->id, $user->id));

        BankMatchingService::dispatch($user->company()->id, $user->company()->db);

        return $this->itemResponse($bank_transaction_rule);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyBankTransactionRuleRequest $request
     * @param BankTransactionRule $bank_transaction_rule
     * @return \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/bank_transaction_rules/{id}",
     *      operationId="deleteBankTransactionRule",
     *      tags={"bank_transaction_rules"},
     *      summary="Deletes a bank_transaction rule",
     *      description="Handles the deletion of a bank_transaction rule by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Bank Transaction Rule Hashed ID",
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
    public function destroy(DestroyBankTransactionRuleRequest $request, BankTransactionRule $bank_transaction_rule)
    {
        $this->bank_transaction_repo->delete($bank_transaction_rule);

        return $this->itemResponse($bank_transaction_rule->fresh());
    }


    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Support\Collection
     *
     * @OA\Post(
     *      path="/api/v1/bank_transation_rules/bulk",
     *      operationId="bulkBankTransactionRules",
     *      tags={"bank_transaction_rules"},
     *      summary="Performs bulk actions on an array of bank_transation rules",
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
    public function bulk(BulkBankTransactionRuleRequest $request)
    {
        $action = $request->input('action');

        $ids = $request->input('ids');

        $bank_transaction_rules = BankTransactionRule::withTrashed()
                                                     ->whereIn('id', $this->transformKeys($ids))
                                                     ->company()
                                                     ->cursor()
                                                     ->each(function ($bank_transaction_rule, $key) use ($action) {
                                                         $this->bank_transaction_repo->{$action}($bank_transaction_rule);
                                                     });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */
        return $this->listResponse(BankTransactionRule::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }
}

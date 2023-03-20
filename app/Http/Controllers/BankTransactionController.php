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

use App\Factory\BankTransactionFactory;
use App\Filters\BankTransactionFilters;
use App\Http\Requests\BankTransaction\BulkBankTransactionRequest;
use App\Http\Requests\BankTransaction\CreateBankTransactionRequest;
use App\Http\Requests\BankTransaction\DestroyBankTransactionRequest;
use App\Http\Requests\BankTransaction\EditBankTransactionRequest;
use App\Http\Requests\BankTransaction\MatchBankTransactionRequest;
use App\Http\Requests\BankTransaction\ShowBankTransactionRequest;
use App\Http\Requests\BankTransaction\StoreBankTransactionRequest;
use App\Http\Requests\BankTransaction\UpdateBankTransactionRequest;
use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;
use App\Repositories\BankTransactionRepository;
use App\Transformers\BankTransactionTransformer;
use App\Utils\Traits\MakesHash;

class BankTransactionController extends BaseController
{
    use MakesHash;
    
    protected $entity_type = BankTransaction::class;

    protected $entity_transformer = BankTransactionTransformer::class;

    protected $bank_transaction_repo;

    public function __construct(BankTransactionRepository $bank_transaction_repo)
    {
        parent::__construct();

        $this->bank_transaction_repo = $bank_transaction_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/bank_transactions",
     *      operationId="getBankTransactions",
     *      tags={"bank_transactions"},
     *      summary="Gets a list of bank_transactions",
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
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
     * @param BankTransactionFilters $filter
     * @return Response|mixed
     */
    public function index(BankTransactionFilters $filters)
    {
        $bank_transactions = BankTransaction::filter($filters);

        return $this->listResponse($bank_transactions);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowBankTransactionRequest $request
     * @param BankTransaction $bank_transaction
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transactions/{id}",
     *      operationId="showBankTransaction",
     *      tags={"bank_transactions"},
     *      summary="Shows a bank_transaction",
     *      description="Displays a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankTransaction Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
    public function show(ShowBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        return $this->itemResponse($bank_transaction);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param EditBankTransactionRequest $request
     * @param BankTransaction $bank_transaction
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transactions/{id}/edit",
     *      operationId="editBankTransaction",
     *      tags={"bank_transactions"},
     *      summary="Shows a bank_transaction for editing",
     *      description="Displays a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankTransaction Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
    public function edit(EditBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        return $this->itemResponse($bank_transaction);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateBankTransactionRequest $request
     * @param BankTransaction $bank_transaction
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/bank_transactions/{id}",
     *      operationId="updateBankTransaction",
     *      tags={"bank_transactions"},
     *      summary="Updates a bank_transaction",
     *      description="Handles the updating of a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankTransaction Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_transaction object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
    public function update(UpdateBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        //stubs for updating the model
        $bank_transaction = $this->bank_transaction_repo->save($request->all(), $bank_transaction);

        return $this->itemResponse($bank_transaction->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBankTransactionRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_transactions/create",
     *      operationId="getBankTransactionsCreate",
     *      tags={"bank_transactions"},
     *      summary="Gets a new blank bank_transaction object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank bank_transaction object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
    public function create(CreateBankTransactionRequest $request)
    {
        $bank_transaction = BankTransactionFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id);

        return $this->itemResponse($bank_transaction);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreBankTransactionRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/bank_transactions",
     *      operationId="storeBankTransaction",
     *      tags={"bank_transactions"},
     *      summary="Adds a bank_transaction",
     *      description="Adds an bank_transaction to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_transaction object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankTransaction"),
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
    public function store(StoreBankTransactionRequest $request)
    {
        //stub to store the model
        $bank_transaction = $this->bank_transaction_repo->save($request->all(), BankTransactionFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id));

        return $this->itemResponse($bank_transaction);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyBankTransactionRequest $request
     * @param BankTransaction $bank_transaction
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/bank_transactions/{id}",
     *      operationId="deleteBankTransaction",
     *      tags={"bank_transactions"},
     *      summary="Deletes a bank_transaction",
     *      description="Handles the deletion of a bank_transaction by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankTransaction Hashed ID",
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
    public function destroy(DestroyBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        $this->bank_transaction_repo->delete($bank_transaction);

        return $this->itemResponse($bank_transaction->fresh());
    }


    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     * @OA\Post(
     *      path="/api/v1/bank_transations/bulk",
     *      operationId="bulkBankTransactions",
     *      tags={"bank_transactions"},
     *      summary="Performs bulk actions on an array of bank_transations",
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
    public function bulk(BulkBankTransactionRequest $request)
    {
        $action = $request->input('action');

        $ids = request()->input('ids');
            
        $bank_transactions = BankTransaction::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if ($action == 'convert_matched') { //catch this action
            $this->bank_transaction_repo->convert_matched($bank_transactions);
        } else {
            $bank_transactions->each(function ($bank_transaction, $key) use ($action) {
                $this->bank_transaction_repo->{$action}($bank_transaction);
            });
        }
        
        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(BankTransaction::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     * @OA\Post(
     *      path="/api/v1/bank_transations/match",
     *      operationId="matchBankTransactions",
     *      tags={"bank_transactions"},
     *      summary="Performs match actions on an array of bank_transactions",
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
    public function match(MatchBankTransactionRequest $request)
    {
        $bts = (new MatchBankTransactions(auth()->user()->company()->id, auth()->user()->company()->db, $request->all()))->handle();

        return $this->listResponse($bts);
    }
}

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

use App\Factory\ExpenseCategoryFactory;
use App\Filters\ExpenseCategoryFilters;
use App\Http\Requests\ExpenseCategory\CreateExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\DestroyExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\EditExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\ShowExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\UpdateExpenseCategoryRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Repositories\BaseRepository;
use App\Transformers\ExpenseCategoryTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

/**
 * Class ExpenseCategoryController.
 */
class ExpenseCategoryController extends BaseController
{
    use MakesHash;

    protected $entity_type = ExpenseCategory::class;

    protected $entity_transformer = ExpenseCategoryTransformer::class;

    protected $base_repo;

    public function __construct(BaseRepository $base_repo)
    {
        parent::__construct();

        $this->base_repo = $base_repo;
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/expense_categories",
     *      operationId="getExpenseCategorys",
     *      tags={"expense_categories"},
     *      summary="Gets a list of expense_categories",
     *      description="Lists tax rates",
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of expense_categories",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
     *
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(ExpenseCategoryFilters $filters)
    {
        $expense_categories = ExpenseCategory::filter($filters);

        return $this->listResponse($expense_categories);
    }



    /**
     * Show the form for creating a new resource.
     *
     * @param CreateExpenseCategoryRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/expense_categories/create",
     *      operationId="getExpenseCategoryCreate",
     *      tags={"expense_categories"},
     *      summary="Gets a new blank Expens Category object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank Expens Category object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
    public function create(CreateExpenseCategoryRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $expense_category = ExpenseCategoryFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($expense_category);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param StoreExpenseCategoryRequest $request
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/expense_categories",
     *      operationId="storeExpenseCategory",
     *      tags={"expense_categories"},
     *      summary="Adds a expense category",
     *      description="Adds an expense category to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
    public function store(StoreExpenseCategoryRequest $request)
    {
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $expense_category = ExpenseCategoryFactory::create($user->company()->id, $user->id);
        $expense_category->fill($request->all());
        $expense_category->save();

        return $this->itemResponse($expense_category);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowExpenseCategoryRequest $request
     * @param ExpenseCategory $expense_category
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/expense_categories/{id}",
     *      operationId="showExpenseCategory",
     *      tags={"expense_categories"},
     *      summary="Shows a Expens Category",
     *      description="Displays an ExpenseCategory by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ExpenseCategory Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Expens Category object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
    public function show(ShowExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        return $this->itemResponse($expense_category);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditExpenseCategoryRequest $request
     * @param ExpenseCategory $expense_category
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/expense_categories/{id}/edit",
     *      operationId="editExpenseCategory",
     *      tags={"expense_categories"},
     *      summary="Shows a Expens Category for editting",
     *      description="Displays a Expens Category by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ExpenseCategory Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Expens Category object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
    public function edit(EditExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        return $this->itemResponse($expense_category);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateExpenseCategoryRequest $request
     * @param ExpenseCategory $expense_category
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/expense_categories/{id}",
     *      operationId="updateExpenseCategory",
     *      tags={"expense_categories"},
     *      summary="Updates a tax rate",
     *      description="Handles the updating of a tax rate by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ExpenseCategory Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ExpenseCategory object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ExpenseCategory"),
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
    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        $expense_category->fill($request->all());
        $expense_category->save();

        return $this->itemResponse($expense_category);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyExpenseCategoryRequest $request
     * @param ExpenseCategory $expense_category
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/expense_categories/{id}",
     *      operationId="deleteExpenseCategory",
     *      tags={"expense_categories"},
     *      summary="Deletes a ExpenseCategory",
     *      description="Handles the deletion of an ExpenseCategory by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ExpenseCategory Hashed ID",
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
    public function destroy(DestroyExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        $expense_category->is_deleted = true;
        $expense_category->save();
        $expense_category->delete();

        return $this->itemResponse($expense_category);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/expense_categories/bulk",
     *      operationId="bulkExpenseCategorys",
     *      tags={"expense_categories"},
     *      summary="Performs bulk actions on an array of ExpenseCategorys",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Expens Categorys",
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
     *          description="The ExpenseCategory List response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $action = request()->input('action');

        $ids = request()->input('ids');

        $expense_categories = ExpenseCategory::withTrashed()->find($this->transformKeys($ids));

        $expense_categories->each(function ($expense_category, $key) use ($action, $user) {
            if ($user->can('edit', $expense_category)) {
                $this->base_repo->{$action}($expense_category);
            }
        });

        return $this->listResponse(ExpenseCategory::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}

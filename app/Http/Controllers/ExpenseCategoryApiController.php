<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Requests\CreateExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Ninja\Repositories\ExpenseCategoryRepository;
use App\Services\ExpenseCategoryService;
use Input;

class ExpenseCategoryApiController extends BaseAPIController
{
    protected $categoryRepo;
    protected $categoryService;
    protected $entityType = ENTITY_EXPENSE_CATEGORY;

    public function __construct(ExpenseCategoryRepository $categoryRepo, ExpenseCategoryService $categoryService)
    {
        parent::__construct();

        $this->categoryRepo = $categoryRepo;
        $this->categoryService = $categoryService;
    }

    /**
     * @SWG\Get(
     *   path="/expense_categories",
     *   summary="List expense categories",
     *   operationId="listExpenseCategories",
     *   tags={"expense_category"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of expense categories",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/ExpenseCategory"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $clients = ExpenseCategory::scope()
            ->orderBy('created_at', 'desc')
            ->withTrashed();

        return $this->listResponse($clients);
    }

    /**
     * @SWG\Get(
     *   path="/expense_categories/{expense_category_id}",
     *   summary="Retrieve an Expense Category",
     *   operationId="getExpenseCategory",
     *   tags={"expense_category"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_category_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single expense categroy",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/ExpenseCategory"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(ExpenseCategory $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/expense_categories",
     *   summary="Create an expense category",
     *   operationId="createExpenseCategory",
     *   tags={"expense_category"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="expense_category",
     *     @SWG\Schema(ref="#/definitions/ExpenseCategory")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New expense category",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/ExpenseCategory"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input());

        return $this->itemResponse($category);
    }

    /**
     * @SWG\Put(
     *   path="/expense_categories/{expense_category_id}",
     *   summary="Update an expense category",
     *   operationId="updateExpenseCategory",
     *   tags={"expense_category"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_category_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="expense_category",
     *     @SWG\Schema(ref="#/definitions/ExpenseCategory")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated expense category",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/ExpenseCategory"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function update(UpdateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input(), $request->entity());

        return $this->itemResponse($category);
    }

    /**
     * @SWG\Delete(
     *   path="/expense_categories/{expense_category_id}",
     *   summary="Delete an expense category",
     *   operationId="deleteExpenseCategory",
     *   tags={"expense_category"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_category_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted expense category",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/ExpenseCategory"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateExpenseCategoryRequest $request)
    {
        $entity = $request->entity();

        $this->expenseCategoryRepo->delete($entity);

        return $this->itemResponse($entity);
    }
}

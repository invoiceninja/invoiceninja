<?php namespace App\Http\Controllers;

use Input;
use App\Services\ExpenseCategoryService;
use App\Http\Requests\CreateExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Ninja\Repositories\ExpenseCategoryRepository;

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
     * @SWG\Post(
     *   path="/expense_categories",
     *   tags={"expense_category"},
     *   summary="Create an expense category",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
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
     *   tags={"expense_category"},
     *   summary="Update an expense category",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/ExpenseCategory")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update expense category",
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
}

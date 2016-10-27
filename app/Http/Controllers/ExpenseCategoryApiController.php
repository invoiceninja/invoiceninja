<?php namespace App\Http\Controllers;

use View;
use Utils;
use Input;
use Session;
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
        $this->categoryRepo = $categoryRepo;
        $this->categoryService = $categoryService;
    }

    public function updateCategory(UpdateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input(), $request->entity());

        return $this->itemResponse($category);
    }

    public function addCategory(CreateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input());

        return $this->itemResponse($category);

    }
}

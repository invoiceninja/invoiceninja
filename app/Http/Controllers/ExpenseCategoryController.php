<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Ninja\Datatables\ExpenseCategoryDatatable;
use App\Ninja\Repositories\ExpenseCategoryRepository;
use App\Services\ExpenseCategoryService;
use Input;
use Session;
use View;

class ExpenseCategoryController extends BaseController
{
    protected $categoryRepo;
    protected $categoryService;
    protected $entityType = ENTITY_EXPENSE_CATEGORY;

    public function __construct(ExpenseCategoryRepository $categoryRepo, ExpenseCategoryService $categoryService)
    {
        $this->categoryRepo = $categoryRepo;
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_EXPENSE_CATEGORY,
            'datatable' => new ExpenseCategoryDatatable(),
            'title' => trans('texts.expense_categories'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        return $this->categoryService->getDatatable(Input::get('sSearch'));
    }

    public function create(ExpenseCategoryRequest $request)
    {
        $data = [
            'category' => null,
            'method' => 'POST',
            'url' => 'expense_categories',
            'title' => trans('texts.new_category'),
        ];

        return View::make('expense_categories.edit', $data);
    }

    public function edit(ExpenseCategoryRequest $request)
    {
        $category = $request->entity();

        $data = [
            'category' => $category,
            'method' => 'PUT',
            'url' => 'expense_categories/' . $category->public_id,
            'title' => trans('texts.edit_category'),
        ];

        return View::make('expense_categories.edit', $data);
    }

    public function store(CreateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input());

        Session::flash('message', trans('texts.created_expense_category'));

        return redirect()->to($category->getRoute());
    }

    public function update(UpdateExpenseCategoryRequest $request)
    {
        $category = $this->categoryRepo->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_expense_category'));

        return redirect()->to($category->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->categoryService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_expense_category" : "{$action}d_expense_categories";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/expense_categories');
    }
}

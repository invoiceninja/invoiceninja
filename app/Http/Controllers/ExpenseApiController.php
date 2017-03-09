<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Ninja\Repositories\ExpenseRepository;
use App\Services\ExpenseService;

class ExpenseApiController extends BaseAPIController
{
    // Expenses
    protected $expenseRepo;
    protected $expenseService;

    protected $entityType = ENTITY_EXPENSE;

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService)
    {
        parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
    }

    /**
     * @SWG\Get(
     *   path="/expenses",
     *   summary="List of expenses",
     *   tags={"expense"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of expenses",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Expense"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $expenses = Expense::scope()
            ->withTrashed()
            ->with('client', 'invoice', 'vendor', 'expense_category')
            ->orderBy('created_at', 'desc');

        return $this->listResponse($expenses);
    }

    /**
     * @SWG\Get(
     *   path="/expenses/{expense_id}",
     *   tags={"expense"},
     *   summary="Retrieve an expense",
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single expense",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Expense"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(ExpenseRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/expenses",
     *   tags={"expense"},
     *   summary="Create an expense",
     *   @SWG\Parameter(
     *     in="body",
     *     name="expense",
     *     @SWG\Schema(ref="#/definitions/Expense")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New expense",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Expense"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateExpenseRequest $request)
    {
        $expense = $this->expenseRepo->save($request->input());

        $expense = Expense::scope($expense->public_id)
            ->with('client', 'invoice', 'vendor')
            ->first();

        return $this->itemResponse($expense);
    }

    /**
     * @SWG\Put(
     *   path="/expenses/{expense_id}",
     *   tags={"expense"},
     *   summary="Update an expense",
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="expense",
     *     @SWG\Schema(ref="#/definitions/Expense")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated expense",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Expense"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */
    public function update(UpdateExpenseRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $expense = $this->expenseRepo->save($data, $request->entity());

        return $this->itemResponse($expense);
    }

    /**
     * @SWG\Delete(
     *   path="/expenses/{expense_id}",
     *   tags={"expense"},
     *   summary="Delete an expense",
     *   @SWG\Parameter(
     *     in="path",
     *     name="expense_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted expense",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Expense"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateExpenseRequest $request)
    {
        $expense = $request->entity();

        $this->expenseRepo->delete($expense);

        return $this->itemResponse($expense);
    }
}

<?php namespace App\Http\Controllers;

use App\Models\Expense;
use app\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Transformers\ExpenseTransformer;
use App\Services\ExpenseService;
use Utils;
use Response;
use Input;
use Auth;


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

    public function index()
    {
        $expenses = Expense::scope()
            ->withTrashed()
            ->with('client', 'invoice', 'vendor')
            ->orderBy('created_at','desc');

        return $this->listResponse($expenses);
    }

    public function update()
    {
        //stub

    }

    public function store()
    {
        //stub

    }

    public function destroy()
    {
        //stub

    }


}
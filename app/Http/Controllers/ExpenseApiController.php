<?php namespace App\Http\Controllers;
// vendor
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

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService)
    {
        //parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
    }

    public function index()
    {

        $expenses = Expense::scope()
            ->withTrashed()
            ->orderBy('created_at','desc');

        $expenses = $expenses->paginate();

        $transformer = new ExpenseTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = Expense::scope()->withTrashed()->paginate();

        $data = $this->createCollection($expenses, $transformer, ENTITY_EXPENSE, $paginator);

        return $this->response($data);

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
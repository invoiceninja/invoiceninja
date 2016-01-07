<?php namespace app\Listeners;

use Carbon;
use App\Models\Credit;
use App\Events\PaymentWasDeleted;
use App\Ninja\Repositories\ExpenseRepository;

class ExpenseListener
{
    // Expenses
    protected $expenseRepo;

    public function __construct(ExpenseRepository $expenseRepo)
    {
        $this->expenseRepo = $expenseRepo;
    }
}

<?php namespace App\Listeners;

use Carbon;
use App\Models\Expense;
use App\Events\PaymentWasDeleted;
use App\Events\InvoiceWasDeleted;
use App\Ninja\Repositories\ExpenseRepository;

class ExpenseListener
{
    // Expenses
    protected $expenseRepo;

    public function __construct(ExpenseRepository $expenseRepo)
    {
        $this->expenseRepo = $expenseRepo;
    }

    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        // Release any tasks associated with the deleted invoice
        Expense::where('invoice_id', '=', $event->invoice->id)
                ->update(['invoice_id' => null]);
    }
}

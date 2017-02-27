<?php

namespace App\Listeners;

use App\Events\InvoiceWasDeleted;
use App\Models\Expense;
use App\Ninja\Repositories\ExpenseRepository;

/**
 * Class ExpenseListener.
 */
class ExpenseListener
{
    // Expenses
    /**
     * @var ExpenseRepository
     */
    protected $expenseRepo;

    /**
     * ExpenseListener constructor.
     *
     * @param ExpenseRepository $expenseRepo
     */
    public function __construct(ExpenseRepository $expenseRepo)
    {
        $this->expenseRepo = $expenseRepo;
    }

    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        // Release any tasks associated with the deleted invoice
        Expense::where('invoice_id', '=', $event->invoice->id)
                ->update(['invoice_id' => null]);
    }
}

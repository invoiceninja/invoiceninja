<?php namespace App\Listeners;

use App\Models\Task;
use App\Events\InvoiceWasDeleted;

/**
 * Class TaskListener
 */
class TaskListener
{
    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        // Release any tasks associated with the deleted invoice
        Task::where('invoice_id', '=', $event->invoice->id)
                ->update(['invoice_id' => null]);
    }
}

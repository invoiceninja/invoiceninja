<?php

namespace App\Listeners;

use App\Events\InvoiceWasDeleted;
use App\Events\ClientWasDeleted;
use App\Events\QuoteWasDeleted;
use App\Events\TaskWasDeleted;
use App\Events\ExpenseWasDeleted;
use App\Events\ProjectWasDeleted;
use App\Events\ProposalWasDeleted;
use App\Libraries\HistoryUtils;

/**
 * Class InvoiceListener.
 */
class HistoryListener
{
    /**
     * @param ClientWasDeleted $event
     */
    public function deletedClient(ClientWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->client);
    }

    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->invoice);
    }

    /**
     * @param QuoteWasDeleted $event
     */
    public function deletedQuote(QuoteWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->quote);
    }

    /**
     * @param TaskWasDeleted $event
     */
    public function deletedTask(TaskWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->task);
    }

    /**
     * @param ExpenseWasDeleted $event
     */
    public function deletedExpense(ExpenseWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->expense);
    }

    /**
     * @param ProjectWasDeleted $event
     */
    public function deletedProject(ProjectWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->project);
    }

    /**
     * @param ProposalWasDeleted $event
     */
    public function deletedProposal(ProposalWasDeleted $event)
    {
        HistoryUtils::deleteHistory($event->proposal);
    }
}

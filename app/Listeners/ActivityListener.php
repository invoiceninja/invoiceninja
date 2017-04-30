<?php

namespace App\Listeners;

use App\Events\ClientWasArchived;
use App\Events\ClientWasCreated;
use App\Events\ClientWasDeleted;
use App\Events\ClientWasRestored;
use App\Events\CreditWasArchived;
use App\Events\CreditWasCreated;
use App\Events\CreditWasDeleted;
use App\Events\CreditWasRestored;
use App\Events\ExpenseWasArchived;
use App\Events\ExpenseWasCreated;
use App\Events\ExpenseWasDeleted;
use App\Events\ExpenseWasRestored;
use App\Events\ExpenseWasUpdated;
use App\Events\InvoiceInvitationWasEmailed;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\InvoiceWasArchived;
use App\Events\InvoiceWasCreated;
use App\Events\InvoiceWasDeleted;
use App\Events\InvoiceWasRestored;
use App\Events\InvoiceWasUpdated;
use App\Events\PaymentFailed;
use App\Events\PaymentWasArchived;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasDeleted;
use App\Events\PaymentWasRefunded;
use App\Events\PaymentWasRestored;
use App\Events\PaymentWasVoided;
use App\Events\QuoteInvitationWasApproved;
use App\Events\QuoteInvitationWasEmailed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteWasArchived;
use App\Events\QuoteWasCreated;
use App\Events\QuoteWasDeleted;
use App\Events\QuoteWasRestored;
use App\Events\QuoteWasUpdated;
use App\Events\TaskWasArchived;
use App\Events\TaskWasCreated;
use App\Events\TaskWasDeleted;
use App\Events\TaskWasRestored;
use App\Events\TaskWasUpdated;
use App\Models\Invoice;
use App\Ninja\Repositories\ActivityRepository;

/**
 * Class ActivityListener.
 */
class ActivityListener
{
    /**
     * @var ActivityRepository
     */
    protected $activityRepo;

    /**
     * ActivityListener constructor.
     *
     * @param ActivityRepository $activityRepo
     */
    public function __construct(ActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    /**
     * @param ClientWasCreated $event
     */
    public function createdClient(ClientWasCreated $event)
    {
        $this->activityRepo->create(
            $event->client,
            ACTIVITY_TYPE_CREATE_CLIENT
        );
    }

    /**
     * @param ClientWasDeleted $event
     */
    public function deletedClient(ClientWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->client,
            ACTIVITY_TYPE_DELETE_CLIENT
        );
    }

    /**
     * @param ClientWasArchived $event
     */
    public function archivedClient(ClientWasArchived $event)
    {
        if ($event->client->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->client,
            ACTIVITY_TYPE_ARCHIVE_CLIENT
        );
    }

    /**
     * @param ClientWasRestored $event
     */
    public function restoredClient(ClientWasRestored $event)
    {
        $this->activityRepo->create(
            $event->client,
            ACTIVITY_TYPE_RESTORE_CLIENT
        );
    }

    /**
     * @param InvoiceWasCreated $event
     */
    public function createdInvoice(InvoiceWasCreated $event)
    {
        $this->activityRepo->create(
            $event->invoice,
            ACTIVITY_TYPE_CREATE_INVOICE,
            $event->invoice->getAdjustment()
        );
    }

    /**
     * @param InvoiceWasUpdated $event
     */
    public function updatedInvoice(InvoiceWasUpdated $event)
    {
        if (! $event->invoice->isChanged()) {
            return;
        }

        $backupInvoice = Invoice::with('invoice_items', 'client.account', 'client.contacts')
                            ->withTrashed()
                            ->find($event->invoice->id);

        $activity = $this->activityRepo->create(
            $event->invoice,
            ACTIVITY_TYPE_UPDATE_INVOICE,
            $event->invoice->getAdjustment()
        );

        $activity->json_backup = $backupInvoice->hidePrivateFields()->toJSON();
        $activity->save();
    }

    /**
     * @param InvoiceWasDeleted $event
     */
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        $invoice = $event->invoice;

        $this->activityRepo->create(
            $invoice,
            ACTIVITY_TYPE_DELETE_INVOICE,
            $invoice->affectsBalance() ? $invoice->balance * -1 : 0,
            $invoice->affectsBalance() ? $invoice->getAmountPaid() * -1 : 0
        );
    }

    /**
     * @param InvoiceWasArchived $event
     */
    public function archivedInvoice(InvoiceWasArchived $event)
    {
        if ($event->invoice->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->invoice,
            ACTIVITY_TYPE_ARCHIVE_INVOICE
        );
    }

    /**
     * @param InvoiceWasRestored $event
     */
    public function restoredInvoice(InvoiceWasRestored $event)
    {
        $invoice = $event->invoice;

        $this->activityRepo->create(
            $invoice,
            ACTIVITY_TYPE_RESTORE_INVOICE,
            $invoice->affectsBalance() && $event->fromDeleted ? $invoice->balance : 0,
            $invoice->affectsBalance() && $event->fromDeleted ? $invoice->getAmountPaid() : 0
        );
    }

    /**
     * @param InvoiceInvitationWasEmailed $event
     */
    public function emailedInvoice(InvoiceInvitationWasEmailed $event)
    {
        $this->activityRepo->create(
            $event->invitation->invoice,
            ACTIVITY_TYPE_EMAIL_INVOICE,
            false,
            false,
            $event->invitation,
            $event->notes
        );
    }

    /**
     * @param InvoiceInvitationWasViewed $event
     */
    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        $this->activityRepo->create(
            $event->invoice,
            ACTIVITY_TYPE_VIEW_INVOICE,
            false,
            false,
            $event->invitation
        );
    }

    /**
     * @param QuoteWasCreated $event
     */
    public function createdQuote(QuoteWasCreated $event)
    {
        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_CREATE_QUOTE
        );
    }

    /**
     * @param QuoteWasUpdated $event
     */
    public function updatedQuote(QuoteWasUpdated $event)
    {
        if (! $event->quote->isChanged()) {
            return;
        }

        $backupQuote = Invoice::with('invoice_items', 'client.account', 'client.contacts')
                            ->withTrashed()
                            ->find($event->quote->id);

        $activity = $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_UPDATE_QUOTE
        );

        $activity->json_backup = $backupQuote->hidePrivateFields()->toJSON();
        $activity->save();
    }

    /**
     * @param QuoteWasDeleted $event
     */
    public function deletedQuote(QuoteWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_DELETE_QUOTE
        );
    }

    /**
     * @param QuoteWasArchived $event
     */
    public function archivedQuote(QuoteWasArchived $event)
    {
        if ($event->quote->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_ARCHIVE_QUOTE
        );
    }

    /**
     * @param QuoteWasRestored $event
     */
    public function restoredQuote(QuoteWasRestored $event)
    {
        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_RESTORE_QUOTE
        );
    }

    /**
     * @param QuoteInvitationWasEmailed $event
     */
    public function emailedQuote(QuoteInvitationWasEmailed $event)
    {
        $this->activityRepo->create(
            $event->invitation->invoice,
            ACTIVITY_TYPE_EMAIL_QUOTE,
            false,
            false,
            $event->invitation,
            $event->notes
        );
    }

    /**
     * @param QuoteInvitationWasViewed $event
     */
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_VIEW_QUOTE,
            false,
            false,
            $event->invitation
        );
    }

    /**
     * @param QuoteInvitationWasApproved $event
     */
    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $this->activityRepo->create(
            $event->quote,
            ACTIVITY_TYPE_APPROVE_QUOTE,
            false,
            false,
            $event->invitation
        );
    }

    /**
     * @param CreditWasCreated $event
     */
    public function createdCredit(CreditWasCreated $event)
    {
        $this->activityRepo->create(
            $event->credit,
            ACTIVITY_TYPE_CREATE_CREDIT
        );
    }

    /**
     * @param CreditWasDeleted $event
     */
    public function deletedCredit(CreditWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->credit,
            ACTIVITY_TYPE_DELETE_CREDIT
        );
    }

    /**
     * @param CreditWasArchived $event
     */
    public function archivedCredit(CreditWasArchived $event)
    {
        if ($event->credit->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->credit,
            ACTIVITY_TYPE_ARCHIVE_CREDIT
        );
    }

    /**
     * @param CreditWasRestored $event
     */
    public function restoredCredit(CreditWasRestored $event)
    {
        $this->activityRepo->create(
            $event->credit,
            ACTIVITY_TYPE_RESTORE_CREDIT
        );
    }

    /**
     * @param PaymentWasCreated $event
     */
    public function createdPayment(PaymentWasCreated $event)
    {
        $this->activityRepo->create(
            $event->payment,
            ACTIVITY_TYPE_CREATE_PAYMENT,
            $event->payment->amount * -1,
            $event->payment->amount,
            false,
            \App::runningInConsole() ? 'auto_billed' : ''
        );
    }

    /**
     * @param PaymentWasDeleted $event
     */
    public function deletedPayment(PaymentWasDeleted $event)
    {
        $payment = $event->payment;

        $this->activityRepo->create(
            $payment,
            ACTIVITY_TYPE_DELETE_PAYMENT,
            $payment->isFailedOrVoided() ? 0 : $payment->getCompletedAmount(),
            $payment->isFailedOrVoided() ? 0 : $payment->getCompletedAmount() * -1
        );
    }

    /**
     * @param PaymentWasRefunded $event
     */
    public function refundedPayment(PaymentWasRefunded $event)
    {
        $payment = $event->payment;

        $this->activityRepo->create(
            $payment,
            ACTIVITY_TYPE_REFUNDED_PAYMENT,
            $event->refundAmount,
            $event->refundAmount * -1
        );
    }

    /**
     * @param PaymentWasVoided $event
     */
    public function voidedPayment(PaymentWasVoided $event)
    {
        $payment = $event->payment;

        $this->activityRepo->create(
            $payment,
            ACTIVITY_TYPE_VOIDED_PAYMENT,
            $payment->is_deleted ? 0 : $payment->getCompletedAmount(),
            $payment->is_deleted ? 0 : $payment->getCompletedAmount() * -1
        );
    }

    /**
     * @param PaymentFailed $event
     */
    public function failedPayment(PaymentFailed $event)
    {
        $payment = $event->payment;

        $this->activityRepo->create(
            $payment,
            ACTIVITY_TYPE_FAILED_PAYMENT,
            $payment->is_deleted ? 0 : $payment->getCompletedAmount(),
            $payment->is_deleted ? 0 : $payment->getCompletedAmount() * -1
        );
    }

    /**
     * @param PaymentWasArchived $event
     */
    public function archivedPayment(PaymentWasArchived $event)
    {
        if ($event->payment->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->payment,
            ACTIVITY_TYPE_ARCHIVE_PAYMENT
        );
    }

    /**
     * @param PaymentWasRestored $event
     */
    public function restoredPayment(PaymentWasRestored $event)
    {
        $payment = $event->payment;

        $this->activityRepo->create(
            $payment,
            ACTIVITY_TYPE_RESTORE_PAYMENT,
            $event->fromDeleted ? $payment->getCompletedAmount() * -1 : 0,
            $event->fromDeleted ? $payment->getCompletedAmount() : 0
        );
    }

    /**
     * Creates an activity when a task was created.
     *
     * @param TaskWasCreated $event
     */
    public function createdTask(TaskWasCreated $event)
    {
        $this->activityRepo->create(
            $event->task,
            ACTIVITY_TYPE_CREATE_TASK
        );
    }

    /**
     * Creates an activity when a task was updated.
     *
     * @param TaskWasUpdated $event
     */
    public function updatedTask(TaskWasUpdated $event)
    {
        if (! $event->task->isChanged()) {
            return;
        }

        $this->activityRepo->create(
            $event->task,
            ACTIVITY_TYPE_UPDATE_TASK
        );
    }

    public function archivedTask(TaskWasArchived $event)
    {
        if ($event->task->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->task,
            ACTIVITY_TYPE_ARCHIVE_TASK
        );
    }

    public function deletedTask(TaskWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->task,
            ACTIVITY_TYPE_DELETE_TASK
        );
    }

    public function restoredTask(TaskWasRestored $event)
    {
        $this->activityRepo->create(
            $event->task,
            ACTIVITY_TYPE_RESTORE_TASK
        );
    }

    public function createdExpense(ExpenseWasCreated $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_CREATE_EXPENSE
        );
    }

    public function updatedExpense(ExpenseWasUpdated $event)
    {
        if (! $event->expense->isChanged()) {
            return;
        }

        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_UPDATE_EXPENSE
        );
    }

    public function archivedExpense(ExpenseWasArchived $event)
    {
        if ($event->expense->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_ARCHIVE_EXPENSE
        );
    }

    public function deletedExpense(ExpenseWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_DELETE_EXPENSE
        );
    }

    public function restoredExpense(ExpenseWasRestored $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_RESTORE_EXPENSE
        );
    }
}

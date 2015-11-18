<?php namespace app\Listeners;

use App\Events\InvoiceWasEmailed;
use App\Events\InvoiceWasUpdated;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasDeleted;
use App\Events\PaymentWasRestored;
use App\Events\InvoiceInvitationWasViewed;

class InvoiceListener
{
    public function createdPayment(PaymentWasCreated $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount * -1;
        $partial = max(0, $invoice->partial - $payment->amount);

        $invoice->updateBalances($adjustment, $partial);
        $invoice->updatePaidStatus();
    }

    public function updatedInvoice(InvoiceWasUpdated $event)
    {
        $invoice = $event->invoice;
        $invoice->updatePaidStatus(false);
    }

    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }

    public function deletedPayment(PaymentWasDeleted $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    public function restoredPayment(PaymentWasRestored $event)
    {
        if ( ! $event->fromDeleted) {
            return;
        }

        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount * -1;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }
}

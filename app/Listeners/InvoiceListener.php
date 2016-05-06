<?php namespace app\Listeners;

use Utils;
use Auth;
use App\Events\InvoiceWasEmailed;
use App\Events\InvoiceWasUpdated;
use App\Events\InvoiceWasCreated;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasDeleted;
use App\Events\PaymentWasRefunded;
use App\Events\PaymentWasRestored;
use App\Events\PaymentWasVoided;
use App\Events\PaymentFailed;
use App\Events\InvoiceInvitationWasViewed;

class InvoiceListener
{
    public function createdInvoice(InvoiceWasCreated $event)
    {
        if (Utils::hasFeature(FEATURE_DIFFERENT_DESIGNS)) {
            return;
        }

        // Make sure the account has the same design set as the invoice does
        if (Auth::check()) {
            $invoice = $event->invoice;
            $account = Auth::user()->account;

            if ($invoice->invoice_design_id
                    && $account->invoice_design_id != $invoice->invoice_design_id) {
                $account->invoice_design_id = $invoice->invoice_design_id;
                $account->save();
            }
        }
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

    public function createdPayment(PaymentWasCreated $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount * -1;
        $partial = max(0, $invoice->partial - $payment->amount);

        $invoice->updateBalances($adjustment, $partial);
        $invoice->updatePaidStatus();
    }

    public function deletedPayment(PaymentWasDeleted $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount - $payment->refunded;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    public function refundedPayment(PaymentWasRefunded $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $event->refundAmount;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    public function voidedPayment(PaymentWasVoided $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    public function failedPayment(PaymentFailed $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount - $payment->refunded;

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
        $adjustment = ($payment->amount - $payment->refunded) * -1;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }
}

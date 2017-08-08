<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobExceptionOccurred;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\InvoiceWasCreated;
use App\Events\InvoiceWasUpdated;
use App\Events\PaymentFailed;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasDeleted;
use App\Events\PaymentWasRefunded;
use App\Events\PaymentWasRestored;
use App\Events\PaymentWasVoided;
use App\Models\Activity;
use Auth;
use Utils;

/**
 * Class InvoiceListener.
 */
class InvoiceListener
{
    /**
     * @param InvoiceWasCreated $event
     */
    public function createdInvoice(InvoiceWasCreated $event)
    {
        if (Utils::hasFeature(FEATURE_DIFFERENT_DESIGNS)) {
            return;
        }

        // Make sure the account has the same design set as the invoice does
        if (Auth::check()) {
            $invoice = $event->invoice;
            $account = Auth::user()->account;

            if ($invoice->invoice_design_id && $account->invoice_design_id != $invoice->invoice_design_id) {
                $account->invoice_design_id = $invoice->invoice_design_id;
                $account->save();
            }
        }
    }

    /**
     * @param InvoiceWasUpdated $event
     */
    public function updatedInvoice(InvoiceWasUpdated $event)
    {
        $invoice = $event->invoice;
        $invoice->updatePaidStatus(false);
    }

    /**
     * @param InvoiceInvitationWasViewed $event
     */
    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        $invitation = $event->invitation;
        $invitation->markViewed();
    }

    /**
     * @param PaymentWasCreated $event
     */
    public function createdPayment(PaymentWasCreated $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount * -1;
        $partial = max(0, $invoice->partial - $payment->amount);

        $invoice->updateBalances($adjustment, $partial);
        $invoice->updatePaidStatus();

        // store a backup of the invoice
        $activity = Activity::wherePaymentId($payment->id)
                        ->whereActivityTypeId(ACTIVITY_TYPE_CREATE_PAYMENT)
                        ->first();
        $activity->json_backup = $invoice->hidePrivateFields()->toJSON();
        $activity->save();
    }

    /**
     * @param PaymentWasDeleted $event
     */
    public function deletedPayment(PaymentWasDeleted $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->getCompletedAmount();

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    /**
     * @param PaymentWasRefunded $event
     */
    public function refundedPayment(PaymentWasRefunded $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $event->refundAmount;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    /**
     * @param PaymentWasVoided $event
     */
    public function voidedPayment(PaymentWasVoided $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    /**
     * @param PaymentFailed $event
     */
    public function failedPayment(PaymentFailed $event)
    {
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->getCompletedAmount();

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    /**
     * @param PaymentWasRestored $event
     */
    public function restoredPayment(PaymentWasRestored $event)
    {
        if (! $event->fromDeleted) {
            return;
        }

        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->getCompletedAmount() * -1;

        $invoice->updateBalances($adjustment);
        $invoice->updatePaidStatus();
    }

    public function jobFailed(JobExceptionOccurred $exception)
    {
        /*
        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw(print_r($exception->data, true), function ($message) use ($errorEmail) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject('Job failed');
            });
        }
        */

        Utils::logError($exception->exception);
    }
}

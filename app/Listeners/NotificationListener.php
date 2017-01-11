<?php namespace App\Listeners;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteInvitationWasApproved;
use App\Events\PaymentWasCreated;
use App\Jobs\SendPaymentEmail;
use App\Jobs\SendNotificationEmail;
use App\Jobs\SendPushNotification;

/**
 * Class NotificationListener
 */
class NotificationListener
{
    /**
     * @param $invoice
     * @param $type
     * @param null $payment
     */
    private function sendEmails($invoice, $type, $payment = null)
    {
        foreach ($invoice->account->users as $user)
        {
            if ($user->{"notify_{$type}"})
            {
                dispatch(new SendNotificationEmail($user, $invoice, $type, $payment));
            }
        }
    }

    /**
     * @param InvoiceWasEmailed $event
     */
    public function emailedInvoice(InvoiceWasEmailed $event)
    {
        $this->sendEmails($event->invoice, 'sent');
        dispatch(new SendPushNotification($event->invoice, 'sent'));
    }

    /**
     * @param QuoteWasEmailed $event
     */
    public function emailedQuote(QuoteWasEmailed $event)
    {
        $this->sendEmails($event->quote, 'sent');
        dispatch(new SendPushNotification($event->quote, 'sent'));
    }

    /**
     * @param InvoiceInvitationWasViewed $event
     */
    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        if ( ! floatval($event->invoice->balance)) {
            return;
        }

        $this->sendEmails($event->invoice, 'viewed');
        dispatch(new SendPushNotification($event->invoice, 'viewed'));
    }

    /**
     * @param QuoteInvitationWasViewed $event
     */
    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        if ($event->quote->quote_invoice_id) {
            return;
        }

        $this->sendEmails($event->quote, 'viewed');
        dispatch(new SendPushNotification($event->quote, 'viewed'));
    }

    /**
     * @param QuoteInvitationWasApproved $event
     */
    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $this->sendEmails($event->quote, 'approved');
        dispatch(new SendPushNotification($event->quote, 'approved'));
    }

    /**
     * @param PaymentWasCreated $event
     */
    public function createdPayment(PaymentWasCreated $event)
    {
        // only send emails for online payments
        if ( ! $event->payment->account_gateway_id) {
            return;
        }

        $this->sendEmails($event->payment->invoice, 'paid', $event->payment);
        dispatch(new SendPaymentEmail($event->payment));
        dispatch(new SendPushNotification($event->payment->invoice, 'paid'));
    }

}

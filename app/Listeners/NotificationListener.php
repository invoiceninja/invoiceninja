<?php namespace app\Listeners;

use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteInvitationWasApproved;
use App\Events\PaymentWasCreated;

class NotificationListener
{
    protected $userMailer;
    protected $contactMailer;

    public function __construct(UserMailer $userMailer, ContactMailer $contactMailer)
    {
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
    }   

    private function sendEmails($invoice, $type, $payment = null)
    {
        foreach ($invoice->account->users as $user)
        {
            if ($user->{"notify_{$type}"})
            {
                $this->userMailer->sendNotification($user, $invoice, $type, $payment);
            }
        }
    }

    public function emailedInvoice(InvoiceWasEmailed $event)
    {
        $this->sendEmails($event->invoice, 'sent');
    }

    public function emailedQuote(QuoteWasEmailed $event)
    {
        $this->sendEmails($event->quote, 'sent');
    }

    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        $this->sendEmails($event->invoice, 'viewed');
    }

    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $this->sendEmails($event->quote, 'viewed');
    }

    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $this->sendEmails($event->quote, 'approved');
    }

    public function createdPayment(PaymentWasCreated $event)
    {
        // only send emails for online payments
        if ( ! $event->payment->account_gateway_id) {
            return;
        }

        $this->contactMailer->sendPaymentConfirmation($event->payment);
        $this->sendEmails($event->payment->invoice, 'paid', $event->payment);
    }

}
<?php namespace App\Listeners;

use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteInvitationWasApproved;
use App\Events\PaymentWasCreated;
use App\Ninja\Notifications;
use App\Services\PushService;

class NotificationListener
{
    protected $userMailer;
    protected $contactMailer;
    protected $pushService;

    public function __construct(UserMailer $userMailer, ContactMailer $contactMailer, PushService $pushService)
    {
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
        $this->pushService = $pushService;
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
        $this->pushService->sendNotification($event->invoice, 'sent');
    }

    public function emailedQuote(QuoteWasEmailed $event)
    {
        $this->sendEmails($event->quote, 'sent');
        $this->pushService->sendNotification($event->quote, 'sent');
    }

    public function viewedInvoice(InvoiceInvitationWasViewed $event)
    {
        $this->sendEmails($event->invoice, 'viewed');
        $this->pushService->sendNotification($event->invoice, 'viewed');
    }

    public function viewedQuote(QuoteInvitationWasViewed $event)
    {
        $this->sendEmails($event->quote, 'viewed');
        $this->pushService->sendNotification($event->quote, 'viewed');
    }

    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $this->sendEmails($event->quote, 'approved');
        $this->pushService->sendNotification($event->quote, 'approved');
    }

    public function createdPayment(PaymentWasCreated $event)
    {
        // only send emails for online payments
        if ( ! $event->payment->account_gateway_id) {
            return;
        }

        $this->contactMailer->sendPaymentConfirmation($event->payment);
        $this->sendEmails($event->payment->invoice, 'paid', $event->payment);

        $this->pushService->sendNotification($event->payment->invoice, 'paid');
    }

}
<?php namespace App\Listeners;

use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;
use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Events\QuoteInvitationWasApproved;
use App\Events\PaymentWasCreated;
use App\Services\PushService;
use App\Jobs\SendNotificationEmail;

/**
 * Class NotificationListener
 */
class NotificationListener
{
    /**
     * @var UserMailer
     */
    protected $userMailer;
    /**
     * @var ContactMailer
     */
    protected $contactMailer;
    /**
     * @var PushService
     */
    protected $pushService;

    /**
     * NotificationListener constructor.
     * @param UserMailer $userMailer
     * @param ContactMailer $contactMailer
     * @param PushService $pushService
     */
    public function __construct(UserMailer $userMailer, ContactMailer $contactMailer, PushService $pushService)
    {
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
        $this->pushService = $pushService;
    }

    /**
     * @param $invoice
     * @param $type
     * @param null $payment
     */
    private function sendEmails($invoice, $type, $payment = null, $notes = false)
    {
        foreach ($invoice->account->users as $user)
        {
            if ($user->{"notify_{$type}"})
            {
                dispatch(new SendNotificationEmail($user, $invoice, $type, $payment, $notes));
            }
        }
    }

    /**
     * @param InvoiceWasEmailed $event
     */
    public function emailedInvoice(InvoiceWasEmailed $event)
    {
        $this->sendEmails($event->invoice, 'sent', null, $event->notes);
        $this->pushService->sendNotification($event->invoice, 'sent');
    }

    /**
     * @param QuoteWasEmailed $event
     */
    public function emailedQuote(QuoteWasEmailed $event)
    {
        $this->sendEmails($event->quote, 'sent', null, $event->notes);
        $this->pushService->sendNotification($event->quote, 'sent');
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
        $this->pushService->sendNotification($event->invoice, 'viewed');
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
        $this->pushService->sendNotification($event->quote, 'viewed');
    }

    /**
     * @param QuoteInvitationWasApproved $event
     */
    public function approvedQuote(QuoteInvitationWasApproved $event)
    {
        $this->sendEmails($event->quote, 'approved');
        $this->pushService->sendNotification($event->quote, 'approved');
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

        $this->contactMailer->sendPaymentConfirmation($event->payment);
        $this->sendEmails($event->payment->invoice, 'paid', $event->payment);

        $this->pushService->sendNotification($event->payment->invoice, 'paid');
    }

}

<?php

namespace App\Handlers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Mailers\ContactMailer;

class InvoiceEventHandler
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
     * InvoiceEventHandler constructor.
     *
     * @param UserMailer $userMailer
     * @param ContactMailer $contactMailer
     */
    public function __construct(UserMailer $userMailer, ContactMailer $contactMailer)
    {
        $this->userMailer = $userMailer;
        $this->contactMailer = $contactMailer;
    }

    /**
     * @param $events
     */
    public function subscribe($events)
    {
        $events->listen('invoice.sent', 'InvoiceEventHandler@onSent');
        $events->listen('invoice.viewed', 'InvoiceEventHandler@onViewed');
        $events->listen('invoice.paid', 'InvoiceEventHandler@onPaid');
    }

    /**
     * @param Invoice $invoice
     */
    public function onSent(Invoice $invoice)
    {
        $this->sendNotifications($invoice, 'sent');
    }

    /**
     * @param Invoice $invoice
     */
    public function onViewed(Invoice $invoice)
    {
        $this->sendNotifications($invoice, 'viewed');
    }

    /**
     * @param Payment $payment
     */
    public function onPaid(Payment $payment)
    {
        $this->contactMailer->sendPaymentConfirmation($payment);

        $this->sendNotifications($payment->invoice, 'paid', $payment);
    }

    /**
     * @param Invoice $invoice
     * @param $type
     * @param null $payment
     */
    private function sendNotifications(Invoice $invoice, $type, $payment = null)
    {
        foreach ($invoice->account->users as $user) {
            if ($user->{'notify_' . $type}) {
                $this->userMailer->sendNotification($user, $invoice, $type, $payment);
            }
        }
    }
}
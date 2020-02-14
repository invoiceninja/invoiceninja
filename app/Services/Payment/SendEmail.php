<?php

namespace App\Services\Payment;

use App\Helpers\Email\PaymentEmail;
use App\Jobs\Payment\EmailPayment;

class SendEmail
{
    public $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    /**
     * Builds the correct template to send
     * @param string $reminder_template The template name ie reminder1
     * @return array
     */
    public function sendEmail($contact = null): array
    {
        $email_builder = (new PaymentEmail())->build($this->payment, $contact);

        $this->payment->client->contacts->each(function ($contact) use ($email_builder) {
            if ($contact->send_invoice && $contact->email) {
                EmailPayment::dispatchNow($this->payment, $email_builder, $contact);
            }
        });
    }
}

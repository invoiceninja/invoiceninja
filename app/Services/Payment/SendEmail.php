<?php

namespace App\Services\Payment;

use App\Helpers\Email\BuildEmail;
use App\Jobs\Payment\EmailPayment;
use App\Traits\FormatEmail;

class SendEmail
{
    use FormatEmail;

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
         $this->payment->client->contacts->each(function ($contact) use ($emailBuilder) {

        //Need to determine which email template we are producing
        EmailPayment::dispatchNow((new BuildEmail())->buildPaymentEmail($this->payment, $contact));
    }
}

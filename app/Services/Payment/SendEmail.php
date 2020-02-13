<?php

namespace App\Services\Payment;

use App\Jobs\Payment\EmailPayment;
use App\Jobs\Quote\EmailQuote;
use App\Quote;
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

        //Need to determine which email template we are producing
        $message_array = $this->generateTemplateData($contact);
        EmailPayment::dispatchNow($message_array);
    }

    private function generateTemplateData($contact) :array
    {
        $data = [];

        $client = $this->payment->customer;

        $body_template = $client->getSetting('payment_message');

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {

            $body_template = trans('texts.payment_message',
                ['amount' => $this->payment->amount, 'account' => $this->account->present()->name()], null,
                $this->customer->locale());
        }

        $subject_template = $client->getSetting('payment_subject');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans('texts.payment_subject', ['number'=>$this->payment->number,'account'=>$this->account->present()->name()], null, $this->customer->locale());
        }

        $data['body'] = $this->parseTemplate($body_template, true, $contact);
        $data['subject'] = $this->parseTemplate($subject_template, false, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $data['files'][] = $this->pdf_file_path();
        }

        return $data;
    }
}

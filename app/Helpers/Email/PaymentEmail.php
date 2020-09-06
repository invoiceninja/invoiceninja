<?php
/**
 * Created by PhpStorm.
 * User: michael.hampton
 * Date: 14/02/2020
 * Time: 19:51.
 */

namespace App\Helpers\Email;

use App\Models\Payment;

class PaymentEmail extends EmailBuilder
{
    public function build(Payment $payment, $contact = null)
    {
        $client = $payment->client;

        $body_template = $client->getSetting('email_template_payment');

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.payment_message',
                ['amount' => $payment->amount, 'company' => $payment->company->present()->name()],
                null,
                $this->client->locale()
            );
        }

        $subject_template = $client->getSetting('email_subject_payment');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans(
                'texts.payment_subject',
                ['number' => $payment->number, 'company' => $payment->company->present()->name()],
                null,
                $payment->client->locale()
            );
        }

        $this->setTemplate($payment->client->getSetting('email_style'))
            ->setSubject($subject_template)
            ->setBody($body_template);

        return $this;
    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\Admin;

use App\Models\User;
use App\Utils\Number;

class PaymentFailureObject
{
    public $client;

    public $message;

    public $company;

    public $amount;

    public function __construct($client, $message, $amount, $company)
    {
        $this->client = $client;
        $this->message = $message;
        $this->amount = $amount;
        $this->company = $company;
    }

    public function build()
    {
        $mail_obj = new \stdClass;
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }

    private function getAmount()
    {
        return Number::formatMoney($this->amount, $this->client);
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.payment_failed_subject',
                ['client' => $this->payment->client->present()->name()]
            );
    }

    private function getData()
    {
        $signature = $this->client->getSetting('email_signature');

        $data = [
            'title' => ctrans(
                'texts.payment_failed_subject',
                ['client' => $this->client->present()->name()]
            ),
            'message' => ctrans(
                'texts.notification_payment_paid',
                ['amount' => $this->getAmount(),
                'client' => $this->client->present()->name(),
                'message' => $this->message,
            ]
            ),
            'signature' => $signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->client->getMergedSettings(),
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];

        return $data;
    }
}

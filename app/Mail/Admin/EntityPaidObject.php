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

class EntityPaidObject
{
    public $invitation;

    public $entity;

    public $contact;

    public $company;

    public $settings;

    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->company = $payment->company;
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
        return Number::formatMoney($this->payment->amount, $this->payment->client);
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.notification_payment_paid_subject',
                ['client' => $this->payment->client->present()->name()]
            );
    }

    private function getData()
    {
        $settings = $this->payment->client->getMergedSettings();

        $amount = Number::formatMoney($this->payment->amount, $this->payment->client);

        $invoice_texts = ctrans('texts.invoice_number_short');

        foreach ($this->payment->invoices as $invoice) {
            $invoice_texts .= $invoice->number.',';
        }

        $invoice_texts = substr($invoice_texts, 0, -1);

        $data = [
            'title' => ctrans(
                'texts.notification_payment_paid_subject',
                ['client' => $this->payment->client->present()->name()]
            ),
            'message' => ctrans(
                'texts.notification_payment_paid',
                ['amount' => $amount,
                'client' => $this->payment->client->present()->name(),
                'invoice' => $invoice_texts,
            ]
            ),
            'url' => config('ninja.app_url'),
            // 'url' => config('ninja.app_url') . '/payments/' . $this->payment->hashed_id, //because we have no deep linking we cannot use this
            'button' => ctrans('texts.view_payment'),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
        ];

        return $data;
    }
}

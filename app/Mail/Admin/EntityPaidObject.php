<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Mail\Engine\PaymentEmailEngine;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

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
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $mail_obj = new stdClass;
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

        $signature = $this->generateSignature($settings);

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
            'content' => ctrans(
                'texts.notification_payment_paid',
                ['amount' => $amount,
                    'client' => $this->payment->client->present()->name(),
                    'invoice' => $invoice_texts,
                ]
            ),
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.view_payment'),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];

        return $data;
    }

    private function generateSignature($settings)
    {
        $html_variables = (new PaymentEmailEngine($this->payment, $this->payment->client->primary_contact()->first()))->makeValues();

        $signature = str_replace(array_keys($html_variables), array_values($html_variables), $settings->email_signature);

        return $signature;
    }
}

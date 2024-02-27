<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Mail\Engine\PaymentEmailEngine;
use App\Models\Payment;
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

    public function __construct(public Payment $payment, protected bool $use_react_url)
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

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';

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
        $content = ctrans(
            'texts.notification_payment_paid',
            ['amount' => $amount,
                    'client' => $this->payment->client->present()->name(),
                    'invoice' => $invoice_texts,
                ]
        );

        $data = [
            'title' => ctrans(
                'texts.notification_payment_paid_subject',
                ['client' => $this->payment->client->present()->name()]
            ),
            'content' => $content,
            'url' => $this->payment->portalUrl($this->use_react_url),
            'button' => ctrans('texts.view_payment'),
            'signature' => $settings->email_signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'text_body' => $content,
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        return $data;
    }

}

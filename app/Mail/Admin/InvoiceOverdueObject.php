<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Facades\App;
use stdClass;

class InvoiceOverdueObject
{
    public function __construct(public Invoice $invoice, public Company $company, public bool $use_react_url)
    {
    }

    public function build()
    {
        MultiDB::setDb($this->company->db);

        if (! $this->invoice) {
            return;
        }

        App::forgetInstance('translator');
        /* Init a new copy of the translator */
        $t = app('translator');
        /* Set the locale */
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
        return Number::formatMoney($this->invoice->balance, $this->invoice->client);
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.notification_invoice_overdue_subject',
                [
                    'client' => $this->invoice->client->present()->name(),
                    'invoice' => $this->invoice->number,
                ]
            );
    }

    private function getData()
    {
        $settings = $this->invoice->client->getMergedSettings();
        $content = ctrans(
            'texts.notification_invoice_overdue',
            [
                    'amount' => $this->getAmount(),
                    'client' => $this->invoice->client->present()->name(),
                    'invoice' => $this->invoice->number,
                ]
        );

        $data = [
            'title' => $this->getSubject(),
            'content' => $content,
            'url' => $this->invoice->invitations->first()->getAdminLink($this->use_react_url),
            'button' => $this->use_react_url ? ctrans('texts.view_invoice') : ctrans('texts.login'),
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


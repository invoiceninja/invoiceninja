<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use stdClass;

/**
 * Tells the user who started an in-person card payment that it was declined while nobody was watching.
 *
 * Plain strings only: never the charge, its decline code or anything about the cardholder.
 */
class TerminalDeclinedObject
{
    public function __construct(public Company $company, public string $invoice_number, public string $client, public string $amount, public string $url)
    {
    }

    public function build(): stdClass
    {
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->company->getLocale());
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $subject = ctrans('texts.terminal_declined_subject', ['invoice' => $this->invoice_number, 'client' => $this->client]);
        $content = ctrans('texts.terminal_declined_body', ['amount' => $this->amount, 'invoice' => $this->invoice_number, 'client' => $this->client]);

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->amount;
        $mail_obj->subject = $subject;
        $mail_obj->data = [
            'title' => $subject,
            'content' => e($content),
            'url' => $this->url,
            'button' => ctrans('texts.view_invoice'),
            'logo' => $this->company->present()->logo(),
            'settings' => $this->company->settings,
            'whitelabel' => $this->company->account->isPaid(),
            'text_body' => $content,
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';

        return $mail_obj;
    }
}

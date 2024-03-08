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

namespace App\Mail\RecurringInvoice;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ClientContactRequestCancellationObject
{
    public Company $company;

    public function __construct(public RecurringInvoice $recurring_invoice, public ClientContact $client_contact, private bool $gateway_refund_attempted)
    {
    }

    public function build()
    {
        $this->company = $this->recurring_invoice->company;

        App::forgetInstance('translator');
        App::setLocale($this->company->getLocale());

        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));
        $content = ctrans('texts.recurring_cancellation_request_body', ['contact' => $this->client_contact->present()->name(), 'client' => $this->client_contact->client->present()->name(), 'invoice' => $this->recurring_invoice->number]);

        if ($this->gateway_refund_attempted) {
            $content .= "\n\n" . ctrans('texts.status') . " : " . ctrans('texts.payment_status_6');
        }

        $data = [
            'title' => ctrans('texts.recurring_cancellation_request', ['contact' => $this->client_contact->present()->name()]),
            'content' => $content,
            'url' => config('ninja.web_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        $mail_obj = new \stdClass();
        $mail_obj->subject = ctrans('texts.recurring_cancellation_request', ['contact' => $this->client_contact->present()->name()]);
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.admin.generic_text';

        return $mail_obj;
    }
}

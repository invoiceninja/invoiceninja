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

namespace App\Mail\RecurringInvoice;

use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ClientContactRequestCancellationObject
{
    public $recurring_invoice;

    public $client_contact;

    private $company;

    public function __construct($recurring_invoice, $client_contact)
    {
        $this->recurring_invoice = $recurring_invoice;
        $this->client_contact = $client_contact;
        $this->company = $recurring_invoice->company;
    }

    public function build()
    {
        App::forgetInstance('translator');
        App::setLocale($this->company->getLocale());

        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $data = [
            'title' => ctrans('texts.recurring_cancellation_request', ['contact' => $this->client_contact->present()->name()]),
            'content' => ctrans('texts.recurring_cancellation_request_body', ['contact' => $this->client_contact->present()->name(), 'client' => $this->client_contact->client->present()->name(), 'invoice' => $this->recurring_invoice->number]),
            'url' => config('ninja.web_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
        ];

        $mail_obj = new \stdClass;
        $mail_obj->subject = ctrans('texts.recurring_cancellation_request', ['contact' => $this->client_contact->present()->name()]);
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.admin.generic_text';

        return $mail_obj;
    }
}

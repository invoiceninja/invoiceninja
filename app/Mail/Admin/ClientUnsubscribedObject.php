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

use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ClientUnsubscribedObject
{
    public function __construct(
        public ClientContact $contact,
        public Company $company
    ) {
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

        $data = [
            'title' => ctrans('texts.client_unsubscribed'),
            'message' => ctrans('texts.client_unsubscribed_help', ['client' => $this->contact->present()->name()]),
            'url' => $this->contact->client->portalUrl(false),
            'button' => ctrans('texts.view_client'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
        ];

        $mail_obj = new \stdClass();
        $mail_obj->subject = ctrans('texts.client_unsubscribed');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }
}

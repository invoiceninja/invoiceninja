<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\VendorContact;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ClientUnsubscribedObject
{
    public function __construct(
        public ClientContact | VendorContact$contact,
        public Company $company,
        private bool $use_react_link = false
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
            'content' => ctrans('texts.client_unsubscribed_help', ['client' => $this->contact->present()->name()]),
            'url' => $this->contact->getAdminLink($this->use_react_link),
            'button' => ctrans('texts.view_client'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
            'text_body' => "\n\n".ctrans('texts.client_unsubscribed_help', ['client' => $this->contact->present()->name()])."\n\n",
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        $mail_obj = new \stdClass();
        $mail_obj->subject = ctrans('texts.client_unsubscribed');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';

        return $mail_obj;
    }
}

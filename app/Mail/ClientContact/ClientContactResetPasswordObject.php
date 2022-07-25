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

namespace App\Mail\ClientContact;

use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ClientContactResetPasswordObject
{
    public $client_contact;

    public $token;

    private $company;

    public function __construct($token, $client_contact)
    {
        $this->token = $token;
        $this->client_contact = $client_contact;
        $this->company = $client_contact->company;
    }

    public function build()
    {
        $settings = $this->client_contact->client->getMergedSettings();
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->client_contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($settings));

        $data = [
            'title' => ctrans('texts.your_password_reset_link'),
            'content' => ctrans('texts.reset_password'),
            'url' => route('client.password.reset', ['token' => $this->token, 'email' => $this->client_contact->email]),
            'button' => ctrans('texts.reset'),
            'signature' => $settings->email_signature,
            'settings' => $settings,
            'company' => $this->company,
            'logo' => $this->company->present()->logo(),
        ];

        $email_from_name = config('mail.from.name');

        if (property_exists($settings, 'email_from_name') && strlen($settings->email_from_name) > 1) {
            $email_from_name = $settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $mail_obj = new \stdClass;
        $mail_obj->subject = ctrans('texts.your_password_reset_link');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.client.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->from_name = $email_from_name;

        return $mail_obj;
    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Mail\ClientContact;

class ClientContactResetPasswordObject
{

    public $client_contact;

    public $token;

    private $company;
    /**
     *
     */
    public function __construct($token, $client_contact)
    {
        $this->token = $token;
        $this->client_contact = $client_contact;
        $this->company = $client_contact->company;
    }

    public function build()
    {

        $data = [
            'title' => ctrans('texts.your_password_reset_link'),
            'message' => ctrans('texts.reset_password'),
            'url' => route('client.password.reset', ['token' => $this->token, 'email' => $this->client_contact->email]),
            'button' => ctrans('texts.reset'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
        ];


        $mail_obj = new \stdClass;
        $mail_obj->subject = ctrans('texts.your_password_reset_link');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }
}
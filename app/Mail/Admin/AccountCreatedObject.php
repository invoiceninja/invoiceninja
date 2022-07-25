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

use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class AccountCreatedObject
{
    public $user;

    public $company;

    public function __construct($user, $company)
    {
        $this->user = $user;
        $this->company = $company;
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
            'title' => ctrans('texts.new_signup'),
            'message' => ctrans('texts.new_signup_text', ['user' => $this->user->present()->name(), 'email' => $this->user->email, 'ip' => $this->user->ip]),
            'url' => config('ninja.web_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
        ];

        $mail_obj = new \stdClass;
        $mail_obj->subject = ctrans('texts.new_signup');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }
}

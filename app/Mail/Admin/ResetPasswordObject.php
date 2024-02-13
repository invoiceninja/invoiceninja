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

use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class ResetPasswordObject
{
    public function __construct(private string $token, protected User $user, protected Company $company, protected bool $is_react)
    {
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
            'title' => ctrans('texts.your_password_reset_link'),
            'content' => ctrans('texts.reset_password'),
            'url' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email, 'react' => $this->is_react ? 'true' : 'false']),
            'button' => ctrans('texts.reset'),
            'signature' => $this->company->settings->email_signature,
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
            'text_body' => ctrans('texts.reset_password'),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        $mail_obj = new \stdClass();
        $mail_obj->subject = ctrans('texts.your_password_reset_link');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';

        return $mail_obj;
    }
}

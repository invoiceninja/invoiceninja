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

namespace App\Mail\Admin;

use App\Utils\Traits\MakesHash;

class VerifyUserObject
{

    use MakesHash;

    public $user;

    public $company;

    /**
     *
     */
    public function __construct($user, $company)
    {
        $this->user = $user;
    	$this->company = $company;
    }

    public function build()
    {
    	$this->user->confirmation_code = $this->createDbHash($this->company->db);
    	$this->user->save();

        $data = [
            'title' => ctrans('texts.confirmation_subject'),
            'message' => ctrans('texts.confirmation_message'),
            'url' => url("/user/confirm/{$this->user->confirmation_code}"),
            'button' => ctrans('texts.button_confirmation_message'),
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
			'signature' => $this->company->settings->email_signature,
        ];


        $mail_obj = new \stdClass;
        $mail_obj->subject = ctrans('texts.confirmation_subject');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;

        return $mail_obj;
    }
}
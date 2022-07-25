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

namespace App\Http\Requests\Setup;

use App\Http\Requests\Request;

class StoreSetupRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            /*System*/
            'url'              => 'required',
            /*Mail driver*/
            'mail_driver'      => 'required',
            'encryption'       => 'required_unless:mail_driver,log',
            'mail_host'        => 'required_unless:mail_driver,log',
            'mail_username'    => 'required_unless:mail_driver,log',
            'mail_name'        => 'required_unless:mail_driver,log',
            'mail_address'     => 'required_unless:mail_driver,log',
            'mail_password'    => 'required_unless:mail_driver,log',
            /*user registration*/
            'privacy_policy'   => 'required',
            'terms_of_service' => 'required',
            'first_name'       => 'required',
            'last_name'        => 'required',
            'email'            => 'required|email:rfc,dns',
            'password'         => 'required',
        ];

        if (! config('ninja.preconfigured_install')) {
            $rules = array_merge($rules, [
                /*Database*/
                'db_host'     => 'required',
                'db_database' => 'required',
                'db_username' => 'required',
                'db_password' => '',
            ]);
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['user_agent'] = request()->server('HTTP_USER_AGENT');

        $this->replace($input);
    }
}

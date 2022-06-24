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

namespace App\Http\Requests\Login;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Account\BlackListRule;
use App\Http\ValidationRules\Account\EmailBlackListRule;
use App\Utils\Ninja;

class LoginRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (Ninja::isHosted()) {
            $email_rules = ['required', new BlackListRule, new EmailBlackListRule];
        } else {
            $email_rules = 'required';
        }

        return [
            'email' => $email_rules,
            'password' => 'required|max:1000',
        ];
    }

    // public function prepareForValidation()
    // {
    //     $input = $this->all();

    //     // if(base64_decode(base64_encode($input['password'])) === $input['password'])
    //     //     $input['password'] = base64_decode($input['password']);

    //     // nlog($input['password']);

    //     $this->replace($input);
    // }
}

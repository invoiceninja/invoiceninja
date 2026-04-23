<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
    protected $stopOnFirstFailure = true;

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
            $email_rules = ['required', 'bail',new EmailBlackListRule()];
        } else {
            $email_rules = 'required|bail';
        }

        return [
            'email' => $email_rules,
            'password' => 'required_without:passkey_challenge_token|max:1000',
            'passkey_challenge_token' => 'nullable|string|max:255',
            'passkey_authentication' => 'nullable|array',
            'passkey_authentication.id' => 'required_with:passkey_challenge_token|string',
            'passkey_authentication.clientDataJSON' => 'required_with:passkey_challenge_token|string',
            'passkey_authentication.authenticatorData' => 'required_with:passkey_challenge_token|string',
            'passkey_authentication.signature' => 'required_with:passkey_challenge_token|string',
        ];
    }

}

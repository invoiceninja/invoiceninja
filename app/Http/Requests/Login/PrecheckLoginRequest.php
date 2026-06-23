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
use App\Http\ValidationRules\Account\EmailBlackListRule;
use App\Utils\Ninja;

class PrecheckLoginRequest extends Request
{
    protected $stopOnFirstFailure = true;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        if (Ninja::isHosted()) {
            $email_rules = ['required', 'bail', new EmailBlackListRule()];
        } else {
            $email_rules = 'required|bail';
        }

        return [
            'email' => $email_rules,
        ];
    }
}

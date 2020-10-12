<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Account;

use App\Http\Requests\Request;
use App\Http\ValidationRules\NewUniqueUserRule;

class CreateAccountRequest extends Request
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
        return [
            //'email' => 'required|string|email|max:100',
            'first_name'        => 'string|max:100',
            'last_name'         =>  'string:max:100',
            'password'          => 'required|string|min:6',
            'email'             => 'bail|required|email:rfc,dns',
            'email'             => new NewUniqueUserRule(),
            'privacy_policy'    => 'required',
            'terms_of_service'  => 'required',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $input['user_agent'] = request()->server('HTTP_USER_AGENT');

        $this->replace($input);
    }
}

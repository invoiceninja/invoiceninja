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

namespace App\Http\Requests\Smtp;

use App\Http\Requests\Request;

class CheckSmtpRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        return $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
        ];
    }

    public function prepareForValidation()
    {   
        $input = $this->input();

        if(isset($input['smtp_username']) && $input['smtp_username'] == '********')
            unset($input['smtp_username']);

        if(isset($input['smtp_password'])&& $input['smtp_password'] == '********')
            unset($input['smtp_password']);

        $this->replace($input);
    }
}

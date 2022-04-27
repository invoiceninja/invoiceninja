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
        return [
            'email' => 'required',
            'password' => 'required|max:1000',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        // if(base64_decode(base64_encode($input['password'])) === $input['password'])
        //     $input['password'] = base64_decode($input['password']);

        // nlog($input['password']);
        
        $this->replace($input);
    }
}

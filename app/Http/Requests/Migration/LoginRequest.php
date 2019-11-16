<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
        $rules =  [
            'email_address' => ['required', 'email'],
            'password' => ['required'],
        ];

        if(request()->_type == 'self_hosted') {
            $rules['x_api_secret'] = ['required'];
            $rules['api_endpoint'] = ['required'];
        }

        return $rules;
    }
}

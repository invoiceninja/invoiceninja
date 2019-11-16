<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        $rules = [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email_address' => ['required', 'email'],
            'password' => ['required', 'min:6'],
            'tos' => ['required'],
            'privacy_policy' => ['required'],
        ];

        if (request()->_type == 'self_hosted') {
            $rules['x_api_secret'] = ['required'];
            $rules['api_endpoint'] = ['required'];
        }

        return $rules;
    }
}

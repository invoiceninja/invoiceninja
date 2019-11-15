<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class SelfLoginRequest extends FormRequest
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
            'email_address' => ['required', 'email'],
            'password' => ['required'],
            'x_api_secret' => ['required'],
            'api_endpoint' => ['required', 'url'],
        ];
    }
}

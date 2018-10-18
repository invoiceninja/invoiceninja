<?php

namespace App\Http\Requests;

use App\Http\ValidationRules\UniqueUserRule;
use Illuminate\Support\Facades\Auth;

class SignupRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return ! auth()->user();
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
            'first_name'        => 'required|string|max:100',
            'last_name'         =>  'required|string:max:100',
            'password'          => 'required|string|min:6',
            'email'             => new UniqueUserRule(),
            'privacy_policy'    => 'required',
            'terms_of_service'  => 'required'
        ];
    }
}
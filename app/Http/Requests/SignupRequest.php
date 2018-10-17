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
        return ! Auth::user();
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
            'password' => 'required|string|min:6',
            'email' => new UniqueUserRule(),
        ];
    }
}
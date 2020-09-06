<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class CheckMailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; /* Return something that will check if setup has been completed, like Ninja::hasCompletedSetup() */
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'driver' => ['required', 'in:smtp,mail,sendmail'],
            'from_name' => ['required'],
            'from_address' => ['required'],
            'username' => ['required'],
            'host' => ['required'],
            'port' => ['required'],
            'encryption' => ['required'],
            'password' => ['required'],
        ];
    }
}

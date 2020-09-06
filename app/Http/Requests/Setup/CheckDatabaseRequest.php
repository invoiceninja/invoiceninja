<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class CheckDatabaseRequest extends FormRequest
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
            'host' => ['required'],
            'database' => ['required'],
            'username' => ['required'],
        ];
    }
}

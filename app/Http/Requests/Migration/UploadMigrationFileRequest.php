<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class UploadMigrationFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'migration' => [],
        ];

        /* We'll skip mime validation while running tests. */
        if (app()->environment() !== 'testing') {
            $rules['migration'] = ['required', 'file', 'mimes:zip'];
        }

        return $rules;
    }
}

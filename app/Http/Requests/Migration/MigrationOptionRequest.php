<?php

namespace App\Http\Requests\Migration;

use Illuminate\Foundation\Http\FormRequest;

class MigrationOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!in_array(request()->migration_option, ['purge_without_settings', 'purge_with_settings'])) {
            false;
        }

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
            'companies' => ['required'],
        ];
    }
}

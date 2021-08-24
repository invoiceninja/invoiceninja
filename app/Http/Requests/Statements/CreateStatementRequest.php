<?php

namespace App\Http\Requests\Statements;

use Illuminate\Foundation\Http\FormRequest;

class CreateStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
        return [
            'start_date' => ['required'],
            'end_date' => ['required'],
        ];
    }
}

<?php

namespace App\Http\Requests\ClientPortal\Statements;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class ShowStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
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
            //
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'show_payments_table' => $this->has('show_payments_table') ? \boolval($this->show_payments_table) : false,
            'show_aging_table' => $this->has('show_aging_table') ? \boolval($this->show_aging_table) : false,
        ]);
    }

    public function client(): Client
    {
        return auth()->guard('contact')->user()->client;
    }
}

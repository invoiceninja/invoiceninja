<?php

namespace App\Http\Requests\ClientPortal;

use Illuminate\Foundation\Http\FormRequest;

class ProcessInvoicesInBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // TODO.
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'invoices' => ['array'],
        ];
    }
}

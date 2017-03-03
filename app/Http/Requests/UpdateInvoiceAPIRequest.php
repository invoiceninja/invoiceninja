<?php

namespace App\Http\Requests;

use App\Models\Client;

class UpdateInvoiceAPIRequest extends InvoiceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('edit', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->action == ACTION_ARCHIVE) {
            return [];
        }

        $invoiceId = $this->entity()->id;

        $rules = [
            'invoice_items' => 'valid_invoice_items',
            'invoice_number' => 'unique:invoices,invoice_number,' . $invoiceId . ',id,account_id,' . $this->user()->account_id,
            'discount' => 'positive',
            //'invoice_date' => 'date',
            //'due_date' => 'date',
            //'start_date' => 'date',
            //'end_date' => 'date',
        ];

        return $rules;
    }
}

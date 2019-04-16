<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
        if (! $this->entity()) {
            return [];
        }

        $invoiceId = $this->entity()->id;

        $rules = [
            'client' => 'required',
            'discount' => 'positive',
            'invoice_date' => 'required',
        ];

        return $rules;
    }

    public function sanitize()
    {
        //do post processing of invoice request here, ie. invoice_items

    }

    public function messages()
    {

    }


}
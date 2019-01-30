<?php

namespace App\Http\Requests;

use App\Models\Invoice;

class UpdatePaymentTermRequest extends PaymentTermRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return $this->entity() && $this->user()->can('edit', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */

    public function rules()
    {
        if (! $this->entity()) {
            return [];
        }

        $paymentTermId = $this->entity()->id;

        $rules = [
            'num_days' => 'required|numeric|unique:payment_terms,num_days,' . $paymentTermId . ',id,account_id,' . $this->user()->account_id . ',deleted_at,NULL'
                . '|unique:payment_terms,num_days,' . $paymentTermId . ',id,account_id,0,deleted_at,NULL',
        ];


        return $rules;
    }
}

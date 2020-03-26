<?php

namespace App\Http\Requests;

use App\Models\Invoice;

class CreatePaymentTermRequest extends PaymentTermRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return $this->user()->can('create', ENTITY_PAYMENT_TERM);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */

    public function rules()
    {

        $rules = [
            'num_days' => 'required|numeric|unique:payment_terms,num_days,,id,account_id,' . $this->user()->account_id . ',deleted_at,NULL'
                . '|unique:payment_terms,num_days,,id,account_id,0,deleted_at,NULL',
        ];


        return $rules;
    }
}

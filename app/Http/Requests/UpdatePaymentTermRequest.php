<?php

namespace App\Http\Requests;

class UpdatePaymentTermRequest extends EntityRequest
{

    protected $entityType = ENTITY_PAYMENT_TERM;

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
        $rules = [];

        $rules['valid_payment_term'] = $this->entity()->account_id>0;

        return $rules;
    }
}

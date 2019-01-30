<?php

namespace App\Http\Requests;

class UpdateProposalRequest extends ProposalRequest
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

        return [
            'invoice_id' => 'required',
        ];
    }
}

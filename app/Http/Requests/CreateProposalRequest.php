<?php

namespace App\Http\Requests;

class CreateProposalRequest extends ProposalRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_PROPOSAL);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'quote_id' => 'required',
            'proposal_template_id' => 'required',
        ];
    }
}

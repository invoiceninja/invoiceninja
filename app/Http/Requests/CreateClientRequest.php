<?php

namespace App\Http\Requests;

class CreateClientRequest extends ClientRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_CLIENT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        if ($this->user()->account->client_number_counter) {
            $rules['id_number'] = 'unique:clients,id_number,,id,account_id,' . $this->user()->account_id;
        }

        return $rules;
    }
}

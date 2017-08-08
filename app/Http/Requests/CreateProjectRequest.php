<?php

namespace App\Http\Requests;

class CreateProjectRequest extends ProjectRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_PROJECT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => sprintf('required|unique:projects,name,,id,account_id,%s', $this->user()->account_id),
            'client_id' => 'required',
        ];
    }
}

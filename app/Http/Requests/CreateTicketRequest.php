<?php

namespace App\Http\Requests;


class CreateTicketRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return $this->user()->can('create', ENTITY_TICKET);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */

    public function rules()
    {

        $rules = [
            'subject' => 'required',
            'description' => 'required',
        ];


        return $rules;
    }
}

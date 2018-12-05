<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;

class UpdateClientRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return true;
       // return ! auth()->user(); //todo permissions
    }

    public function rules()

    {
        $rules = [
            'name' => 'required',
            'contacts.*.email' => 'email|unique:client_contacts,email'
            ];

        $custom_messages = [
            'unique' => 'The email is already in use.'
        ];

        $this->validate($rules, $custom_messages);


    }


}
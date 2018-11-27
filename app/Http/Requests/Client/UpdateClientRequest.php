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
        return [
            'name' => 'required',
            //'contacts.*.email' => 'email|unique:client_contacts,email'
        ];
    }


}
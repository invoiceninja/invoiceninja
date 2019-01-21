<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Models\Client;

class StoreClientRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return $this->user()->can('create', Client::class);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules['name'] = 'required';

        $contacts = request('contacts');

            for ($i = 0; $i < count($contacts); $i++) {
                $rules['contacts.' . $i . '.email'] = 'required|email|unique:client_contacts,email,' . isset($contacts[$i]['id']);
            }

            return $rules;
            

    }

    public function messages()
    {
        return [
            'unique' => trans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => trans('validation.email', ['attribute' => 'email']),
        ];
    }


}
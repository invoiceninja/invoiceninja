<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Request;

class UpdateUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->user);

    }


    public function rules()
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' =>  'required|string:max:100',
            'email' => new UniqueUserRule(),
        ];
    }

}
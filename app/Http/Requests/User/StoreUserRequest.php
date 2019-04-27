<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Request;
use App\Models\User;

class StoreUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', User::class);
    }


    public function sanitize()
    {
        //do post processing of user request
    }

    public function messages()
    {

    }


}
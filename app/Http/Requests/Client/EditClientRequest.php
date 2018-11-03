<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;

class EditClientRequest extends Request
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


}
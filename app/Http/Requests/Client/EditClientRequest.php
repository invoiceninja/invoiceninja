<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class CreateAccountRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function entity()
    {
        parent::entity(Client::class, $this->decodePrimaryKey(request()))
    }



    public function authorize()
    {
        return ! auth()->user(); //todo permissions
    }


}
<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Models\Client;

class EditClientRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return $this->user()->can('edit', $this->client);
    }

    public function sanitize()
    {
        $input = $this->all();

        //$input['id'] = $this->encodePrimaryKey($input['id']);

        //$this->replace($input);

        return $this->all();
    }

}
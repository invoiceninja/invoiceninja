<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Models\Quote;

class EditQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return auth()->user()->can('edit', $this->quote);
    }

    public function rules()
    {
        $rules = [];
        
        return $rules;
    }


    public function sanitize()
    {
        $input = $this->all();

        //$input['id'] = $this->encodePrimaryKey($input['id']);

        //$this->replace($input);

        return $this->all();
    }

}
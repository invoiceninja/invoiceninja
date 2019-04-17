<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\Invoice;

class EditInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return auth()->user()->can('edit', $this->invoice);
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
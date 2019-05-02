<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->quote);

    }


    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }
    
}
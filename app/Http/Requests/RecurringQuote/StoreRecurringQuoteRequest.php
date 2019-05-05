<?php

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Models\RecurringQuote;

class StoreRecurringQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', RecurringQuote::class);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }


    public function sanitize()
    {
        //do post processing of RecurringQuote request here, ie. RecurringQuote_items
    }

    public function messages()
    {

    }


}


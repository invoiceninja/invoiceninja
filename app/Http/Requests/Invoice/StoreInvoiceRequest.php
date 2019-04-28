<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\Invoice;

class StoreInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Invoice::class);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }


    public function sanitize()
    {
        //do post processing of invoice request here, ie. invoice_items
    }

    public function messages()
    {

    }


}


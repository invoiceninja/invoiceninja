<?php

namespace App\Http\Requests\RecurringInvoice;

use App\Http\Requests\Request;
use App\Models\RecurringInvoice;

class StoreRecurringInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', RecurringInvoice::class);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }


    public function sanitize()
    {
        //do post processing of RecurringInvoice request here, ie. RecurringInvoice_items
    }

    public function messages()
    {

    }


}


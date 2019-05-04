<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Models\Payment;

class StorePaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Payment::class);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }


    public function sanitize()
    {
        //do post processing of Payment request here, ie. Payment_items
    }

    public function messages()
    {

    }


}


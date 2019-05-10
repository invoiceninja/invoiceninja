<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\ClientContact;
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
        $this->sanitize();

        return [
            'client_id' => 'required',
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }


public function sanitize()
{
    $input = $this->all();

    /** If we have an email address instead of a client_id - harvest the client_id here */
    if(isset($input['email']) && !$input['client_id'])
    {
        $contact = ClientContact::company(auth()->user()->company()->id)->whereEmail($input['email'])->first();

        if($contact)
            $input['client_id'] = $contact->client_id;
    }

    $this->replace($input);     
}

    public function messages()
    {

    }


}


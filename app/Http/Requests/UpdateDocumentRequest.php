<?php

namespace App\Http\Requests;

use App\Models\Contact;

class UpdateDocumentRequest extends DocumentRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {

        $contact = Contact::getContactIfLoggedIn();

        if($contact && $contact->account->hasFeature(FEATURE_DOCUMENTS))
            return true;
        else
            return $this->entity() && $this->user()->can('edit', $this->entity());

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [

        ];
    }
}

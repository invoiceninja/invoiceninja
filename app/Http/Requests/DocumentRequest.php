<?php

namespace App\Http\Requests;

use App\Models\Contact;

class DocumentRequest extends EntityRequest
{
    protected $entityType = ENTITY_DOCUMENT;

    public function authorize()
    {
        $contact = Contact::getContactIfLoggedIn();

        if($contact && $contact->account->hasFeature(FEATURE_DOCUMENTS))
            return true;
        else
            return $this->user()->can('view', ENTITY_DOCUMENT);
    }

}

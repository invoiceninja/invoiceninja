<?php

namespace App\Transformers;

use App\Models\ClientContact;

/**
 * Class ContactTransformer.
 *
 * @SWG\Definition(definition="ClientContact", @SWG\Xml(name="ClientContact"))
 */
class ClientContactTransformer extends EntityTransformer
{
    /**
     * @param ClientContact $contact
     *
     * @return array
     *
     */
    public function transform(ClientContact $contact)
    {
        return [
            'id' => (int) $contact->public_id,
            'first_name' => $contact->first_name ?: '',
            'last_name' => $contact->last_name ?: '',
            'email' => $contact->email ?: '',
            'updated_at' => $contact->updated_at,
            'archived_at' => $contact->deleted_at,
            'is_primary' => (bool) $contact->is_primary,
            'phone' => $contact->phone ?: '',
            'custom_value1' => $contact->custom_value1 ?: '',
            'custom_value2' => $contact->custom_value2 ?: '',
        ];
    }
}

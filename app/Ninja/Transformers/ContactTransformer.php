<?php

namespace App\Ninja\Transformers;

use App\Models\Contact;

/**
 * Class ContactTransformer.
 *
 * @SWG\Definition(definition="Contact", @SWG\Xml(name="Contact"))
 */
class ContactTransformer extends EntityTransformer
{
    /**
     * @param Contact $contact
     *
     * @return array
     *
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="first_name", type="string", example="John")
     * @SWG\Property(property="last_name", type="string", example="Doe")
     * @SWG\Property(property="email", type="string", example="john.doe@company.com")
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="is_primary", type="boolean", example=false)
     * @SWG\Property(property="phone", type="string", example="(212) 555-1212")
     * @SWG\Property(property="last_login", type="string", format="date-time", example="2016-01-01 12:10:00")
     * @SWG\Property(property="send_invoice", type="boolean", example=false)
     * @SWG\Property(property="custom_value1", type="string", example="Value")
     * @SWG\Property(property="custom_value2", type="string", example="Value")
     */
    public function transform(Contact $contact)
    {
        return array_merge($this->getDefaults($contact), [
            'id' => (int) $contact->public_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'updated_at' => $this->getTimestamp($contact->updated_at),
            'archived_at' => $this->getTimestamp($contact->deleted_at),
            'is_primary' => (bool) $contact->is_primary,
            'phone' => $contact->phone,
            'last_login' => $contact->last_login,
            'send_invoice' => (bool) $contact->send_invoice,
            'custom_value1' => $contact->custom_value1,
            'custom_value2' => $contact->custom_value2,
        ]);
    }
}

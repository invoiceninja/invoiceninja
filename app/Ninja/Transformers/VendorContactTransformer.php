<?php

namespace App\Ninja\Transformers;

use App\Models\VendorContact;

// vendor
class VendorContactTransformer extends EntityTransformer
{
		/**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="first_name", type="string", example="Luke")
     * @SWG\Property(property="last_name", type="string", example="Smith")
     * @SWG\Property(property="email", type="string", example="john.doe@company.com")
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="is_primary", type="boolean", example=false)
     * @SWG\Property(property="phone", type="string", example="(212) 555-1212")
     */
    public function transform(VendorContact $contact)
    {
        return array_merge($this->getDefaults($contact), [
            'id' => (int) $contact->public_id,
            'first_name' => $contact->first_name ?: '',
            'last_name' => $contact->last_name ?: '',
            'email' => $contact->email ?: '',
            'updated_at' => $this->getTimestamp($contact->updated_at),
            'archived_at' => $this->getTimestamp($contact->deleted_at),
            'is_primary' => (bool) $contact->is_primary,
            'phone' => $contact->phone ?: '',
        ]);
    }
}

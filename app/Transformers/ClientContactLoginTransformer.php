<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

/**
 * Class ContactTransformer.
 *
 * @SWG\Definition(definition="ClientContact", @SWG\Xml(name="ClientContact"))
 */
class ClientContactLoginTransformer extends EntityTransformer
{
    use MakesHash;
    /**
     * @param ClientContact $contact
     *
     * @return array
     *
     */
    public function transform(ClientContact $contact)
    {
        
        return [
            'id' => $this->encodePrimaryKey($contact->id),
            'first_name' => $contact->first_name ?: '',
            'last_name' => $contact->last_name ?: '',
            'email' => $contact->email ?: '',
            'updated_at' => $contact->updated_at,
            'archived_at' => $contact->deleted_at,
            'is_primary' => (bool) $contact->is_primary,
            'is_locked' => (bool) $contact->is_locked,
            'phone' => $contact->phone ?: '',
            'custom_value1' => $contact->custom_value1 ?: '',
            'custom_value2' => $contact->custom_value2 ?: '',
            'custom_value3' => $contact->custom_value3 ?: '',
            'custom_value4' => $contact->custom_value4 ?: '',
            'token'=> $contact->token ?: '',
        ];
    }
}

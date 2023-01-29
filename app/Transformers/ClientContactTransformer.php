<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

/**
 * Class ContactTransformer.
 */
class ClientContactTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @param ClientContact $contact
     *
     * @return array
     */
    public function transform(ClientContact $contact)
    {
        return [
            'id' => $this->encodePrimaryKey($contact->id),
            'first_name' => $contact->first_name ?: '',
            'last_name' => $contact->last_name ?: '',
            'email' => $contact->email ?: '',
            'created_at' => (int) $contact->created_at,
            'updated_at' => (int) $contact->updated_at,
            'archived_at' => (int) $contact->deleted_at,
            'is_primary' => (bool) $contact->is_primary,
            'is_locked' => (bool) $contact->is_locked,
            'phone' => $contact->phone ?: '',
            'custom_value1' => $contact->custom_value1 ?: '',
            'custom_value2' => $contact->custom_value2 ?: '',
            'custom_value3' => $contact->custom_value3 ?: '',
            'custom_value4' => $contact->custom_value4 ?: '',
            'contact_key' => $contact->contact_key ?: '',
            'send_email' => (bool) $contact->send_email,
            'last_login' => (int) $contact->last_login,
            'password' => empty($contact->password) ? '' : '**********',
            'link' => $contact->getLoginLink(),
        ];
    }
}

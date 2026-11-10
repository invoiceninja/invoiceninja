<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

/**
 * Class ClientContactProfileTransformer.
 */
class ClientContactProfileTransformer extends ClientContactTransformer
{
    use MakesHash;

    /**
     * @param ClientContact $contact
     *
     * @return array
     */
    public function transform(ClientContact $contact)
    {
        return array_merge(parent::transform($contact), [
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'contact_key' => '',
            'last_login' => (int) 0,
            'password' => '**********',
            'link' => '',
        ]);
    }
}

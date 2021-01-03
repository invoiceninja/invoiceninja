<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\Models\VendorContact;

class VendorContactFactory
{
    public static function create(int $company_id, int $user_id) :VendorContact
    {
        $vendor_contact = new VendorContact;
        $vendor_contact->first_name = '';
        $vendor_contact->user_id = $user_id;
        $vendor_contact->company_id = $company_id;
        $vendor_contact->id = 0;

        return $vendor_contact;
    }
}

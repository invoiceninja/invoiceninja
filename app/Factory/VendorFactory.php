<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorFactory
{
    public static function create(int $company_id, int $user_id): Vendor
    {
        $vendor = new Vendor();
        $vendor->company_id = $company_id;
        $vendor->user_id = $user_id;
        $vendor->name = '';
        $vendor->website = '';
        $vendor->private_notes = '';
        $vendor->public_notes = '';
        $vendor->country_id = 4;
        $vendor->is_deleted = false;
        $vendor->vendor_hash = Str::random(40);
        // $vendor->classification = '';

        return $vendor;
    }
}

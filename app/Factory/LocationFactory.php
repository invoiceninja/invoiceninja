<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Location;

class LocationFactory
{
    public static function create(int $company_id, int $user_id): Location
    {
        $location = new Location();
        $location->company_id = $company_id;
        $location->user_id = $user_id;
        $location->name = '';
        $location->country_id = null;
        $location->is_deleted = false;

        return $location;
    }
}

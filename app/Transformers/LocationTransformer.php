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

namespace App\Transformers;

use App\Models\Location;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * class LocationTransformer.
 */
class LocationTransformer extends EntityTransformer
{
    use MakesHash;
    use SoftDeletes;

    protected array $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
    ];

    /**
     * @param Location $location
     *
     * @return array
     */
    public function transform(Location $location)
    {
        return [
            'id' => $location->hashed_id,
            'user_id' => $this->encodePrimaryKey($location->user_id),
            'vendor_id' => $this->encodePrimaryKey($location->vendor_id),
            'client_id' => $this->encodePrimaryKey($location->client_id),
            'name' => (string) $location->name ?: '',
            'address1' => $location->address1 ?: '',
            'address2' => $location->address2 ?: '',
            // 'phone' => $location->phone ?: '',
            'city' => $location->city ?: '',
            'state' => $location->state ?: '',
            'postal_code' => $location->postal_code ?: '',
            'country_id' => (string) $location->country_id ?: '',
            'custom_value1' => $location->custom_value1 ?: '',
            'custom_value2' => $location->custom_value2 ?: '',
            'custom_value3' => $location->custom_value3 ?: '',
            'custom_value4' => $location->custom_value4 ?: '',
            'is_deleted' => (bool) $location->is_deleted,
            'is_shipping_location' => (bool) $location->is_shipping_location,
            'tax_data' => $location->tax_data ?: new \stdClass(),
            'updated_at' => (int) $location->updated_at,
            'archived_at' => (int) $location->deleted_at,
            'created_at' => (int) $location->created_at,
        ];
    }
}

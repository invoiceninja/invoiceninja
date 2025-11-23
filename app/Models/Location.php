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

namespace App\Models;

use App\Models\Traits\Excludable;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Location
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $vendor_id
 * @property int|null $client_id
 * @property string|null $name
 * @property string|null $address1
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property int|null $country_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property bool $is_deleted
 * @property bool $is_shipping_location
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property object|array|null $tax_data
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Vendor|null $vendor
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Country|null $country
 *
 * @mixin \Eloquent
 */
class Location extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use Excludable;

    protected $hidden = [
        'id',
        'user_id',
        'company_id',
    ];

    protected $fillable = [
        'name',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'is_deleted',
        'is_shipping_location',
        'client_id',
        'vendor_id',
       ];

    protected $casts = [
        'tax_data' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_deleted' => 'bool',
        'is_shipping_locaiton',
    ];

    protected $touches = [];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function getEntityType()
    {
        return self::class;
    }

}

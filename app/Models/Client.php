<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;
use Hashids\Hashids;
use App\Utils\Traits\MakesHash;

class Client extends BaseModel
{
    use PresentableTrait;
    use MakesHash;

    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    protected $appends = ['hash_id'];

    protected $fillable = [
        'name',
        'id_number',
        'vat_number',
        'work_phone',
        'custom_value1',
        'custom_value2',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'size_id',
        'industry_id',
        'currency_id',
        'language_id',
        'payment_terms',
        'website',
    ];

    public function getHashIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function locations()
    {
        return $this->hasMany(ClientLocation::class);
    }

    public function primary_billing_location()
    {
        return $this->hasMany(ClientLocation::class)->whereIsPrimaryBilling(true);
    }

    public function primary_shipping_location()
    {
        return $this->hasMany(ClientLocation::class)->whereIsPrimaryShipping(true);
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->whereIsPrimary(true);
    }

}

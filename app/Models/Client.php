<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Country;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use SoftDeletes;

    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    //protected $appends = ['client_id'];

    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
        'deleted_at',
        'contacts',
        'primary_contact',
        'q',
        'company',
        'country',
        'shipping_country'
    ];

    
    protected $with = ['contacts', 'primary_contact', 'country', 'shipping_country'];

    //protected $dates = ['deleted_at'];

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderBy('is_primary', 'desc');
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->whereIsPrimary(true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function shipping_country()
    {
        return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
    }

}

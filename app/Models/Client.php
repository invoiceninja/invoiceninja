<?php

namespace App\Models;

use App\DataMapper\ClientSettings;
use App\Models\Company;
use App\Models\Country;
use App\Models\Filterable;
use App\Models\Timezone;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    
    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    protected $appends = [
        'client_settings_object'
    ];

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
    
    protected $with = ['contacts', 'primary_contact', 'country', 'shipping_country', 'company'];

    protected $casts = [
        'settings' => 'object'
    ];

    public function getClientSettingsObjectAttribute()
    {
        return new ClientSettings($this->settings);
    }

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

    public function timezone()
    {
        return Timezone::find($this->getSettings()->timezone_id);
    }

    public function getSettings()
    {
        return ClientSettings::buildClientSettings($this->company->settings, $this->settings);
    }



}

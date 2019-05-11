<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Filterable;
use App\Models\Timezone;
use App\Utils\Traits\GeneratesNumberCounter;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use MakesDates;
    use SoftDeletes;
    use Filterable;
    use GeneratesNumberCounter;
    
    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    protected $appends = [
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
    
    protected $with = [
        'contacts', 
        'primary_contact', 
        'country', 
        'shipping_country', 
        'company'
    ];

    protected $casts = [
        'settings' => 'object'
    ];

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
        return Timezone::find($this->getMergedSettings()->timezone_id);
    }

    public function getSettings()
    {
        return new ClientSettings($this->settings);
    }

    public function getMergedSettings()
    {
        return ClientSettings::buildClientSettings(new CompanySettings($this->company->settings), new ClientSettings($this->settings));
    }


    /**
     * Gets the settings by key.
     *
     * When we need to update a setting value, we need to harvest
     * the object of the setting. This is not possible when using the merged settings
     * as we do not know which object the setting has come from.
     *
     * The following method will return the entire object of the property searched for
     * where a value exists for $key.
     *
     * This object can then be mutated by the handling class, 
     * to persist the new settings we will also need to pass back a 
     * reference to the parent class.
     *
     * @param      mixes  $key    The key of property
     */
    public function getSettingsByKey($key)
    {
        /* Does Setting Exist @ client level */
        if(isset($this->getSettings()->{$key}))
        {   
            //Log::error('harvesting client settings for key = '. $key . ' and it has the value = '. $this->getSettings()->{$key});
            //Log::error(print_r($this->getSettings(),1));
            return $this->getSettings();
        }
        else {
            //Log::error(print_r(new CompanySettings($this->company->settings),1));
            return new CompanySettings($this->company->settings);  
        }

    }

    public function setSettingsByEntity($entity, $settings)
    {
        switch ($entity) {
            case Client::class:
               // Log::error('saving client settings');
                $this->settings = $settings;
                $this->save();
                $this->fresh();
                break;
            case Company::class:
               // Log::error('saving company settings');
                $this->company->settings = $settings;
                $this->company->save();
                $this->company->fresh();
                break;
            
            default:
                # code...
                break;
        }
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }




}

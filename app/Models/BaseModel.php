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
use App\Filters\QueryFilters;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class BaseModel extends Model
{
    use MakesHash;
    use UserSessionAttributes;
    use SoftDeletes;

    //todo customise names of archived_at / updated_at columns
    ///const CREATED_AT = 'creation_date';
    //const UPDATED_AT = 'last_update';

    protected $appends = [
        'hashed_id'
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];
    
    protected $dateFormat = 'Y-m-d H:i:s.u';
    
    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function __call($method, $params)
    {
        $entity = strtolower(class_basename($this));

        if ($entity) {
            $configPath = "modules.relations.$entity.$method";

            if (config()->has($configPath)) {
                $function = config()->get($configPath);

				return call_user_func_array(array($this, $function[0]), $function[1]);
            }
        }

        return parent::__call($method, $params);
    }

    /*
    V2 type of scope
     */
    public function scopeCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
        
        return $query;
    }

    /*
     V1 type of scope
     */
    public function scopeScope($query)
    {

        $query->where($this->getTable() .'.company_id', '=', auth()->user()->company()->id);

        return $query;
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
            return $this->getSettings()->{$key};
        }
        else {
            Log::error(print_r(new CompanySettings($this->company->settings),1));
            return (new CompanySettings($this->company->settings))->{$key};  
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
                break;
            //todo check that saving any other entity (Invoice:: RecurringInvoice::) settings is valid using the default:
            default:
                $this->client->settings = $settings;
                $this->client->save();
                break;
        }
    }
    
    /**
     * Gets the settings.
     *
     * Generic getter for client settings
     * 
     * @return     ClientSettings  The settings.
     */
    public function getSettings()
    {
        return new ClientSettings($this->settings);
    }



}

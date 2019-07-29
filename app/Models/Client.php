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
use App\Utils\Traits\GeneratesCounter;
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
    use GeneratesCounter;
    
    protected $presenter = 'App\Models\Presenters\ClientPresenter';


    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'company_id',
        'backup',
        'settings',
        'last_login',
        'private_notes'
    ];
   
    protected $fillable = [
        'name',
        'website',
        'private_notes',
        'industry_id',
        'size_id',
        'currency_id',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4,',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
        'settings',
        'payment_terms',
        'vat_number',
        'id_number',
    ];
    
    /*
    protected $with = [
        'contacts', 
        'primary_contact', 
        'country', 
        'shipping_country', 
        'company'
    ];
    */
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

    public function getMergedSettings()
    {
        return ClientSettings::buildClientSettings(new CompanySettings($this->company->settings), new ClientSettings($this->settings));
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }




}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\DataMapper\CompanySettings;
use App\Models\Presenters\VendorPresenter;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

class Vendor extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;
    use PresentableTrait;
    use AppSetup;

    protected $fillable = [
        'name',
        'assigned_user_id',
        'id_number',
        'vat_number',
        'phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'public_notes',
        'currency_id',
        'website',
        'transaction_name',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'number',
    ];

    protected $casts = [
        'country_id' => 'string',
        'currency_id' => 'string',
        'is_deleted' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    protected $with = [
        'company',
    ];

    protected $presenter = VendorPresenter::class;

    public function getEntityType()
    {
        return self::class;
    }

    public function primary_contact()
    {
        return $this->hasMany(VendorContact::class)->where('is_primary', true);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function contacts()
    {
        return $this->hasMany(VendorContact::class)->orderBy('is_primary', 'desc');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function currency()
    {
        $currencies = Cache::get('currencies');

        if (! $currencies) {
            $this->buildCache(true);
        }

        if (! $this->currency_id) {
            $this->currency_id = 1;
        }

        return $currencies->filter(function ($item) {
            return $item->id == $this->currency_id;
        })->first();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function translate_entity()
    {
        return ctrans('texts.vendor');
    }

    public function setCompanyDefaults($data, $entity_name) :array
    {
        $defaults = [];

        if (! (array_key_exists('terms', $data) && strlen($data['terms']) > 1)) {
            $defaults['terms'] = $this->getSetting($entity_name.'_terms');
        } elseif (array_key_exists('terms', $data)) {
            $defaults['terms'] = $data['terms'];
        }

        if (! (array_key_exists('footer', $data) && strlen($data['footer']) > 1)) {
            $defaults['footer'] = $this->getSetting($entity_name.'_footer');
        } elseif (array_key_exists('footer', $data)) {
            $defaults['footer'] = $data['footer'];
        }

        if (strlen($this->public_notes) >= 1) {
            $defaults['public_notes'] = $this->public_notes;
        }

        return $defaults;
    }

    public function getSetting($setting)
    {
        if ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company->settings->{$setting};
        } elseif (property_exists(CompanySettings::defaults(), $setting)) {
            return CompanySettings::defaults()->{$setting};
        }

        return '';
    }

    public function purchase_order_filepath($invitation)
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->vendor_hash.'/'.$contact_key.'/purchase_orders/';
    }

    public function locale()
    {
        return $this->company->locale();
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function date_format()
    {
        return $this->company->date_format();
    }

}

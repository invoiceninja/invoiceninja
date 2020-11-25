<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyLedgerFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\QuoteFactory;
use App\Models\Activity;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use App\Models\Filterable;
use App\Models\GatewayType;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Presenters\ClientPresenter;
use App\Models\Quote;
use App\Models\Timezone;
use App\Models\User;
use App\Services\Client\ClientService;
use App\Utils\Traits\CompanyGatewaySettings;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Exception;
use Hashids\Hashids;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel implements HasLocalePreference
{
    use PresentableTrait;
    use MakesHash;
    use MakesDates;
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;

    protected $presenter = ClientPresenter::class;

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'company_id',
//        'settings',
        'last_login',
    ];

    protected $fillable = [
        'assigned_user_id',
        'currency_id',
        'name',
        'website',
        'private_notes',
        'industry_id',
        'size_id',
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
        'group_settings_id',
        'public_notes',
    ];

    protected $with = [
        'gateway_tokens',
        'documents'
        //'currency',
        // 'primary_contact',
        // 'country',
        // 'contacts',
        // 'shipping_country',
        // 'company',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'country_id' => 'string',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function ledger()
    {
        return $this->hasMany(CompanyLedger::class)->orderBy('id', 'desc');
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function gateway_tokens()
    {
        return $this->hasMany(ClientGatewayToken::class);
    }

    /**
     * Retrieves the specific payment token per
     * gateway - per payment method.
     *
     * Allows the storage of multiple tokens
     * per client per gateway per payment_method
     *
     * @param  int $company_gateway_id  The company gateway ID
     * @param  int $payment_method_id   The payment method ID
     * @return ClientGatewayToken       The client token record
     */
    public function gateway_token($company_gateway_id, $payment_method_id)
    {
        return $this->gateway_tokens()
                    ->whereCompanyGatewayId($company_gateway_id)
                    ->whereGatewayTypeId($payment_method_id)
                    ->first();
    }

    public function credits()
    {
        return $this->hasMany(Credit::class)->withTrashed();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'desc');
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderBy('is_primary', 'desc');
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->where('is_primary', true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    public function shipping_country()
    {
        return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
    }

    public function system_logs()
    {
        return $this->hasMany(SystemLog::class);
    }

    public function timezone()
    {
        return Timezone::find($this->getSetting('timezone_id'));
    }

    public function language()
    {
        //return Language::find($this->getSetting('language_id'));

        $languages = Cache::get('languages');

        return $languages->filter(function ($item) {
            return $item->id == $this->getSetting('language_id');
        })->first();
    }

    public function locale()
    {
        return $this->language()->locale ?: 'en';
    }

    public function date_format()
    {
        $date_formats = Cache::get('date_formats');

        return $date_formats->filter(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->first()->format;
    }

    public function currency()
    {
        $currencies = Cache::get('currencies');

        return $currencies->filter(function ($item) {
            return $item->id == $this->getSetting('currency_id');
        })->first();
    }

    public function service() :ClientService
    {
        return new ClientService($this);
    }

    public function updateBalance($amount) :ClientService
    {
        return $this->service()->updateBalance($amount);
    }

    /**
     * Adjusts client "balances" when a client
     * makes a payment that goes on file, but does
     * not effect the client.balance record.
     *
     * @param  float $amount Adjustment amount
     * @return Client
     */
    // public function processUnappliedPayment($amount) :Client
    // {
    //     return $this->service()->updatePaidToDate($amount)
    //                             ->adjustCreditBalance($amount)
    //                             ->save();
    // }

    /**
     * Returns the entire filtered set
     * of settings which have been merged from
     * Client > Group > Company levels.
     *
     * @return stdClass stdClass object of settings
     */
    public function getMergedSettings() :object
    {
        if ($this->group_settings !== null) {
            $group_settings = ClientSettings::buildClientSettings($this->group_settings->settings, $this->settings);

            return ClientSettings::buildClientSettings($this->company->settings, $group_settings);
        }

        return CompanySettings::setProperties(ClientSettings::buildClientSettings($this->company->settings, $this->settings));
    }

    /**
     * Returns a single setting
     * which cascades from
     * Client > Group > Company.
     *
     * @param  string $setting The Setting parameter
     * @return mixed          The setting requested
     */
    public function getSetting($setting)
    {

        /*Client Settings*/
        if ($this->settings && property_exists($this->settings, $setting) && isset($this->settings->{$setting})) {
            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >= 1)) {
                return $this->settings->{$setting};
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings->settings->{$setting};
        }

        /*Company Settings*/
        elseif ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company->settings->{$setting};
        }

        return '';

//        throw new \Exception("Settings corrupted", 1);
    }

    public function getSettingEntity($setting)
    {
        /*Client Settings*/
        if ($this->settings && (property_exists($this->settings, $setting) !== false) && (isset($this->settings->{$setting}) !== false)) {
            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >= 1)) {
                return $this;
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings;
        }

        /*Company Settings*/
        if ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company;
        }

        throw new Exception('Could not find a settings object', 1);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function group_settings()
    {
        return $this->belongsTo(GroupSetting::class);
    }

    /**
     * Returns the first Credit Card Gateway.
     *
     * @return null|CompanyGateway The Priority Credit Card gateway
     */
    public function getCreditCardGateway() :?CompanyGateway
    {
        $company_gateways = $this->getSetting('company_gateway_ids');

        /* It is very important to respect the order of the company_gateway_ids as they are ordered by priority*/
        if (strlen($company_gateways) >= 1) {
            $transformed_ids = $this->transformKeys(explode(',', $company_gateways));
            $gateways = $this->company
                             ->company_gateways
                             ->whereIn('id', $transformed_ids)
                             ->sortby(function ($model) use ($transformed_ids) {
                                 return array_search($model->id, $transformed_ids);
                             });
        } else {
            $gateways = $this->company->company_gateways;
        }

        foreach ($gateways as $gateway) {
            if (in_array(GatewayType::CREDIT_CARD, $gateway->driver($this)->gatewayTypes())) {
                return $gateway;
            }
        }

        return null;
    }

    public function getBankTransferGateway() :?CompanyGateway
    {
        $company_gateways = $this->getSetting('company_gateway_ids');

        if (strlen($company_gateways) >= 1) {
            $transformed_ids = $this->transformKeys(explode(',', $company_gateways));
            $gateways = $this->company
                             ->company_gateways
                             ->whereIn('id', $transformed_ids)
                             ->sortby(function ($model) use ($transformed_ids) {
                                 return array_search($model->id, $transformed_ids);
                             });
        } else {
            $gateways = $this->company->company_gateways;
        }

        foreach ($gateways as $gateway) {
            if ($this->currency()->code == 'USD' && in_array(GatewayType::BANK_TRANSFER, $gateway->driver($this)->gatewayTypes())) {
                return $gateway;
            }

            if ($this->currency()->code == 'EUR' && in_array(GatewayType::SEPA, $gateway->driver($this)->gatewayTypes())) {
                return $gateway;
            }
        }

        return null;
    }

    public function getBankTransferMethodType()
    {
        if ($this->currency()->code == 'USD') {
            return GatewayType::BANK_TRANSFER;
        }

        if ($this->currency()->code == 'EUR') {
            return GatewayType::SEPA;
        }
    }

    public function getCurrencyCode()
    {
        if ($this->currency()) {
            return $this->currency()->code;
        }

        return 'USD';
    }

    /**
     * Generates an array of payment urls per client
     * for a given amount.
     *
     * The route produced will provide the
     * company_gateway and payment_type ids
     *
     * The invoice/s will need to be injected
     * upstream of this method as they are not
     * included in this logic.
     *
     * @param  float $amount The amount to be charged
     * @return array         Array of payment labels and urls
     */
    public function getPaymentMethods($amount) :array
    {
        //this method will get all the possible gateways a client can pay with
        //but we also need to consider payment methods that are already stored
        //so we MUST filter the company gateways and remove duplicates.

        //Also need to harvest the list of client gateway tokens and present these
        //for instant payment

        $company_gateways = $this->getSetting('company_gateway_ids');

        //we need to check for "0" here as we disable a payment gateway for a client with the number "0"
        if ($company_gateways || $company_gateways == '0') {
            $transformed_ids = $this->transformKeys(explode(',', $company_gateways));
            $gateways = $this->company
                             ->company_gateways
                             ->whereIn('id', $transformed_ids)
                             ->sortby(function ($model) use ($transformed_ids) { //company gateways are sorted in order of priority
                                 return array_search($model->id, $transformed_ids);// this closure sorts for us
                             });
        } else {
            $gateways = $this->company->company_gateways->where('is_deleted', false);
        }

        $payment_methods = [];

        foreach ($gateways as $gateway) {
            foreach ($gateway->driver($this)->gatewayTypes() as $type) {
                if (isset($gateway->fees_and_limits) && property_exists($gateway->fees_and_limits, $type)) {
                    if ($this->validGatewayForAmount($gateway->fees_and_limits->{$type}, $amount)) {
                        $payment_methods[] = [$gateway->id => $type];
                    }
                } else {
                    $payment_methods[] = [$gateway->id => $type];
                }
            }
        }

        $payment_methods_collections = collect($payment_methods);

        //** Plucks the remaining keys into its own collection
        $payment_methods_intersect = $payment_methods_collections->intersectByKeys($payment_methods_collections->flatten(1)->unique());

        $payment_urls = [];

        foreach ($payment_methods_intersect as $key => $child_array) {
            foreach ($child_array as $gateway_id => $gateway_type_id) {
                $gateway = $gateways->where('id', $gateway_id)->first();

                $fee_label = $gateway->calcGatewayFeeLabel($amount, $this);

                $payment_urls[] = [
                    'label' => $gateway->getTypeAlias($gateway_type_id) . $fee_label,
                    'company_gateway_id'  => $gateway_id,
                    'gateway_type_id' => $gateway_type_id,
                ];
            }
        }

        if (($this->getSetting('use_credits_payment') == 'option' || $this->getSetting('use_credits_payment') == 'always') && $this->service()->getCreditBalance() > 0) {
            $payment_urls[] = [
                    'label' => ctrans('texts.apply_credit'),
                    'company_gateway_id'  => CompanyGateway::GATEWAY_CREDIT,
                    'gateway_type_id' => GatewayType::CREDIT,
                ];
        }

        return $payment_urls;
    }

    public function validGatewayForAmount($fees_and_limits_for_payment_type, $amount) :bool
    {
        if (isset($fees_and_limits_for_payment_type)) {
            $fees_and_limits = $fees_and_limits_for_payment_type;
        } else {
            return true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $fees_and_limits->min_limit != -1 && $amount < $fees_and_limits->min_limit) {
            return false;
        }

        if ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $fees_and_limits->max_limit != -1 && $amount > $fees_and_limits->max_limit) {
            return false;
        }

        return true;
    }

    public function preferredLocale()
    {
        $languages = Cache::get('languages');

        return $languages->filter(function ($item) {
            return $item->id == $this->getSetting('language_id');
        })->first()->locale;
    }

    public function invoice_filepath()
    {
        return $this->company->company_key.'/'.$this->client_hash.'/invoices/';
    }

    public function quote_filepath()
    {
        return $this->company->company_key.'/'.$this->client_hash.'/quotes/';
    }

    public function credit_filepath()
    {
        return $this->company->company_key.'/'.$this->client_hash.'/credits/';
    }

    public function company_filepath()
    {
        return $this->company->company_key.'/';
    }

    public function document_filepath()
    {
        return $this->company->company_key.'/documents/';
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

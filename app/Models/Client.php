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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\FeesAndLimits;
use App\Models\CompanyGateway;
use App\Models\Expense;
use App\Models\Presenters\ClientPresenter;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Services\Client\ClientService;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\ClientGroupSettingsSaver;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel implements HasLocalePreference
{
    use PresentableTrait;
    use MakesHash;
    use MakesDates;
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;
    use AppSetup;
    use ClientGroupSettingsSaver;

    protected $presenter = ClientPresenter::class;

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'company_id',
        'last_login',
    ];

    protected $fillable = [
        'assigned_user_id',
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
        'vat_number',
        'id_number',
        'group_settings_id',
        'public_notes',
        'phone',
        'number',
    ];

    protected $with = [
        'gateway_tokens',
        'documents',
        'contacts.company',
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

    /**
     * Whitelisted fields for using from query parameters on subscriptions request.
     *
     * @var string[]
     */
    public static $subscriptions_fillable = [
        'assigned_user_id',
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
        'payment_terms',
        'vat_number',
        'id_number',
        'public_notes',
        'phone',
    ];

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
        return $this->hasMany(ClientGatewayToken::class)->orderBy('is_default', 'DESC');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->withTrashed();
    }

    public function projects()
    {
        return $this->hasMany(Project::class)->withTrashed();
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
        return $this->hasMany(Activity::class)->take(50)->orderBy('id', 'desc');
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
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class)->withTrashed();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->withTrashed();
    }

    public function recurring_invoices()
    {
        return $this->hasMany(RecurringInvoice::class)->withTrashed();
    }

    public function recurring_expenses()
    {
        return $this->hasMany(RecurringExpense::class)->withTrashed();
    }

    public function shipping_country()
    {
        return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
    }

    public function system_logs()
    {
        return $this->hasMany(SystemLog::class)->take(50)->orderBy('id', 'desc');
    }

    public function timezone()
    {
        return Timezone::find($this->getSetting('timezone_id'));
    }

    public function language()
    {
        $languages = Cache::get('languages');

        if (! $languages) {
            $this->buildCache(true);
        }

        return $languages->filter(function ($item) {
            return $item->id == $this->getSetting('language_id');
        })->first();
    }

    public function locale()
    {
        if (! $this->language()) {
            return 'en';
        }

        return $this->language()->locale ?: 'en';
    }

    public function date_format()
    {
        $date_formats = Cache::get('date_formats');

        if (! $date_formats) {
            $this->buildCache(true);
        }

        return $date_formats->filter(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->first()->format;
    }

    public function currency()
    {
        $currencies = Cache::get('currencies');

        if (! $currencies) {
            $this->buildCache(true);
        }

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
            } elseif (is_bool($this->settings->{$setting})) {
                return $this->settings->{$setting};
            } elseif (is_int($this->settings->{$setting})) { //10-08-2022 integer client values are not being passed back! This resolves it.
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
        } elseif (property_exists(CompanySettings::defaults(), $setting)) {
            return CompanySettings::defaults()->{$setting};
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
        $pms = $this->service()->getPaymentMethods(-1);

        foreach ($pms as $pm) {
            if ($pm['gateway_type_id'] == GatewayType::CREDIT_CARD) {
                $cg = CompanyGateway::find($pm['company_gateway_id']);

                if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::CREDIT_CARD)) {
                    $fees_and_limits = $cg->fees_and_limits;
                    $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits;
                    $cg->fees_and_limits = $fees_and_limits;
                    $cg->save();
                }

                if ($cg && $cg->fees_and_limits->{GatewayType::CREDIT_CARD}->is_enabled) {
                    return $cg;
                }
            }
        }

        return null;
    }

    //todo refactor this  - it is only searching for existing tokens
    public function getBankTransferGateway() :?CompanyGateway
    {
        $pms = $this->service()->getPaymentMethods(-1);

        if ($this->currency()->code == 'USD' && in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::BANK_TRANSFER) {
                    $cg = CompanyGateway::find($pm['company_gateway_id']);

                    if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::BANK_TRANSFER)) {
                        $fees_and_limits = $cg->fees_and_limits;
                        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits;
                        $cg->fees_and_limits = $fees_and_limits;
                        $cg->save();
                    }

                    if ($cg && $cg->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        if ($this->currency()->code == 'EUR' && (in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id')) || in_array(GatewayType::SEPA, array_column($pms, 'gateway_type_id')))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::SEPA) {
                    $cg = CompanyGateway::find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::SEPA}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        if ($this->country && $this->country->iso_3166_3 == 'GBR' && in_array(GatewayType::DIRECT_DEBIT, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::DIRECT_DEBIT) {
                    $cg = CompanyGateway::find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::DIRECT_DEBIT}->is_enabled) {
                        return $cg;
                    }
                }
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

        if ($this->currency()->code == 'GBP') {
            return GatewayType::DIRECT_DEBIT;
        }
    }

    public function getCurrencyCode()
    {
        if ($this->currency()) {
            return $this->currency()->code;
        }

        return 'USD';
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

        if (! $languages) {
            $this->buildCache(true);
        }

        return $languages->filter(function ($item) {
            return $item->id == $this->getSetting('language_id');
        })->first()->locale;
    }

    public function backup_path()
    {
        return $this->company->company_key.'/'.$this->client_hash.'/backups';
    }

    public function invoice_filepath($invitation)
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/invoices/';
    }

    public function quote_filepath($invitation)
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/quotes/';
    }

    public function credit_filepath($invitation)
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/credits/';
    }

    public function recurring_invoice_filepath($invitation)
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/recurring_invoices/';
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
        return $this->hasMany(Payment::class)->withTrashed();
    }

    public function timezone_offset()
    {
        $offset = 0;

        $entity_send_time = $this->getSetting('entity_send_time');

        if ($entity_send_time == 0) {
            return 0;
        }

        $timezone = $this->company->timezone();

        $offset -= $timezone->utc_offset;
        $offset += ($entity_send_time * 3600);

        return $offset;
    }

    public function transaction_event()
    {
        $client = $this->fresh();

        return [
            'client_id' => $client->id,
            'client_balance' => $client->balance ?: 0,
            'client_paid_to_date' => $client->paid_to_date ?: 0,
            'client_credit_balance' => $client->credit_balance ?: 0,
        ];
    }

    public function translate_entity()
    {
        return ctrans('texts.client');
    }
}

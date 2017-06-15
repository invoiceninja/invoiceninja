<?php

namespace App\Models;

use Carbon;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;
use Utils;

/**
 * Class Client.
 */
class Client extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\ClientPresenter';

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
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
        'invoice_number_counter',
        'quote_number_counter',
        'public_notes',
    ];

    /**
     * @var string
     */
    public static $fieldName = 'name';
    /**
     * @var string
     */
    public static $fieldPhone = 'work_phone';
    /**
     * @var string
     */
    public static $fieldAddress1 = 'address1';
    /**
     * @var string
     */
    public static $fieldAddress2 = 'address2';
    /**
     * @var string
     */
    public static $fieldCity = 'city';
    /**
     * @var string
     */
    public static $fieldState = 'state';
    /**
     * @var string
     */
    public static $fieldPostalCode = 'postal_code';
    /**
     * @var string
     */
    public static $fieldNotes = 'notes';
    /**
     * @var string
     */
    public static $fieldCountry = 'country';
    /**
     * @var string
     */
    public static $fieldWebsite = 'website';
    /**
     * @var string
     */
    public static $fieldVatNumber = 'vat_number';
    /**
     * @var string
     */
    public static $fieldIdNumber = 'id_number';

    /**
     * @return array
     */
    public static function getImportColumns()
    {
        return [
            self::$fieldName,
            self::$fieldPhone,
            self::$fieldAddress1,
            self::$fieldAddress2,
            self::$fieldCity,
            self::$fieldState,
            self::$fieldPostalCode,
            self::$fieldCountry,
            self::$fieldNotes,
            self::$fieldWebsite,
            self::$fieldVatNumber,
            self::$fieldIdNumber,
            Contact::$fieldFirstName,
            Contact::$fieldLastName,
            Contact::$fieldPhone,
            Contact::$fieldEmail,
        ];
    }

    /**
     * @return array
     */
    public static function getImportMap()
    {
        return [
            'first' => 'first_name',
            'last' => 'last_name',
            'email' => 'email',
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'apt|street2|address2' => 'address2',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
            'note' => 'notes',
            'site|website' => 'website',
            'vat' => 'vat_number',
            'number' => 'id_number',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function quotes()
    {
        return $this->hasMany('App\Models\Invoice')->where('invoice_type_id', '=', INVOICE_TYPE_QUOTE);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function publicQuotes()
    {
        return $this->hasMany('App\Models\Invoice')->where('invoice_type_id', '=', INVOICE_TYPE_QUOTE)->whereIsPublic(true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Models\Payment');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany('App\Models\Contact');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function credits()
    {
        return $this->hasMany('App\Models\Credit');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function creditsWithBalance()
    {
        return $this->hasMany('App\Models\Credit')->where('balance', '>', 0);
    }

    /**
     * @return mixed
     */
    public function expenses()
    {
        return $this->hasMany('App\Models\Expense', 'client_id', 'id')->withTrashed();
    }

    /**
     * @param $data
     * @param bool $isPrimary
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function addContact($data, $isPrimary = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : (isset($data['id']) ? $data['id'] : false);

        if ($publicId && $publicId != '-1') {
            $contact = Contact::scope($publicId)->firstOrFail();
        } else {
            $contact = Contact::createNew();
            $contact->send_invoice = true;

            if (isset($data['contact_key']) && $this->account->account_key == env('NINJA_LICENSE_ACCOUNT_KEY')) {
                $contact->contact_key = $data['contact_key'];
            } else {
                $contact->contact_key = strtolower(str_random(RANDOM_KEY_LENGTH));
            }
        }

        if ($this->account->isClientPortalPasswordEnabled()) {
            if (! empty($data['password']) && $data['password'] != '-%unchanged%-') {
                $contact->password = bcrypt($data['password']);
            } elseif (empty($data['password'])) {
                $contact->password = null;
            }
        }

        $contact->fill($data);
        $contact->is_primary = $isPrimary;

        return $this->contacts()->save($contact);
    }

    /**
     * @param $balanceAdjustment
     * @param $paidToDateAdjustment
     */
    public function updateBalances($balanceAdjustment, $paidToDateAdjustment)
    {
        if ($balanceAdjustment == 0 && $paidToDateAdjustment == 0) {
            return;
        }

        $this->balance = $this->balance + $balanceAdjustment;
        $this->paid_to_date = $this->paid_to_date + $paidToDateAdjustment;

        $this->save();
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/clients/{$this->public_id}";
    }

    /**
     * @return float|int
     */
    public function getTotalCredit()
    {
        return DB::table('credits')
                ->where('client_id', '=', $this->id)
                ->whereNull('deleted_at')
                ->sum('balance');
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getPrimaryContact()
    {
        return $this->contacts()
                    ->whereIsPrimary(true)
                    ->first();
    }

    /**
     * @return mixed|string
     */
    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        if (! count($this->contacts)) {
            return '';
        }

        $contact = $this->contacts[0];

        return $contact->getDisplayName();
    }

    /**
     * @return string
     */
    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;

        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_CLIENT;
    }

    /**
     * @return bool
     */
    public function showMap()
    {
        return $this->hasAddress() && env('GOOGLE_MAPS_ENABLED') !== false;
    }

    /**
     * @return bool
     */
    public function hasAddress()
    {
        $fields = [
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country_id',
        ];

        foreach ($fields as $field) {
            if ($this->$field) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getDateCreated()
    {
        if ($this->created_at == '0000-00-00 00:00:00') {
            return '---';
        } else {
            return $this->created_at->format('m/d/y h:i a');
        }
    }

    /**
     * @return bool
     */
    public function getGatewayToken()
    {
        $accountGateway = $this->account->getGatewayByType(GATEWAY_TYPE_TOKEN);

        if (! $accountGateway) {
            return false;
        }

        return AccountGatewayToken::clientAndGateway($this->id, $accountGateway->id)->first();
    }

    /**
     * @return bool
     */
    public function defaultPaymentMethod()
    {
        if ($token = $this->getGatewayToken()) {
            return $token->default_payment_method;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function autoBillLater()
    {
        if ($token = $this->getGatewayToken()) {
            if ($this->account->auto_bill_on_due_date) {
                return true;
            }

            return $token->autoBillLater();
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->balance + $this->paid_to_date;
    }

    /**
     * @return mixed
     */
    public function getCurrencyId()
    {
        if ($this->currency_id) {
            return $this->currency_id;
        }

        if (! $this->account) {
            $this->load('account');
        }

        return $this->account->currency_id ?: DEFAULT_CURRENCY;
    }

    /**
     * @return string
     */
    public function getCurrencyCode()
    {
        if ($this->currency) {
            return $this->currency->code;
        }

        if (! $this->account) {
            $this->load('account');
        }

        return $this->account->currency ? $this->account->currency->code : 'USD';
    }

    /**
     * @param $isQuote
     *
     * @return mixed
     */
    public function getCounter($isQuote)
    {
        return $isQuote ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    public function markLoggedIn()
    {
        $this->last_login = Carbon::now()->toDateTimeString();
        $this->save();
    }

    /**
     * @return bool
     */
    public function hasAutoBillConfigurableInvoices()
    {
        return $this->invoices()->whereIsPublic(true)->whereIn('auto_bill', [AUTO_BILL_OPT_IN, AUTO_BILL_OPT_OUT])->count() > 0;
    }

    /**
     * @return bool
     */
    public function hasRecurringInvoices()
    {
        return $this->invoices()->whereIsPublic(true)->whereIsRecurring(true)->count() > 0;
    }

    public function defaultDaysDue()
    {
        return $this->payment_terms == -1 ? 0 : $this->payment_terms;
    }
}

Client::creating(function ($client) {
    $client->setNullValues();
    $client->account->incrementCounter($client);
});

Client::updating(function ($client) {
    $client->setNullValues();
});

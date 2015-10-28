<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use App\Events\ClientWasCreated;
use App\Events\ClientWasUpdated;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

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

    public static $fieldName = 'Client - Name';
    public static $fieldPhone = 'Client - Phone';
    public static $fieldAddress1 = 'Client - Street';
    public static $fieldAddress2 = 'Client - Apt/Floor';
    public static $fieldCity = 'Client - City';
    public static $fieldState = 'Client - State';
    public static $fieldPostalCode = 'Client - Postal Code';
    public static $fieldNotes = 'Client - Notes';
    public static $fieldCountry = 'Client - Country';

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment');
    }

    public function contacts()
    {
        return $this->hasMany('App\Models\Contact');
    }

    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    public function addContact($data, $isPrimary = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($publicId && $publicId != '-1') {
            $contact = Contact::scope($publicId)->firstOrFail();
        } else {
            $contact = Contact::createNew();
            $contact->send_invoice = true;
        }

        $contact->fill($data);
        $contact->is_primary = $isPrimary;

        return $this->contacts()->save($contact);
    }

    public function updateBalances($balanceAdjustment, $paidToDateAdjustment)
    {
        if ($balanceAdjustment === 0 && $paidToDateAdjustment === 0) {
            return;
        }

        $this->balance = $this->balance + $balanceAdjustment;
        $this->paid_to_date = $this->paid_to_date + $paidToDateAdjustment;
        
        $this->save();
    }

    public function getRoute()
    {
        return "/clients/{$this->public_id}";
    }

    public function getTotalCredit()
    {
        return DB::table('credits')
                ->where('client_id', '=', $this->id)
                ->whereNull('deleted_at')
                ->sum('balance');
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }
        
        if ( ! count($this->contacts)) {
            return '';
        }

        $contact = $this->contacts[0];
        return $contact->getDisplayName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getEntityType()
    {
        return ENTITY_CLIENT;
    }

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

    public function getDateCreated()
    {
        if ($this->created_at == '0000-00-00 00:00:00') {
            return '---';
        } else {
            return $this->created_at->format('m/d/y h:i a');
        }
    }


    public function getGatewayToken()
    {
        $this->account->load('account_gateways');

        if (!count($this->account->account_gateways)) {
            return false;
        }

        $accountGateway = $this->account->getGatewayConfig(GATEWAY_STRIPE);
        
        if (!$accountGateway) {
            return false;
        }

        $token = AccountGatewayToken::where('client_id', '=', $this->id)
                    ->where('account_gateway_id', '=', $accountGateway->id)->first();

        return $token ? $token->token : false;
    }

    public function getGatewayLink()
    {
        $token = $this->getGatewayToken();
        return $token ? "https://dashboard.stripe.com/customers/{$token}" : false;
    }

    public function getCurrencyId()
    {
        if ($this->currency_id) {
            return $this->currency_id;
        }

        if (!$this->account) {
            $this->load('account');
        }

        return $this->account->currency_id ?: DEFAULT_CURRENCY;
    }

    public function getCounter($isQuote)
    {
        return $isQuote ? $this->quote_number_counter : $this->invoice_number_counter;
    }

    public function markLoggedIn()
    {
        $this->last_login = Carbon::now()->toDateTimeString();
        $this->save();
    }
}

Client::creating(function ($client) {
    $client->setNullValues();
});

Client::created(function ($client) {
    event(new ClientWasCreated($client));
});

Client::updating(function ($client) {
    $client->setNullValues();
});

Client::updated(function ($client) {
    event(new ClientWasUpdated($client));
});

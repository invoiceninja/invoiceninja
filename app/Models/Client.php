<?php namespace App\Models;

use DB;

use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

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

    public function projects()
    {
        return $this->hasMany('App\Models\Project');
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
        
        $contact = $this->contacts()->first();

        return $contact->getDisplayName();
    }

    public function getEntityType()
    {
        return ENTITY_CLIENT;
    }

    public function getWebsite()
    {
        if (!$this->website) {
            return '';
        }

        $link = $this->website;
        $title = $this->website;
        $prefix = 'http://';

        if (strlen($link) > 7 && substr($link, 0, 7) === $prefix) {
            $title = substr($title, 7);
        } else {
            $link = $prefix.$link;
        }

        return link_to($link, $title, array('target' => '_blank'));
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
}

/*
Client::created(function($client)
{
    Activity::createClient($client);
});
*/

Client::updating(function ($client) {
    Activity::updateClient($client);
});

Client::deleting(function ($client) {
    Activity::archiveClient($client);
});

/*Client::restoring(function ($client) {
    Activity::restoreClient($client);
});
*/
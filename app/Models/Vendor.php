<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use App\Events\VendorWasCreated;
use App\Events\VendorWasUpdated;
use App\Events\VendorWasDeleted;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter    = 'App\Ninja\Presenters\VendorPresenter';
    protected $dates        = ['deleted_at'];
    protected $fillable     = [
        'name',
        'id_number',
        'vat_number',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'currency_id',
        'website',
        'transaction_name',
    ];

    public static $fieldName        = 'name';
    public static $fieldPhone       = 'work_phone';
    public static $fieldAddress1    = 'address1';
    public static $fieldAddress2    = 'address2';
    public static $fieldCity        = 'city';
    public static $fieldState       = 'state';
    public static $fieldPostalCode  = 'postal_code';
    public static $fieldNotes       = 'notes';
    public static $fieldCountry     = 'country';

    public static function getImportColumns()
    {
        return [
            Vendor::$fieldName,
            Vendor::$fieldPhone,
            Vendor::$fieldAddress1,
            Vendor::$fieldAddress2,
            Vendor::$fieldCity,
            Vendor::$fieldState,
            Vendor::$fieldPostalCode,
            Vendor::$fieldCountry,
            Vendor::$fieldNotes,
            VendorContact::$fieldFirstName,
            VendorContact::$fieldLastName,
            VendorContact::$fieldPhone,
            VendorContact::$fieldEmail,
        ];
    }

    public static function getImportMap()
    {
        return [
            'first' => 'first_name',
            'last' => 'last_name',
            'email' => 'email',
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'street2|address2' => 'address2',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
            'note' => 'notes',
        ];
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment');
    }

    public function vendor_contacts()
    {
        return $this->hasMany('App\Models\VendorContact');
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

    public function expenses()
    {
        return $this->hasMany('App\Models\Expense','vendor_id','id');
    }

    public function addVendorContact($data, $isPrimary = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($publicId && $publicId != '-1') {
            $contact = VendorContact::scope($publicId)->firstOrFail();
        } else {
            $contact = VendorContact::createNew();
        }

        $contact->fill($data);
        $contact->is_primary = $isPrimary;

        return $this->vendor_contacts()->save($contact);
    }

    public function getRoute()
    {
        return "/vendors/{$this->public_id}";
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDisplayName()
    {
        return $this->getName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getEntityType()
    {
        return 'vendor';
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

    public function getTotalExpense()
    {
        return DB::table('expenses')
                ->where('vendor_id', '=', $this->id)
                ->whereNull('deleted_at')
                ->sum('amount');
    }
}

Vendor::creating(function ($vendor) {
    $vendor->setNullValues();
});

Vendor::created(function ($vendor) {
    event(new VendorWasCreated($vendor));
});

Vendor::updating(function ($vendor) {
    $vendor->setNullValues();
});

Vendor::updated(function ($vendor) {
    event(new VendorWasUpdated($vendor));
});


Vendor::deleting(function ($vendor) {
    $vendor->setNullValues();
});

Vendor::deleted(function ($vendor) {
    event(new VendorWasDeleted($vendor));
});

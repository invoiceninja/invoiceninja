<?php namespace App\Models;
// vendor
use HTML;

use Illuminate\Database\Eloquent\SoftDeletes;

class VendorContact extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    protected $table = 'vendor_contacts';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'send_invoice',
    ];

    public static $fieldFirstName = 'first_name';
    public static $fieldLastName = 'last_name';
    public static $fieldEmail = 'email';
    public static $fieldPhone = 'phone';

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo('App\Models\Vendor')->withTrashed();
    }

    public function getPersonType()
    {
        return PERSON_VENDOR_CONTACT;
    }

    public function getName()
    {
        return $this->getDisplayName();
    }

    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } else {
            return $this->email;
        }
    }

    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name.' '.$this->last_name;
        } else {
            return '';
        }
    }
}

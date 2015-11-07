<?php namespace App\Models;

use HTML;

use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'send_invoice',
    ];

    public static $fieldFirstName = 'Contact - First Name';
    public static $fieldLastName = 'Contact - Last Name';
    public static $fieldEmail = 'Contact - Email';
    public static $fieldPhone = 'Contact - Phone';

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function getPersonType()
    {
        return PERSON_CONTACT;
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

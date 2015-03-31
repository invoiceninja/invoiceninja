<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public static $fieldFirstName = 'Contact - First Name';
    public static $fieldLastName = 'Contact - Last Name';
    public static $fieldEmail = 'Contact - Email';
    public static $fieldPhone = 'Contact - Phone';

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function getPersonType()
    {
        return PERSON_CONTACT;
    }

    /*
    public function getLastLogin()
    {
        if ($this->last_login == '0000-00-00 00:00:00')
        {
            return '---';
        }
        else
        {
            return $this->last_login->format('m/d/y h:i a');
        }
    }
    */

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

    public function getDetails()
    {
        $str = '';

        if ($this->first_name || $this->last_name) {
            $str .= '<b>'.$this->first_name.' '.$this->last_name.'</b><br/>';
        }

        if ($this->email) {
            $str .= '<i class="fa fa-envelope" style="width: 20px"></i>'.HTML::mailto($this->email, $this->email).'<br/>';
        }

        if ($this->phone) {
            $str .= '<i class="fa fa-phone" style="width: 20px"></i>'.Utils::formatPhoneNumber($this->phone);
        }

        if ($str) {
            $str = '<p>'.$str.'</p>';
        }

        return $str;
    }
}

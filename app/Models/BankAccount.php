<?php namespace App\Models;

use Crypt;
use App\Models\Bank;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_BANK_ACCOUNT;
    }

    public function bank()
    {
        return $this->belongsTo('App\Models\Bank');
    }

    public function bank_subaccounts()
    {
        return $this->hasMany('App\Models\BankSubaccount');
    }
}


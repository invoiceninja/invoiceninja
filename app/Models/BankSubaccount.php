<?php namespace App\Models;

use Crypt;
use App\Models\Bank;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankSubaccount extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_BANK_SUBACCOUNT;
    }

    public function bank_account()
    {
        return $this->belongsTo('App\Models\BankAccount');
    }

}


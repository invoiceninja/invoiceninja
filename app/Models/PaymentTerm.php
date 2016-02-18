<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends EntityModel
{
    //use SoftDeletes;
    
    public $timestamps = true;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_PAYMENT_TERM;
    }
    
}

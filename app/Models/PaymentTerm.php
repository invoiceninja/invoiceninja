<?php namespace App\Models;

class PaymentTerm extends EntityModel
{
    public $timestamps = true;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_PAYMENT_TERM;
    }

}

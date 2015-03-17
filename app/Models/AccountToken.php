<?php namespace App\Models;

class AccountToken extends EntityModel
{
    public function account()
    {
        return $this->belongsTo('Account');
    }
}

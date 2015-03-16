<?php

class AccountToken extends EntityModel
{
    public function account()
    {
        return $this->belongsTo('Account');
    }
}

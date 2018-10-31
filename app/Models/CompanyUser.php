<?php

namespace App\Models;

class CompanyUser extends BaseModel
{
    protected $guarded = ['id'];

    public function account()
    {
        return $this->hasOne(Account::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}

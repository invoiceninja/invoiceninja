<?php

namespace App\Models;

class CompanyUser extends BaseModel
{
    protected $guarded = ['id'];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'settings' => 'collection',
    ];

    public function account()
    {
        return $this->hasOne(Account::class);
    }

    public function user()
    {
        return $this->hasOne(User::class)->withPivot('permissions', 'settings');
    }

    public function company()
    {
    	return $this->hasOne(Company::class)->withPivot('permissions', 'settings');
    }
}

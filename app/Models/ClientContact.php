<?php

namespace App\Models;

use Hashids\Hashids;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;


class ClientContact extends Authenticatable
{
    use Notifiable;

    protected $guard = 'contact';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function setIdAttribute($value)
    {
        $hashids = new Hashids(); //decoded output is _always_ an array.
        $hashed_id_array = $hashids->decode($value);

        $this->attributes['id'] = strtolower($hashed_id_array[0]);
    }

    public function client()
    {
        $this->hasOne(Client::class);
    }

}

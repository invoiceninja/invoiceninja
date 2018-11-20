<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class ClientContact extends Authenticatable
{
    use Notifiable;
    use MakesHash;
    
    protected $appends = ['contact_id'];

    protected $guard = 'contact';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];
    
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    
    public function getRouteKeyName()
    {
        return 'contact_id';
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function client()
    {
        $this->hasOne(Client::class);
    }

    public function primary_contact()
    {
        $this->where('is_primary', true);
    }

}

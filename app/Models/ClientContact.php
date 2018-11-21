<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;


class ClientContact extends Authenticatable
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;

   // protected $appends = ['contact_id'];

    protected $guard = 'contact';

    protected $presenter = 'App\Models\Presenters\ClientContactPresenter';

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'password', 
        'remember_token',
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

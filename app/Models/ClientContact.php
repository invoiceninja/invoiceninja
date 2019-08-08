<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Company;
use App\Models\User;
use App\Notifications\ClientContactResetPassword as ResetPasswordNotification;
use App\Notifications\ClientContactResetPassword;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;


class ClientContact extends Authenticatable
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;

    /* Used to authenticate a contact */
    protected $guard = 'contact';

    /* Deprecated TODO remove*/
    protected $presenter = 'App\Models\Presenters\ClientContactPresenter';

    protected $dates = ['deleted_at'];
    
    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $appends = [
        'hashed_id'
    ];

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
    
    protected $hidden = [
        'password', 
        'remember_token',
    ];


    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'email',
        'avatar',
    ];
    
    /**/
    public function getRouteKeyName()
    {
        return 'contact_id';
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function setAvatarAttribute()
    {
        if(!filter_var($this->attributes['avatar'], FILTER_VALIDATE_URL))
            return url('/') . $this->attributes['avatar'];
        else
            return $this->attributes['avatar'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function primary_contact()
    {
        return $this->where('is_primary', true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ClientContactResetPassword($token));
    }
}

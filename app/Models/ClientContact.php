<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Company;
use App\Models\Language;
use App\Models\User;
use App\Notifications\ClientContactResetPassword as ResetPasswordNotification;
use App\Notifications\ClientContactResetPassword;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laracasts\Presenter\PresentableTrait;

class ClientContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;

    /* Used to authenticate a contact */
    protected $guard = 'contact';

    protected $touches = ['client'];

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $presenter = 'App\Models\Presenters\ClientContactPresenter';

    protected $dates = [
        'deleted_at'
    ];

    protected $appends = [
        'hashed_id'
    ];

    protected $with = [
//        'client',
//        'company'
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'user_id',
        'company_id',
        'client_id',
        'google_2fa_secret',
        'id',
        'oauth_provider_id',
        'oauth_user_id',
        'token',
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
        'is_primary',
        'client_id',
    ];

    public function getEntityType()
    {
        return ClientContact::class;
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    /**/
    public function getRouteKeyName()
    {
        return 'contact_id';
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function setAvatarAttribute($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL) && $value) {
            $this->attributes['avatar'] = url('/') . $value;
        } else {
            $this->attributes['avatar'] = $value;
        }
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
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
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ClientContactResetPassword($token));
    }

    public function preferredLocale()
    {
        
        $languages = Cache::get('languages');

        return $languages->filter(function ($item) {
            return $item->id == $this->client->getSetting('language_id');
        })->first()->locale;

    }

    public function routeNotificationForMail($notification)
    {
        return $this->email;
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value)
    {
        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    /**
     * @return mixed|string
     */
    public function avatar()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return asset('images/svg/user.svg');
    }
}

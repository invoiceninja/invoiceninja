<?php

namespace App\Models;

use App\Models\Traits\UserTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laracasts\Presenter\PresentableTrait;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;
    use PresentableTrait;

    protected $guard = 'user';

    protected $dates = ['deleted_at'];

    protected $presenter = 'App\Models\Presenters\UserPresenter';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'signature',
        'avatar',
        'accepted_terms_version'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token',
        'confirmation_code',
        'oauth_user_id',
        'oauth_provider_id',
        'google_2fa_secret',
        'google_2fa_phone',
        'remember_2fa_token',
        'slack_webhook_url',
    ];

    public function user_accounts()
    {
        return $this->hasMany(UserAccount::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }




    public function owns($entity)
    {
        return ! empty($entity->user_id) && $entity->user_id == $this->id;
    }
}

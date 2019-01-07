<?php

namespace App\Models;

use App\Models\Traits\UserTrait;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use SoftDeletes;
    use PresentableTrait;
    use MakesHash;
    use UserSessionAttributes;

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
        'oauth_user_id',
        'oauth_provider_id',
        'google_2fa_secret',
        'google_2fa_phone',
        'remember_2fa_token',
        'slack_webhook_url',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class)->withPivot('permissions');
    }

    public function company()
    {
        return $this->companies()->where('company_id', $this->getCurrentCompanyId())->first();
    }

    public function permissions()
    {
        return $this->company()->pivot->permissions;
    }

    public function is_admin()
    {
        return $this->company()->pivot->is_admin;
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function owns($entity) : bool
    {
        return ! empty($entity->user_id) && $entity->user_id == $this->id;
    }

    public function permissionsFlat()
    {
        $permissions = json_decode($this->permissions());
        return collect($permissions)->flatten();
    }

    public function permissionsMap()
    {
        $data = [];
        $permissions = json_decode($this->permissions());

        if (! $permissions) 
            return $data;

        $keys = array_values((array) $permissions);
        $values = array_fill(0, count($keys), true);

        return array_combine($keys, $values);
    }
}

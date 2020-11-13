<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Filterable;
use App\Models\Language;
use App\Models\Presenters\UserPresenter;
use App\Models\Traits\UserTrait;
use App\Notifications\ResetPasswordNotification;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use App\Utils\Traits\UserSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laracasts\Presenter\PresentableTrait;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use SoftDeletes;
    use PresentableTrait;
    use MakesHash;
    use UserSessionAttributes;
    use UserSettings;
    use Filterable;
    use HasRelationships;
    use HasFactory;

    protected $guard = 'user';

    protected $dates = ['deleted_at'];

    protected $presenter = UserPresenter::class;

    protected $with = []; // ? companies also

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $company;

    protected $appends = [
        'hashed_id',
    ];

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
        'accepted_terms_version',
        'oauth_user_id',
        'oauth_provider_id',
        'oauth_user_token',
        'oauth_user_refresh_token',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'is_deleted',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token',
        'google_2fa_secret',
        'google_2fa_phone',
        'remember_2fa_token',
        'slack_webhook_url',
    ];

    protected $casts = [
        'oauth_user_token' => 'object',
        'settings'         => 'object',
        'updated_at'       => 'timestamp',
        'created_at'       => 'timestamp',
        'deleted_at'       => 'timestamp',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    /**
     * Returns a account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Returns all company tokens.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tokens()
    {
        return $this->hasMany(CompanyToken::class)->orderBy('id', 'ASC');
    }

    /**
     * Returns all companies a user has access to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class)->using(CompanyUser::class)->withPivot('permissions', 'settings', 'is_admin', 'is_owner', 'is_locked')->withTimestamps();
    }

    /**
     * As we are authenticating on CompanyToken,
     * we need to link the company to the user manually. This allows
     * us to decouple a $user and their attached companies.
     * @param $company
     */
    public function setCompany($company)
    {
        config(['ninja.company_id' => $company->id]);

        $this->company = $company;
    }

    /**
     * Returns the currently set Company.
     */
    public function getCompany()
    {
        if ($this->company) {
            return $this->company;
        }

        return Company::find(config('ninja.company_id'));
    }

    /**
     * Returns the current company.
     *
     * @return Collection
     */
    public function company()
    {
        return $this->getCompany();
    }

    private function setCompanyByGuard()
    {
        if (Auth::guard('contact')->check()) {
            $this->setCompany(auth()->user()->client->company);
        }
    }

    public function company_users()
    {
        return $this->hasMany(CompanyUser::class)->withTrashed();
    }

    public function company_user()
    {
        if (! $this->id && auth()->user()) {
            $this->id = auth()->user()->id;
        }

        // return $this->hasOneThrough(CompanyUser::class, CompanyToken::class, 'user_id', 'company_id', 'id', 'company_id')
        //     ->where('company_user.user_id', $this->id)
        //     ->withTrashed();

        if(request()->header('X-API-TOKEN')){
            return $this->hasOneThrough(CompanyUser::class, CompanyToken::class, 'user_id', 'company_id', 'id', 'company_id')
            ->where('company_tokens.token', request()->header('X-API-TOKEN'))
            ->withTrashed();
        }
        else {

            return $this->hasOneThrough(CompanyUser::class, CompanyToken::class, 'user_id', 'company_id', 'id', 'company_id')
            ->where('company_user.user_id', $this->id)
            ->withTrashed();
        }
    }

    /**
     * Returns the currently set company id for the user.
     *
     * @return int
     */
    public function companyId() :int
    {
        return $this->company()->id;
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Returns a comma separated list of user permissions.
     *
     * @return comma separated list
     */
    public function permissions()
    {
        return $this->company_user->permissions;
    }

    /**
     * Returns a object of User Settings.
     *
     * @return stdClass
     */
    public function settings()
    {
        return json_decode($this->company_user->settings);
    }

    /**
     * Returns a boolean of the administrator status of the user.
     *
     * @return bool
     */
    public function isAdmin() : bool
    {
        return $this->company_user->is_admin;
    }

    public function isOwner() : bool
    {
        return $this->company_user->is_owner;
    }

    /**
     * Returns all user created contacts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    /**
     * Returns a boolean value if the user owns the current Entity.
     *
     * @param  string Entity
     * @return bool
     */
    public function owns($entity) : bool
    {
        return ! empty($entity->user_id) && $entity->user_id == $this->id;
    }

    /**
     * Returns a boolean value if the user is assigned to the current Entity.
     *
     * @param  string Entity
     * @return bool
     */
    public function assigned($entity) : bool
    {
        return ! empty($entity->assigned_user_id) && $entity->assigned_user_id == $this->id;
    }

    /**
     * Returns true if permissions exist in the map.
     *
     * @param  string permission
     * @return bool
     */
    public function hasPermission($permission) : bool
    {
        $parts = explode('_', $permission);
        $all_permission = '';

        if (count($parts) > 1) {
            $all_permission = $parts[0].'_all';
        }

        return  $this->isOwner() ||
                $this->isAdmin() ||
                (stripos($this->company_user->permissions, $all_permission) !== false) ||
                (stripos($this->company_user->permissions, $permission) !== false);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getEmailVerifiedAt()
    {
        if ($this->email_verified_at) {
            return Carbon::parse($this->email_verified_at)->timestamp;
        } else {
            return null;
        }
    }

    public function routeNotificationForSlack($notification)
    {
        if ($this->company_user->slack_webhook_url) {
            return $this->company_user->slack_webhook_url;
        }
    }

    public function routeNotificationForMail($notification)
    {
        return $this->email;
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     * @param null $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = NULL)
    {
        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}

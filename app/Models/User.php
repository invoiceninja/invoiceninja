<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Admin\ResetPasswordObject;
use App\Models\Presenters\UserPresenter;
use App\Notifications\ResetPasswordNotification;
use App\Services\User\UserService;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use App\Utils\Traits\UserSettings;
use App\Utils\TruthSource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use SoftDeletes;
    use PresentableTrait;
    use MakesHash;
    use UserSessionAttributes;
    use UserSettings;
    use Filterable;
    use HasFactory;
    use \Awobaz\Compoships\Compoships;

    protected $guard = 'user';

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
        // 'google_2fa_secret',
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
        'oauth_user_token_expiry' => 'datetime',
    ];

    public function name()
    {
        return $this->first_name.' '.$this->last_name;
    }

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

    public function token()
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyToken()) {
            return $truth->getCompanyToken();
        }

        if (request()->header('X-API-TOKEN')) {
            return CompanyToken::with(['cu'])->where('token', request()->header('X-API-TOKEN'))->first();
        }

        return $this->tokens()->first();
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
        $this->company = $company;

        return $this;
    }

    /**
     * Returns the currently set Company.
     */
    public function getCompany()
    {
        $truth = app()->make(TruthSource::class);

        if ($this->company) {
            return $this->company;
        } elseif ($truth->getCompany()) {
            return $truth->getCompany();
        } elseif (request()->header('X-API-TOKEN')) {
            $company_token = CompanyToken::with(['company'])->where('token', request()->header('X-API-TOKEN'))->first();

            return $company_token->company;
        }

        throw new \Exception('No Company Found');
    }

    public function companyIsSet()
    {
        if ($this->company) {
            return true;
        }

        return false;
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

    public function co_user()
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyUser()) {
            return $truth->getCompanyUser();
        }

        return $this->token()->cu;
    }

    public function company_user()
    {
        if ($this->companyId()) {
            return $this->belongsTo(CompanyUser::class)->where('company_id', $this->companyId())->withTrashed();
        }

        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyUser()) {
            return $truth->getCompanyUser();
        }

        return $this->token()->cu;

        // return $this->hasOneThrough(CompanyUser::class, CompanyToken::class, 'user_id', 'user_id', 'id', 'user_id')
        // ->withTrashed();
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
        return $this->token()->cu->permissions;

        // return $this->company_user->permissions;
    }

    /**
     * Returns a object of User Settings.
     *
     * @return stdClass
     */
    public function settings()
    {
        return json_decode($this->token()->cu->settings);

        //return json_decode($this->company_user->settings);
    }

    /**
     * Returns a boolean of the administrator status of the user.
     *
     * @return bool
     */
    public function isAdmin() : bool
    {
        return $this->token()->cu->is_admin;

        // return $this->company_user->is_admin;
    }

    public function isOwner() : bool
    {
        return $this->token()->cu->is_owner;

        // return $this->company_user->is_owner;
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
                (is_int(stripos($this->token()->cu->permissions, $all_permission))) ||
                (is_int(stripos($this->token()->cu->permissions, $permission)));

        //23-03-2021 - stripos return an int if true and bool false, but 0 is also interpreted as false, so we simply use is_int() to verify state
        // return  $this->isOwner() ||
        //         $this->isAdmin() ||
        //         (stripos($this->company_user->permissions, $all_permission) !== false) ||
        //         (stripos($this->company_user->permissions, $permission) !== false);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isVerified()
    {
        return is_null($this->email_verified_at) ? false : true;
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
        if ($this->token()->cu->slack_webhook_url) {
            return $this->token()->cu->slack_webhook_url;
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
    public function resolveRouteBinding($value, $field = null)
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
        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new ResetPasswordObject($token, $this, $this->account->default_company))->build());
        $nmo->to_user = $this;
        $nmo->settings = $this->account->default_company->settings;
        $nmo->company = $this->account->default_company;

        NinjaMailerJob::dispatch($nmo, true);

        //$this->notify(new ResetPasswordNotification($token));
    }

    public function service()
    {
        return new UserService($this);
    }

    public function translate_entity()
    {
        return ctrans('texts.user');
    }
}

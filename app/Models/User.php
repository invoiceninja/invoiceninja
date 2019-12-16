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
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Filterable;
use App\Models\Language;
use App\Models\Traits\UserTrait;
use App\Models\Users\Upload;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use App\Utils\Traits\UserSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
    use \Staudenmeir\EloquentHasManyDeep\HasRelationships;

    protected $guard = 'user';

    protected $dates = ['deleted_at'];

    protected $presenter = 'App\Models\Presenters\UserPresenter';

    protected $with = ['companies'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $company;

    protected $appends = [
        'hashed_id'
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
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        //'last_login' => 'timestamp',
    ];

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }


    /**
     * Returns a account.
     *
     * @return Collection
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Returns all company tokens.
     *
     * @return Collection
     */
    public function tokens()
    {
        return $this->hasMany(CompanyToken::class)->orderBy('id', 'ASC');
    }

    /**
     * Returns all companies a user has access to.
     *
     * @return Collection
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class)->using(CompanyUser::class)->withPivot('permissions', 'settings', 'is_admin', 'is_owner', 'is_locked');
    }

    /**
    *
    * As we are authenticating on CompanyToken,
    * we need to link the company to the user manually. This allows
    * us to decouple a $user and their attached companies.
    *
    */
    public function setCompany($company)
    {
        $this->company = $company;
    }

    /**
     * Returns the currently set Company
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Returns the current company
     *
     * @return Collection
     */
    public function company()
    {
        return $this->getCompany();
    }

    private function setCompanyByGuard()
    {

        if(Auth::guard('contact')->check())
            $this->setCompany(auth()->user()->client->company);

    }

    public function company_users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function company_user()
    {
        if(!$this->id)
            $this->id = auth()->user()->id;

        return $this->hasOneThrough(CompanyUser::class, CompanyToken::class, 'user_id', 'company_id','id','company_id')->where('company_user.user_id', $this->id);
    }

    /**
     * Returns the currently set company id for the user
     *
     * @return int
     */
    public function companyId() :int
    {

        return $this->company()->id;

    }

    /**
     * Returns a comma separated list of user permissions
     *
     * @return comma separated list
     */
    public function permissions()
    {

        return $this->company_user->permissions;

    }

    /**
     * Returns a object of User Settings
     *
     * @return stdClass
     */
    public function settings()
    {

        return json_decode($this->company_user->settings);

    }

    /**
     * Returns a boolean of the administrator status of the user
     *
     * @return bool
     */
    public function isAdmin() : bool
    {

        return $this->company_user->is_admin;

    }

    /**
     * Returns all user created contacts
     *
     * @return Collection
     */
    public function contacts()
    {

        return $this->hasMany(ClientContact::class);

    }

    /**
     * Returns a boolean value if the user owns the current Entity
     *
     * @param  string Entity
     * @return bool
     */
    public function owns($entity) : bool
    {

        return ! empty($entity->user_id) && $entity->user_id == $this->id;

    }

    /**
     * Returns a boolean value if the user is assigned to the current Entity
     *
     * @param  string Entity
     * @return bool
     */
    public function assigned($entity) : bool
    {

        return ! empty($entity->assigned_user_id) && $entity->assigned_user_id == $this->id;

    }


    /**
     * Returns true if permissions exist in the map
     *
     * @param  string permission
     * @return boolean
     */
    public function hasPermission($permission) : bool
    {

        return (stripos($this->company_user->permissions, $permission) !== false);

    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getEmailVerifiedAt()
    {

        if($this->email_verified_at)
            return Carbon::parse($this->email_verified_at)->timestamp;
        else
            return null;

    }

    public function routeNotificationForSlack($notification)
    {
        //todo need to return the company channel here for hosted users
        //else the env variable for selfhosted
        return config('ninja.notification.slack');
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    /**
     * @return mixed|null
     */
    public function avatar()
    {
        $avatar = $this->uploads()->where('type', Upload::AVATAR)->first();

        if($avatar) {
            return $avatar->path;
        }

        return null; // or default avatar.
    }
}


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
use App\Models\Traits\UserTrait;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use App\Utils\Traits\UserSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

    protected $guard = 'user';

    protected $dates = ['deleted_at'];

    protected $presenter = 'App\Models\Presenters\UserPresenter';

    protected $with = ['companies','user_companies'];

    public $company;

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
     * Returns all one company token.
     * 
     * @return Collection
     */
    public function token()
    {
        return $this->tokens->whereCompanyId($this->company()->id)->first();
        //return $this->hasOne(CompanyToken::class)->orderBy('id', 'ASC');
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

    /**
     * Returns the pivot tables for Company / User
     * 
     * @return Collection
     * 
     */
    public function user_companies()
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Returns the current company by
     * querying directly on the pivot table relationship
     * 
     * @return Collection
     * @deprecated
     */
    public function user_company()
    {
    
        return $this->user_companies->where('company_id', $this->companyId())->first();

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
     * Returns a object of user permissions
     * 
     * @return stdClass
     */
    public function permissions()
    {
        
        $permissions = json_decode($this->user_company()->permissions);
        
        if (! $permissions) 
            return [];

        return $permissions;
    }

    /**
     * Returns a object of User Settings
     * 
     * @return stdClass
     */
    public function settings()
    {

        return json_decode($this->user_company()->settings);

    }

    /**
     * Returns a boolean of the administrator status of the user
     * 
     * @return bool
     */
    public function isAdmin() : bool
    {

        return $this->user_company()->is_admin;

    }

    /**
     * Returns all user created contacts
     * 
     * @return Collection
     */
    public function contacts()
    {

        return $this->hasMany(Contact::class);

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
     * Flattens a stdClass representation of the User Permissions
     * into a Collection
     * 
     * @return Collection
     */
    public function permissionsFlat() :Collection
    {

        return collect($this->permissions())->flatten();

    }

    /**
     * Returns true if permissions exist in the map
     * 
     * @param  string permission
     * @return boolean
     */
    public function hasPermission($permission) : bool
    { 

        return $this->permissionsFlat()->contains($permission);

    }

    /**
     * Returns a array of permission for the mobile application
     * 
     * @return array
     */
    public function permissionsMap() : array
    {
        
        $keys = array_values((array) $this->permissions());
        $values = array_fill(0, count($keys), true);

        return array_combine($keys, $values);

    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

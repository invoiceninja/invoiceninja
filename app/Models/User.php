<?php namespace App\Models;

use Session;
use Event;
use App\Libraries\Utils;
use App\Events\UserSettingsChanged;
use App\Events\UserSignedUp;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class User
 */
class User extends Authenticatable
{
    /**
     * @var array
     */
    public static $all_permissions = [
        'create_all' => 0b0001,
        'view_all' => 0b0010,
        'edit_all' => 0b0100,
    ];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token', 'confirmation_code'];

    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function theme()
    {
        return $this->belongsTo('App\Models\Theme');
    }

    /**
     * @param $value
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $this->attributes['username'] = $value;
    }

    /**
     * @return mixed|string
     */
    public function getName()
    {
        return $this->getDisplayName();
    }

    /**
     * @return mixed
     */
    public function getPersonType()
    {
        return PERSON_USER;
    }

    /**
     * Get the e-mail address where password reminders are sent.
     *
     * @return string
     */
    public function getReminderEmail()
    {
        return $this->email;
    }

    /**
     * @return mixed
     */
    public function isPro()
    {
        return $this->account->isPro();
    }

    /**
     * @param $feature
     * @return mixed
     */
    public function hasFeature($feature)
    {
        return $this->account->hasFeature($feature);
    }

    /**
     * @return mixed
     */
    public function isTrial()
    {
        return $this->account->isTrial();
    }

    /**
     * @param null $plan
     * @return mixed
     */
    public function isEligibleForTrial($plan = null)
    {
        return $this->account->isEligibleForTrial($plan);
    }

    /**
     * @return int
     */
    public function maxInvoiceDesignId()
    {
        return $this->hasFeature(FEATURE_MORE_INVOICE_DESIGNS) ? 11 : (Utils::isNinja() ? COUNT_FREE_DESIGNS : COUNT_FREE_DESIGNS_SELF_HOST);
    }

    /**
     * @return mixed|string
     */
    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } elseif ($this->email) {
            return $this->email;
        } else {
            return 'Guest';
        }
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name.' '.$this->last_name;
        } else {
            return '';
        }
    }

    /**
     * @return bool
     */
    public function showGreyBackground()
    {
        return !$this->theme_id || in_array($this->theme_id, [2, 3, 5, 6, 7, 8, 10, 11, 12]);
    }

    /**
     * @return mixed
     */
    public function getRequestsCount()
    {
        return Session::get(SESSION_COUNTER, 0);
    }

    /**
     * @param bool $success
     * @param bool $forced
     * @return bool
     */
    public function afterSave($success = true, $forced = false)
    {
        if ($this->email) {
            return parent::afterSave($success = true, $forced = false);
        } else {
            return true;
        }
    }

    /**
     * @return mixed
     */
    public function getMaxNumClients()
    {
        if ($this->hasFeature(FEATURE_MORE_CLIENTS)) {
            return MAX_NUM_CLIENTS_PRO;
        }

        if ($this->id < LEGACY_CUTOFF) {
            return MAX_NUM_CLIENTS_LEGACY;
        }

        return MAX_NUM_CLIENTS;
    }

    /**
     * @return mixed
     */
    public function getMaxNumVendors()
    {
        if ($this->hasFeature(FEATURE_MORE_CLIENTS)) {
            return MAX_NUM_VENDORS_PRO;
        }

        return MAX_NUM_VENDORS;
    }

    public function clearSession()
    {
        $keys = [
            SESSION_USER_ACCOUNTS,
            SESSION_TIMEZONE,
            SESSION_DATE_FORMAT,
            SESSION_DATE_PICKER_FORMAT,
            SESSION_DATETIME_FORMAT,
            SESSION_CURRENCY,
            SESSION_LOCALE,
        ];

        foreach ($keys as $key) {
            Session::forget($key);
        }
    }

    /**
     * @param $user
     */
    public static function onUpdatingUser($user)
    {
        if ($user->password != $user->getOriginal('password')) {
            $user->failed_logins = 0;
        }

        // if the user changes their email then they need to reconfirm it
        if ($user->isEmailBeingChanged()) {
            $user->confirmed = 0;
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }
    }

    /**
     * @param $user
     */
    public static function onUpdatedUser($user)
    {
        if (!$user->getOriginal('email')
            || $user->getOriginal('email') == TEST_USERNAME
            || $user->getOriginal('username') == TEST_USERNAME
            || $user->getOriginal('email') == 'tests@bitrock.com') {
            event(new UserSignedUp());
        }

        event(new UserSettingsChanged($user));
    }

    /**
     * @return bool
     */
    public function isEmailBeingChanged()
    {
        return Utils::isNinjaProd()
                && $this->email != $this->getOriginal('email')
                && $this->getOriginal('confirmed');
    }



    /**
     * Set the permissions attribute on the model.
     *
     * @param  mixed  $value
     * @return $this
     */
     protected function setPermissionsAttribute($value){
         if(empty($value)) {
             $this->attributes['permissions'] = 0;
         } else {
             $bitmask = 0;
             foreach($value as $permission){
                if ( ! $permission) {
                    continue;
                }
                $bitmask = $bitmask | static::$all_permissions[$permission];
             }

             $this->attributes['permissions'] = $bitmask;
         }

         return $this;
    }

    /**
     * Expands the value of the permissions attribute
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getPermissionsAttribute($value){
        $permissions = [];
        foreach(static::$all_permissions as $permission => $bitmask){
            if(($value & $bitmask) == $bitmask) {
                $permissions[$permission] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Checks to see if the user has the required permission
     *
     * @param  mixed  $permission Either a single permission or an array of possible permissions
     * @param boolean True to require all permissions, false to require only one
     * @return boolean
     */
    public function hasPermission($permission, $requireAll = false){
        if ($this->is_admin) {
            return true;
        } else if(is_string($permission)){
            return !empty($this->permissions[$permission]);
        } else if(is_array($permission)) {
            if($requireAll){
                return count(array_diff($permission, $this->permissions)) == 0;
            } else {
                return count(array_intersect($permission, $this->permissions)) > 0;
            }
        }

        return false;
    }

    /**
     * @param $entity
     * @return bool
     */
    public function owns($entity) {
        return !empty($entity->user_id) && $entity->user_id == $this->id;
    }

    /**
     * @return bool|mixed
     */
    public function filterId() {
        return $this->hasPermission('view_all') ? false : $this->id;
    }


    public function caddAddUsers()
    {
        if ( ! Utils::isNinjaProd()) {
            return true;
        } elseif ( ! $this->hasFeature(FEATURE_USERS)) {
            return false;
        }

        $account = $this->account;
        $company = $account->company;

        $numUsers = 1;
        foreach ($company->accounts as $account) {
            $numUsers += $account->users->count() - 1;
        }

        return $numUsers < $company->num_users;
    }

    public function canCreateOrEdit($entityType, $entity = false)
    {
        return (($entity && $this->can('edit', $entity))
            || (!$entity && $this->can('create', $entityType)));
    }
}

User::updating(function ($user) {
    User::onUpdatingUser($user);
});

User::updated(function ($user) {
    User::onUpdatedUser($user);
});

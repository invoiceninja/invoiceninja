<?php namespace App\Models;

use Session;
use Auth;
use Event;
use App\Libraries\Utils;
use App\Events\UserSettingsChanged;
use App\Events\UserSignedUp;
use Illuminate\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract {
    public static $all_permissions = array(
        'create_all' => 0b0001,
        'view_all' => 0b0010,
        'edit_all' => 0b0100,
    );    
    
    use Authenticatable, Authorizable, CanResetPassword;

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
    protected $dates = ['deleted_at'];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function theme()
    {
        return $this->belongsTo('App\Models\Theme');
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $this->attributes['username'] = $value;
    }

    public function getName()
    {
        return $this->getDisplayName();
    }

    public function getPersonType()
    {
        return PERSON_USER;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password;
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

    public function isPro()
    {
        return $this->account->isPro();
    }

    public function hasFeature($feature)
    {
        return $this->account->hasFeature($feature);
    }

    public function isPaidPro()
    {
        return $this->isPro($accountDetails) && !$accountDetails['trial'];
    }

    public function isTrial()
    {
        return $this->account->isTrial();
    }

    public function isEligibleForTrial($plan = null)
    {
        return $this->account->isEligibleForTrial($plan);
    }

    public function maxInvoiceDesignId()
    {
        return $this->hasFeature(FEATURE_MORE_INVOICE_DESIGNS) ? 11 : (Utils::isNinja() ? COUNT_FREE_DESIGNS : COUNT_FREE_DESIGNS_SELF_HOST);
    }

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

    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name.' '.$this->last_name;
        } else {
            return '';
        }
    }

    public function showGreyBackground()
    {
        return !$this->theme_id || in_array($this->theme_id, [2, 3, 5, 6, 7, 8, 10, 11, 12]);
    }

    public function getRequestsCount()
    {
        return Session::get(SESSION_COUNTER, 0);
    }
    
    public function afterSave($success = true, $forced = false)
    {
        if ($this->email) {
            return parent::afterSave($success = true, $forced = false);
        } else {
            return true;
        }
    }

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

    public function getMaxNumVendors()
    {
        if ($this->hasFeature(FEATURE_MORE_CLIENTS)) {
            return MAX_NUM_VENDORS_PRO;
        }

        return MAX_NUM_VENDORS;
    }
    
    
    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }

    public function clearSession()
    {
        $keys = [
            RECENTLY_VIEWED,
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
        $permissions = array();
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
    
    public function owns($entity) {
        return !empty($entity->user_id) && $entity->user_id == $this->id;
    }
}

User::updating(function ($user) {
    User::onUpdatingUser($user);
});

User::updated(function ($user) {
    User::onUpdatedUser($user);
});

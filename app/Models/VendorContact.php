<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Models\Presenters\VendorContactPresenter;
use App\Notifications\ClientContactResetPassword;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\VendorContact
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $vendor_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $is_primary
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int $send_email
 * @property string|null $email_verified_at
 * @property string|null $confirmation_code
 * @property int $confirmed
 * @property string|null $last_login
 * @property int|null $failed_logins
 * @property string|null $oauth_user_id
 * @property int|null $oauth_provider_id
 * @property string|null $google_2fa_secret
 * @property string|null $accepted_terms_version
 * @property string|null $avatar
 * @property string|null $avatar_type
 * @property string|null $avatar_size
 * @property string $password
 * @property string|null $token
 * @property int $is_locked
 * @property string|null $contact_key
 * @property string|null $remember_token
 * @property-read \App\Models\Company $company
 * @property-read mixed $contact_id
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $purchase_order_invitations
 * @property-read int|null $purchase_order_invitations_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor $vendor
 * @method static \Database\Factories\VendorContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereAcceptedTermsVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereAvatarSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereAvatarType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereConfirmationCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereContactKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereFailedLogins($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereGoogle2faSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereOauthProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereOauthUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereSendEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact withoutTrashed()
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $purchase_order_invitations
 * @mixin \Eloquent
 */
class VendorContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;
    use HasFactory;

    /* Used to authenticate a vendor */
    protected $guard = 'vendor';

    protected $touches = ['vendor'];

    protected $presenter = VendorContactPresenter::class;

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $appends = [
        'hashed_id',
    ];

    protected $with = [];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
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
        'vendor_id',
        'send_email',
    ];

    public function avatar()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return asset('images/svg/user.svg');
    }

    public function setAvatarAttribute($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_URL) && $value) {
            $this->attributes['avatar'] = url('/').$value;
        } else {
            $this->attributes['avatar'] = $value;
        }
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
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
        // $this->notify(new ClientContactResetPassword($token));
    }

    public function preferredLocale()
    {
        $languages = Cache::get('languages');

        return $languages->filter(function ($item) {
            return $item->id == $this->company->getSetting('language_id');
        })->first()->locale;
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
            // ->company()
            ->where('id', $this->decodePrimaryKey($value))
            ->firstOrFail();
    }

    public function purchase_order_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    public function getLoginLink()
    {
        $domain = isset($this->company->portal_domain) ? $this->company->portal_domain : $this->company->domain();

        return $domain.'/vendor/key_login/'.$this->contact_key;
    }
}

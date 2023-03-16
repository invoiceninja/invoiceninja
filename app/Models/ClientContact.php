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

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\ClientContact\ClientContactResetPasswordObject;
use App\Models\Presenters\ClientContactPresenter;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
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
 * Class ClientContact
 *
 * @method scope() static
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $email
 * @property string|null $email_verified_at
 * @property string|null $confirmation_code
 * @property int $is_primary
 * @property int $confirmed
 * @property int|null $last_login
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
 * @property int $send_email
 * @property string|null $contact_key
 * @property string|null $remember_token
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $credit_invitations
 * @property-read int|null $credit_invitations_count
 * @property-read mixed $contact_id
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invoice_invitations
 * @property-read int|null $invoice_invitations_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $quote_invitations
 * @property-read int|null $quote_invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoiceInvitation> $recurring_invoice_invitations
 * @property-read int|null $recurring_invoice_invitations_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact company()
 * @method static \Database\Factories\ClientContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact query()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereAcceptedTermsVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereAvatarSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereAvatarType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereConfirmationCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereContactKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereFailedLogins($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereGoogle2faSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereLastLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereOauthProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereOauthUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereSendEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $credit_invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invoice_invitations
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $quote_invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoiceInvitation> $recurring_invoice_invitations
 * @mixin \Eloquent
 */
class ClientContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;
    use HasFactory;
    use AppSetup;
    
    /* Used to authenticate a contact */
    protected $guard = 'contact';

    protected $touches = ['client'];

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $presenter = ClientContactPresenter::class;

    protected $appends = [
        'hashed_id',
    ];

    protected $with = [];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'last_login' => 'timestamp',
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
        'send_email',
    ];

    /**
     * Whitelisted fields for using from query parameters on subscriptions request.
     *
     * @var string[]
     */
    public static $subscription_fillable = [
        'first_name',
        'last_name',
        'phone',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'email',
    ];

    /*
    V2 type of scope
     */
    public function scopeCompany($query)
    {
        $query->where('company_id', auth()->user()->companyId());

        return $query;
    }

    /*
     V1 type of scope
     */
    public function scopeScope($query)
    {
        $query->where($this->getTable().'.company_id', '=', auth()->user()->company()->id);

        return $query;
    }

    public function getEntityType()
    {
        return self::class;
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
        if (! filter_var($value, FILTER_VALIDATE_URL) && $value) {
            $this->attributes['avatar'] = url('/').$value;
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

    public function invoice_invitations()
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function recurring_invoice_invitations()
    {
        return $this->hasMany(RecurringInvoiceInvitation::class);
    }

    public function quote_invitations()
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    public function credit_invitations()
    {
        return $this->hasMany(CreditInvitation::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->token = $token;
        $this->save();

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new ClientContactResetPasswordObject($token, $this))->build());
        $nmo->to_user = $this;
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;

        NinjaMailerJob::dispatch($nmo);
    }

    public function preferredLocale()
    {
        $languages = Cache::get('languages');

        if (! $languages) {
            $this->buildCache(true);
        }

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
     * @return mixed|string
     */
    public function avatar()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return asset('images/svg/user.svg');
    }

    /**
     * Provides a convenience login click for contacts to bypass the
     * contact authentication layer
     *
     * @return string URL
     */
    public function getLoginLink()
    {
        // $domain = isset($this->company->portal_domain) ? $this->company->portal_domain : $this->company->domain();

        // return $domain.'/client/key_login/'.$this->contact_key;

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/client/key_login/'.$this->contact_key;
                break;
            case 'iframe':
                return $domain.'/client/key_login/'.$this->contact_key;
                //return $domain . $entity_type .'/'. $this->contact->client->client_hash .'/'. $this->key;
                break;
            case 'domain':
                return $domain.'/client/key_login/'.$this->contact_key;
                break;

            default:
                return '';
                break;
        }
    }
}

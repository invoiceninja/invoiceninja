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
use App\Mail\ClientContact\ClientContactResetPasswordObject;
use App\Models\Presenters\ClientContactPresenter;
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
 * Class ClientContact
 *
 * @method scope() static
 */
class ClientContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;
    use HasFactory;

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
        $domain = isset($this->company->portal_domain) ? $this->company->portal_domain : $this->company->domain();

        return $domain.'/client/key_login/'.$this->contact_key;
    }
}

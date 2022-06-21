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

    protected $with = [
        // 'vendor',
        // 'company'
    ];

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
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    public function purchase_order_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }
}

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
use App\Models\Language;
use App\Models\User;
use App\Notifications\ClientContactResetPassword;
use App\Notifications\ClientContactResetPassword as ResetPasswordNotification;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laracasts\Presenter\PresentableTrait;

class VendorContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;

    /* Used to authenticate a vendor */
    protected $guard = 'vendor';

    protected $touches = ['vendor'];

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $dates = [
        'deleted_at',
    ];

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
    ];

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
        $this->notify(new ClientContactResetPassword($token));
    }

    public function preferredLocale()
    {
        $languages = Cache::get('languages');

        return $languages->filter(function ($item) {
            return $item->id == $this->client->getSetting('language_id');
        })->first()->locale;

        //$lang = Language::find($this->client->getSetting('language_id'));

        //return $lang->locale;
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = NULL)
    {
        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}

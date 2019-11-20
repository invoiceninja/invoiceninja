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

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\CompanyUser;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\GroupSetting;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Timezone;
use App\Models\Traits\AccountTrait;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\CompanySettingsSaver;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laracasts\Presenter\PresentableTrait;

class Company extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use CompanySettingsSaver;

    protected $presenter = 'App\Models\Presenters\CompanyPresenter';

    protected $fillable = [
        'industry_id',
        'domain',
        'size_id',
        'custom_fields',
        'enable_product_cost',
        'enable_product_quantity',
        'default_quantity',
    ];

    protected $hidden = [
        'id',
        'db',
        'ip',
    ];

    protected $casts = [
        'country_id' => 'string',
        'custom_fields' => 'object',
        'settings' => 'object',
        'custom_fields' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [
   //     'tokens'
    ];

    public function getCompanyIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function users()
    {

        return $this->hasManyThrough(User::class, CompanyUser::class, 'company_id', 'id', 'id', 'user_id');

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function groups()
    {
        return $this->hasMany(GroupSetting::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function company_gateways()
    {
        return $this->hasMany(CompanyGateway::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tax_rates()
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        //return $this->belongsTo(Country::class);
        return Country::find($this->settings->country_id);
    }

    public function group_settings()
    {
        return $this->hasMany(GroupSetting::class);
    }

    /**
     * 
     */
    public function timezone()
    {
        return Timezone::find($this->settings->timezone_id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function language()
    {
        return Language::find($this->settings->language_id);
    }

    public function getLocale()
    {
        return isset($this->settings->language_id) && $this->language() ? $this->language()->locale : config('ninja.i18n.locale');
    }

    public function getLogo()
    {
        return $this->settings->company_logo ?: null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * @return mixed
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'account_id', 'id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'account_id', 'id')->withTrashed();
    }

    public function tokens()
    {
        return $this->hasMany(CompanyToken::class);
    }

    public function company_users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function owner()
    {
        $c = $this->company_users->where('is_owner',true)->first();

        return User::find($c->user_id);
    }

    public function resolveRouteBinding($value)
    {
        return $this
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }


    private function isThrottled()
    {
        if (Ninja::isSelfHost()) {
            return false;
        }

        $key = $this->id;

        // http://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
        $day = 60 * 60 * 24;
        $day_limit = $account->getDailyEmailLimit();
        $day_throttle = Cache::get("email_day_throttle:{$key}", null);
        $last_api_request = Cache::get("last_email_request:{$key}", 0);
        $last_api_diff = time() - $last_api_request;

        if (is_null($day_throttle)) {
            $new_day_throttle = 0;
        } else {
            $new_day_throttle = $day_throttle - $last_api_diff;
            $new_day_throttle = $new_day_throttle < 0 ? 0 : $new_day_throttle;
            $new_day_throttle += $day / $day_limit;
            $day_hits_remaining = floor(($day - $new_day_throttle) * $day_limit / $day);
            $day_hits_remaining = $day_hits_remaining >= 0 ? $day_hits_remaining : 0;
        }

        Cache::put("email_day_throttle:{$key}", $new_day_throttle, 60);
        Cache::put("last_email_request:{$key}", time(), 60);

        if ($new_day_throttle > $day) {
            $errorEmail = env('ERROR_EMAIL');
            if ($errorEmail && ! Cache::get("throttle_notified:{$key}")) {
                Mail::raw('Account Throttle: ' . $account->account_key, function ($message) use ($errorEmail, $account) {
                    $message->to($errorEmail)
                            ->from(CONTACT_EMAIL)
                            ->subject("Email throttle triggered for account " . $account->id);
                });
            }
            Cache::put("throttle_notified:{$key}", true, 60 * 24);
            return true;
        }

        return false;
    }
}

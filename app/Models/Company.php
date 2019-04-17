ß<?php

namespace App\Models;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\AccountGateway;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Expense;
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
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Laracasts\Presenter\PresentableTrait;

class Company extends BaseModel
{
    use PresentableTrait;
    use MakesHash;

    protected $presenter = 'App\Models\Presenters\CompanyPresenter';

    protected $guarded = [
        'id',
        'company_id'
    ];

    protected $appends = [
        'settings_object'
    ];

    protected $casts = [
        'settings' => 'object'
    ];

    public function getSettingsObjectAttribute()
    {
        return new CompanySettings($this->settings);
    }

    public function getRouteKeyName()
    {
        return 'company_id';
    }

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
        return $this->hasMany(User::class);
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
        return $this->hasMany(Contact::class);
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
    public function account_gateways()
    {
        return $this->hasMany(AccountGateway::class);
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
        return $this->belongsTo(Country::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
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

}

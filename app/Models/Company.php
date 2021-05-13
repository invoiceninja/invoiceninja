<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Presenters\CompanyPresenter;
use App\Services\Notification\NotificationService;
use App\Utils\Ninja;
use App\Utils\Traits\CompanySettingsSaver;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\ThrottlesEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Support\Facades\Cache;

class Company extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use CompanySettingsSaver;
    use ThrottlesEmail;

    const ENTITY_RECURRING_INVOICE = 'recurring_invoice';
    const ENTITY_CREDIT = 'credit';
    const ENTITY_QUOTE = 'quote';
    const ENTITY_TASK = 'task';
    const ENTITY_EXPENSE = 'expense';
    const ENTITY_PROJECT = 'project';
    const ENTITY_VENDOR = 'vendor';
    const ENTITY_TICKET = 'ticket';
    const ENTITY_PROPOSAL = 'proposal';
    const ENTITY_RECURRING_EXPENSE = 'recurring_expense';
    const ENTITY_RECURRING_TASK = 'task';
    const ENTITY_RECURRING_QUOTE = 'recurring_quote';

    protected $presenter = CompanyPresenter::class;

    protected $fillable = [
        'calculate_expense_tax_by_amount',
        'invoice_expense_documents',
        'invoice_task_documents',
        'show_tasks_table',
        'mark_expenses_invoiceable',
        'mark_expenses_paid',
        'enabled_item_tax_rates',
        'fill_products',
        'industry_id',
        'subdomain',
        'size_id',
        'custom_fields',
        'enable_product_cost',
        'enable_product_quantity',
        'enabled_modules',
        'default_quantity',
        'enabled_tax_rates',
        'portal_mode',
        'portal_domain',
        'convert_products',
        'update_products',
        'custom_surcharge_taxes1',
        'custom_surcharge_taxes2',
        'custom_surcharge_taxes3',
        'custom_surcharge_taxes4',
        'show_product_details',
        'first_day_of_week',
        'first_month_of_year',
        'slack_webhook_url',
        'google_analytics_key',
        'client_can_register',
        'enable_shop_api',
        'invoice_task_timelog',
        'auto_start_tasks',
        'is_disabled',
        'default_task_is_date_based',
        'enable_product_discount',
        'expense_inclusive_taxes',
        'session_timeout',
        'oauth_password_required',
        'invoice_task_datelog',
        'default_password_timeout',
        'show_task_end_date',
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
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [
   //     'tokens'
    ];

    public static $modules = [
        self::ENTITY_RECURRING_INVOICE => 1,
        self::ENTITY_CREDIT => 2,
        self::ENTITY_QUOTE => 4,
        self::ENTITY_TASK => 8,
        self::ENTITY_EXPENSE => 16,
        self::ENTITY_PROJECT => 32,
        self::ENTITY_VENDOR => 64,
        self::ENTITY_TICKET => 128,
        self::ENTITY_PROPOSAL => 256,
        self::ENTITY_RECURRING_EXPENSE => 512,
        self::ENTITY_RECURRING_TASK => 1024,
        self::ENTITY_RECURRING_QUOTE => 2048,
    ];

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function ledger()
    {
        return $this->hasMany(CompanyLedger::class);
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
        return $this->hasManyThrough(User::class, CompanyUser::class, 'company_id', 'id', 'id', 'user_id');
    }

    public function expense_categories()
    {
        return $this->hasMany(ExpenseCategory::class)->withTrashed();
    }
    
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->withTrashed();
    }

    public function task_statuses()
    {
        return $this->hasMany(TaskStatus::class)->withTrashed();
    }

    public function clients()
    {
        return $this->hasMany(Client::class)->withTrashed();
    }
    /**
     * @return HasMany
     */
    public function tasks()
    {
        return $this->hasMany(Task::class)->withTrashed();
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    /**
     * @return HasMany
     */
    public function projects()
    {
        return $this->hasMany(Project::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function vendors()
    {
        return $this->hasMany(Vendor::class)->withTrashed();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(300);
    }

    /**
     * @return HasMany
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
     * @return HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function recurring_invoices()
    {
        return $this->hasMany(RecurringInvoice::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function credits()
    {
        return $this->hasMany(Credit::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function company_gateways()
    {
        return $this->hasMany(CompanyGateway::class);
    }

    /**
     * @return HasMany
     */
    public function tax_rates()
    {
        return $this->hasMany(TaxRate::class);
    }

    /**
     * @return HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsTo
     */
    public function country()
    {
//        return $this->belongsTo(Country::class);
        return Country::find($this->settings->country_id);
    }

    public function group_settings()
    {
        return $this->hasMany(GroupSetting::class);
    }

    public function timezone()
    {
        return Timezone::find($this->settings->timezone_id);
    }

    public function designs()
    {
        return $this->hasMany(Design::class)->whereCompanyId($this->id)->orWhere('company_id', null);
    }

    public function user_designs()
    {
        return $this->hasMany(Design::class);
    }

    public function payment_terms()
    {
        return $this->hasMany(PaymentTerm::class)->whereCompanyId($this->id)->orWhere('company_id', null);
    }

    public function user_payment_terms()
    {
        return $this->hasMany(PaymentTerm::class);
    }

    /**
     * @return BelongsTo
     */
    public function language()
    {
        return Language::find($this->settings->language_id);
    }

    public function getLocale()
    {
        return isset($this->settings->language_id) && $this->language() ? $this->language()->locale : config('ninja.i18n.locale');
    }

    public function getLogo() :?string
    {
        return $this->settings->company_logo ?: null;
    }

    public function locale()
    {
        return $this->getLocale();
    }

    public function getSetting($setting)
    {
        if (property_exists($this->settings, $setting) != false) {
            return $this->settings->{$setting};
        }

        return null;
    }

    public function currency()
    {
        $currencies = Cache::get('currencies');

        return $currencies->filter(function ($item) {
            return $item->id == $this->settings->currency_id;
        })->first();
    }

    /**
     * @return BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * @return BelongsTo
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
        return $this->hasMany(Expense::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payments()
    {
        return $this->hasMany(Payment::class)->withTrashed();
    }

    public function tokens()
    {
        return $this->hasMany(CompanyToken::class);
    }

    public function client_gateway_tokens()
    {
        return $this->hasMany(ClientGatewayToken::class);
    }

    public function system_logs()
    {
        return $this->hasMany(SystemLog::class)->orderBy('id', 'DESC')->take(100);
    }

    public function system_log_relation()
    {
        return $this->hasMany(SystemLog::class)->orderBy('id', 'DESC');
    }

    public function tokens_hashed()
    {
        return $this->hasMany(CompanyToken::class);
    }

    public function company_users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function owner()
    {
        $c = $this->company_users->where('is_owner', true)->first();

        return User::find($c->user_id);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    public function domain()
    {
        if (Ninja::isNinja()) {

            if($this->portal_mode == 'domain')
                return $this->portal_domain;

            return "https://{$this->subdomain}" . config('ninja.app_domain');
        }

        return config('ninja.app_url');
    }

    public function notification(Notification $notification)
    {
        return new NotificationService($this, $notification);
    }

    public function routeNotificationForSlack($notification)
    {
        return $this->slack_webhook_url;
    }
}

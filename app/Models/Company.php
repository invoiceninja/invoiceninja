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

use App\DataMapper\CompanySettings;
use App\Models\Presenters\CompanyPresenter;
use App\Services\Notification\NotificationService;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\CompanySettingsSaver;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\ThrottlesEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\Company
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $industry_id
 * @property string|null $ip
 * @property string $company_key
 * @property int $convert_products
 * @property int $fill_products
 * @property int $update_products
 * @property int $show_product_details
 * @property int $client_can_register
 * @property int $custom_surcharge_taxes1
 * @property int $custom_surcharge_taxes2
 * @property int $custom_surcharge_taxes3
 * @property int $custom_surcharge_taxes4
 * @property int $show_product_cost
 * @property int $enabled_tax_rates
 * @property int $enabled_modules
 * @property int $enable_product_cost
 * @property int $enable_product_quantity
 * @property int $default_quantity
 * @property string|null $subdomain
 * @property string|null $db
 * @property int|null $size_id
 * @property string|null $first_day_of_week
 * @property string|null $first_month_of_year
 * @property string $portal_mode
 * @property string|null $portal_domain
 * @property int $enable_modules
 * @property object $custom_fields
 * @property object $settings
 * @property string $slack_webhook_url
 * @property string $google_analytics_key
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $enabled_item_tax_rates
 * @property int $is_large
 * @property int $enable_shop_api
 * @property string $default_auto_bill
 * @property int $mark_expenses_invoiceable
 * @property int $mark_expenses_paid
 * @property int $invoice_expense_documents
 * @property int $auto_start_tasks
 * @property int $invoice_task_timelog
 * @property int $invoice_task_documents
 * @property int $show_tasks_table
 * @property int $is_disabled
 * @property int $default_task_is_date_based
 * @property int $enable_product_discount
 * @property int $calculate_expense_tax_by_amount
 * @property int $expense_inclusive_taxes
 * @property int $session_timeout
 * @property int $oauth_password_required
 * @property int $invoice_task_datelog
 * @property int $default_password_timeout
 * @property int $show_task_end_date
 * @property int $markdown_enabled
 * @property int $use_comma_as_decimal_place
 * @property int $report_include_drafts
 * @property array|null $client_registration_fields
 * @property int $convert_rate_to_client
 * @property int $markdown_email_enabled
 * @property int $stop_on_unpaid_recurring
 * @property int $use_quote_terms_on_conversion
 * @property int $enable_applying_payments
 * @property int $track_inventory
 * @property int $inventory_notification_threshold
 * @property int $stock_notification
 * @property string|null $matomo_url
 * @property int|null $matomo_id
 * @property int $enabled_expense_tax_rates
 * @property int $invoice_task_project
 * @property int $report_include_deleted
 * @property int $invoice_task_lock
 * @property int $convert_payment_currency
 * @property int $convert_expense_currency
 * @property int $notify_vendor_when_paid
 * @property int $invoice_task_hours
 * @property-read \App\Models\Account $account
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $all_activities
 * @property-read int|null $all_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $all_documents
 * @property-read int|null $all_documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankIntegration> $bank_integrations
 * @property-read int|null $bank_integrations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransactionRule> $bank_transaction_rules
 * @property-read int|null $bank_transaction_rules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransaction> $bank_transactions
 * @property-read int|null $bank_transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $client_contacts
 * @property-read int|null $client_contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientGatewayToken> $client_gateway_tokens
 * @property-read int|null $client_gateway_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyGateway> $company_gateways
 * @property-read int|null $company_gateways_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyUser> $company_users
 * @property-read int|null $company_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read int|null $credits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $designs
 * @property-read int|null $designs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseCategory> $expense_categories
 * @property-read int|null $expense_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $company_id
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $group_settings
 * @property-read int|null $group_settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $groups
 * @property-read int|null $groups_count
 * @property-read \App\Models\Industry|null $industry
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $ledger
 * @property-read int|null $ledger_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $payment_terms
 * @property-read int|null $payment_terms_count
 * @property-read \App\Models\PaymentType|null $payment_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrder> $purchase_orders
 * @property-read int|null $purchase_orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read int|null $quotes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringExpense> $recurring_expenses
 * @property-read int|null $recurring_expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurring_invoices
 * @property-read int|null $recurring_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $schedulers
 * @property-read int|null $schedulers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_log_relation
 * @property-read int|null $system_log_relation_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_logs
 * @property-read int|null $system_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $task_schedulers
 * @property-read int|null $task_schedulers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskStatus> $task_statuses
 * @property-read int|null $task_statuses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxRate> $tax_rates
 * @property-read int|null $tax_rates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens_hashed
 * @property-read int|null $tokens_hashed_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $user_designs
 * @property-read int|null $user_designs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $user_payment_terms
 * @property-read int|null $user_payment_terms_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vendor> $vendors
 * @property-read int|null $vendors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Webhook> $webhooks
 * @property-read int|null $webhooks_count
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\CompanyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereAutoStartTasks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCalculateExpenseTaxByAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereClientCanRegister($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereClientRegistrationFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCompanyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereConvertExpenseCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereConvertPaymentCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereConvertProducts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereConvertRateToClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCustomFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCustomSurchargeTaxes1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCustomSurchargeTaxes2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCustomSurchargeTaxes3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCustomSurchargeTaxes4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDb($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDefaultAutoBill($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDefaultPasswordTimeout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDefaultQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereDefaultTaskIsDateBased($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableApplyingPayments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableModules($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableProductCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableProductDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableProductQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnableShopApi($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnabledExpenseTaxRates($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnabledItemTaxRates($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnabledModules($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEnabledTaxRates($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereExpenseInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereFillProducts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereFirstDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereFirstMonthOfYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereGoogleAnalyticsKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereIndustryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInventoryNotificationThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceExpenseDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskDatelog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskLock($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskProject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereInvoiceTaskTimelog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereIsDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereIsLarge($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMarkExpensesInvoiceable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMarkExpensesPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMarkdownEmailEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMarkdownEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMatomoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereMatomoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereNotifyVendorWhenPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereOauthPasswordRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company wherePortalDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company wherePortalMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereReportIncludeDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereReportIncludeDrafts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSessionTimeout($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereShowProductCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereShowProductDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereShowTaskEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereShowTasksTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSizeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSlackWebhookUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereStockNotification($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereStopOnUnpaidRecurring($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereSubdomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereTrackInventory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUpdateProducts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUseCommaAsDecimalPlace($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUseQuoteTermsOnConversion($value)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $all_activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $all_documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankIntegration> $bank_integrations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransactionRule> $bank_transaction_rules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransaction> $bank_transactions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $client_contacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientGatewayToken> $client_gateway_tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $clients
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyGateway> $company_gateways
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyUser> $company_users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $contacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $designs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseCategory> $expense_categories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $group_settings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $groups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $payment_terms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrder> $purchase_orders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringExpense> $recurring_expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurring_invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $schedulers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_log_relation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_logs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $task_schedulers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskStatus> $task_statuses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxRate> $tax_rates
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens_hashed
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $user_designs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $user_payment_terms
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vendor> $vendors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Webhook> $webhooks
 * @mixin \Eloquent
 */
class Company extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use CompanySettingsSaver;
    use ThrottlesEmail;
    use AppSetup;
    use \Awobaz\Compoships\Compoships;

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
        'invoice_task_hours',
        'markdown_enabled',
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
        'matomo_url',
        'matomo_id',
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
        'use_comma_as_decimal_place',
        'report_include_drafts',
        'client_registration_fields',
        'convert_rate_to_client',
        'markdown_email_enabled',
        'stop_on_unpaid_recurring',
        'use_quote_terms_on_conversion',
        'enable_applying_payments',
        'track_inventory',
        'inventory_notification_threshold',
        'stock_notification',
        'enabled_expense_tax_rates',
        'invoice_task_project',
        'report_include_deleted',
        'invoice_task_lock',
        'convert_payment_currency',
        'convert_expense_currency',
        'notify_vendor_when_paid',
    ];

    protected $hidden = [
        'id',
        'db',
        'ip',
    ];

    protected $casts = [
        'is_proforma' => 'bool',
        'country_id' => 'string',
        'custom_fields' => 'object',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'client_registration_fields' => 'array',
    ];

    protected $with = [];

    public static $modules = [
        self::ENTITY_RECURRING_INVOICE => 1,
        self::ENTITY_CREDIT => 2,
        self::ENTITY_QUOTE => 4,
        // @phpstan-ignore-next-line
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

    public function schedulers()
    {
        return $this->hasMany(Scheduler::class);
    }

    public function task_schedulers() //alias for schedulers
    {
        return $this->hasMany(Scheduler::class);
    }

    public function all_documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function ledger()
    {
        return $this->hasMany(CompanyLedger::class);
    }

    public function bank_integrations()
    {
        return $this->hasMany(BankIntegration::class);
    }

    public function bank_transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function bank_transaction_rules()
    {
        return $this->hasMany(BankTransactionRule::class);
    }

    public function getCompanyIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function client_contacts()
    {
        return $this->hasMany(ClientContact::class)->withTrashed();
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, CompanyUser::class, 'company_id', 'id', 'id', 'user_id')->withTrashed();
    }

    public function expense_categories()
    {
        return $this->hasMany(ExpenseCategory::class)->withTrashed();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->withTrashed();
    }

    public function purchase_orders()
    {
        return $this->hasMany(PurchaseOrder::class)->withTrashed();
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

    public function all_activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
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
    public function recurring_expenses()
    {
        return $this->hasMany(RecurringExpense::class)->withTrashed();
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
        return $this->hasMany(CompanyGateway::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function tax_rates()
    {
        return $this->hasMany(TaxRate::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class)->withTrashed();
    }

    public function country()
    {
        $companies = Cache::get('countries');

        if (! $companies) {
            $this->buildCache(true);

            $companies = Cache::get('countries');
        }

        return $companies->filter(function ($item) {
            return $item->id == $this->getSetting('country_id');
        })->first();

//        return $this->belongsTo(Country::class);
        // return Country::find($this->settings->country_id);
    }

    public function group_settings()
    {
        return $this->hasMany(GroupSetting::class)->withTrashed();
    }

    public function timezone()
    {
        $timezones = Cache::get('timezones');

        if (! $timezones) {
            $this->buildCache(true);
        }

        return $timezones->filter(function ($item) {
            return $item->id == $this->settings->timezone_id;
        })->first();

        // return Timezone::find($this->settings->timezone_id);
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

    public function language()
    {
        $languages = Cache::get('languages');

        //build cache and reinit
        if (! $languages) {
            $this->buildCache(true);
            $languages = Cache::get('languages');
        }

        //if the cache is still dead, get from DB
        if (!$languages && property_exists($this->settings, 'language_id')) {
            return Language::find($this->settings->language_id);
        }

        return $languages->filter(function ($item) {
            return $item->id == $this->settings->language_id;
        })->first();
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

        $cs = CompanySettings::defaults();

        if (property_exists($cs, $setting) != false) {
            return $cs->{$setting};
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
        return $this->hasMany(CompanyUser::class)->withTrashed();
    }

    public function owner()
    {
        return $this->company_users()->withTrashed()->where('is_owner', true)->first()?->user;
    }

    public function credit_rules()
    {
        return BankTransactionRule::query()
                                  ->where('company_id', $this->id)
                                  ->where('applies_to', 'CREDIT')
                                  ->get();
    }

    public function debit_rules()
    {
        return BankTransactionRule::query()
                          ->where('company_id', $this->id)
                          ->where('applies_to', 'DEBIT')
                          ->get();
    }


    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $this->decodePrimaryKey($value))
                    ->where('account_id', auth()->user()->account_id)
                    ->firstOrFail();
    }

    public function domain()
    {
        if (Ninja::isHosted()) {
            if ($this->portal_mode == 'domain' && strlen($this->portal_domain) > 3) {
                return $this->portal_domain;
            }

            return "https://{$this->subdomain}.".config('ninja.app_domain');
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

    public function file_path()
    {
        return $this->company_key.'/';
    }

    public function rBits()
    {
        $user = $this->owner();
        $data = [];

        $data[] = $this->createRBit('business_name', 'user', ['business_name' => $this->present()->name()]);
        $data[] = $this->createRBit('industry_code', 'user', ['industry_detail' => $this->industry ? $this->industry->name : '']);
        $data[] = $this->createRBit('comment', 'partner_database', ['comment_text' => 'Logo image not present']);
        $data[] = $this->createRBit('business_description', 'user', ['business_description' => $this->present()->size()]);

        $data[] = $this->createRBit('person', 'user', ['name' => $user->present()->getFullName()]);
        $data[] = $this->createRBit('email', 'user', ['email' => $user->email]);
        $data[] = $this->createRBit('phone', 'user', ['phone' => $user->phone]);
        $data[] = $this->createRBit('website_uri', 'user', ['uri' => $this->settings->website]);
        $data[] = $this->createRBit('external_account', 'partner_database', ['is_partner_account' => 'yes', 'account_type' => 'Invoice Ninja', 'create_time' => time()]);

        return $data;
    }

    private function createRBit($type, $source, $properties)
    {
        $data = new \stdClass;
        $data->receive_time = time();
        $data->type = $type;
        $data->source = $source;
        $data->properties = new \stdClass;

        foreach ($properties as $key => $val) {
            $data->properties->$key = $val;
        }

        return $data;
    }

    public function timezone_offset()
    {
        $offset = 0;

        $entity_send_time = $this->getSetting('entity_send_time');

        if ($entity_send_time == 0) {
            return 0;
        }

        $timezone = $this->timezone();

        $offset -= $timezone->utc_offset;
        $offset += ($entity_send_time * 3600);

        return $offset;
    }

    public function translate_entity()
    {
        return ctrans('texts.company');
    }

    public function date_format()
    {
        $date_formats = Cache::get('date_formats');

        if (! $date_formats) {
            $this->buildCache(true);
        }

        return $date_formats->filter(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->first()->format;
    }
}

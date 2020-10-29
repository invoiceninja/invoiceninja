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

namespace App\Transformers;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Webhook;
use App\Transformers\CompanyLedgerTransformer;
use App\Transformers\CompanyTokenHashedTransformer;
use App\Transformers\CompanyTokenTransformer;
use App\Transformers\CreditTransformer;
use App\Transformers\DocumentTransformer;
use App\Transformers\ExpenseCategoryTransformer;
use App\Transformers\PaymentTermTransformer;
use App\Transformers\RecurringInvoiceTransformer;
use App\Transformers\SystemLogTransformer;
use App\Transformers\TaskStatusTransformer;
use App\Transformers\TaskTransformer;
use App\Transformers\WebhookTransformer;
use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * Class CompanyTransformer.
 */
class CompanyTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'documents',
        'users',
        'designs',
        'account',
        'clients',
        'contacts',
        'invoices',
        'recurring_invoices',
        'tax_rates',
        'products',
        'country',
        'timezone',
        'language',
        'expenses',
        'vendors',
        'payments',
        'payment_terms',
        'company_user',
        'groups',
        'company_gateways',
        'activities',
        'quotes',
        'credits',
        'projects',
        'tasks',
        'ledger',
        'webhooks',
        'tokens',
        'tokens_hashed',
        'system_logs',
        'expense_categories',
        'task_statuses',
    ];

    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        $std = new stdClass;

        return [
            'id' => (string) $this->encodePrimaryKey($company->id),
            'company_key' => (string) $company->company_key ?: '',
            'update_products' => (bool) $company->update_products,
            'fill_products' => (bool) $company->fill_products,
            'convert_products' => (bool) $company->convert_products,
            'custom_surcharge_taxes1' => (bool) $company->custom_surcharge_taxes1,
            'custom_surcharge_taxes2' => (bool) $company->custom_surcharge_taxes2,
            'custom_surcharge_taxes3' => (bool) $company->custom_surcharge_taxes3,
            'custom_surcharge_taxes4' => (bool) $company->custom_surcharge_taxes4,
            'show_product_cost' => (bool) $company->show_product_cost,
            'enable_product_cost' => (bool) $company->enable_product_cost,
            'show_product_details' => (bool) $company->show_product_details,
            'enable_product_quantity' => (bool) $company->enable_product_quantity,
            'default_quantity' => (bool) $company->default_quantity,
            'custom_fields' => $company->custom_fields ?: $std,
            'size_id' => (string) $company->size_id ?: '',
            'industry_id' => (string) $company->industry_id ?: '',
            'first_month_of_year' => (string) $company->first_month_of_year ?: '',
            'first_day_of_week' => (string) $company->first_day_of_week ?: '',
            'subdomain' => (string) $company->subdomain ?: '',
            'portal_mode' => (string) $company->portal_mode ?: '',
            'portal_domain' => (string) $company->portal_domain ?: '',
            'settings' => $company->settings ?: '',
            'enabled_tax_rates' => (int) $company->enabled_tax_rates,
            'enabled_modules' => (int) $company->enabled_modules,
            'updated_at' => (int) $company->updated_at,
            'archived_at' => (int) $company->deleted_at,
            'created_at' =>(int) $company->created_at,
            'slack_webhook_url' => (string) $company->slack_webhook_url,
            'google_analytics_url' => (string) $company->google_analytics_key, //@deprecate
            'google_analytics_key' => (string) $company->google_analytics_key,
            'enabled_item_tax_rates' => (int) $company->enabled_item_tax_rates,
            'client_can_register' => (bool) $company->client_can_register,
            'is_large' => (bool) $company->is_large,
            'enable_shop_api' => (bool) $company->enable_shop_api,
            'mark_expenses_invoiceable'=> (bool) $company->mark_expenses_invoiceable,
            'mark_expenses_paid' => (bool) $company->mark_expenses_paid,
            'invoice_expense_documents' => (bool) $company->invoice_expense_documents,
            'invoice_task_timelog' => (bool) $company->invoice_task_timelog,
            'auto_start_tasks' => (bool) $company->auto_start_tasks,
            'invoice_task_documents' => (bool) $company->invoice_task_documents,
            'show_tasks_table' => (bool) $company->show_tasks_table,
            'use_credits_payment' => 'always', //todo remove
        ];
    }

    public function includeExpenseCategories(Company $company)
    {
        $transformer = new ExpenseCategoryTransformer($this->serializer);

        return $this->includeCollection($company->expense_categories, $transformer, ExpenseCategory::class);
    }

    public function includeTaskStatuses(Company $company)
    {
        $transformer = new TaskStatusTransformer($this->serializer);

        return $this->includeCollection($company->task_statuses, $transformer, TaskStatus::class);
    }

    public function includeDocuments(Company $company)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($company->documents, $transformer, Document::class);
    }

    public function includeCompanyUser(Company $company)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeItem($company->company_users->where('user_id', auth()->user()->id)->first(), $transformer, CompanyUser::class);
    }

    public function includeTokens(Company $company)
    {
        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeCollection($company->tokens, $transformer, CompanyToken::class);
    }

    public function includeTokensHashed(Company $company)
    {
        $transformer = new CompanyTokenHashedTransformer($this->serializer);

        return $this->includeCollection($company->tokens, $transformer, CompanyToken::class);
    }

    public function includeWebhooks(Company $company)
    {
        $transformer = new WebhookTransformer($this->serializer);

        return $this->includeCollection($company->webhooks, $transformer, Webhook::class);
    }

    public function includeActivities(Company $company)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($company->activities, $transformer, Activity::class);
    }

    public function includeUsers(Company $company)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeCollection($company->users, $transformer, User::class);
    }

    public function includeCompanyGateways(Company $company)
    {
        $transformer = new CompanyGatewayTransformer($this->serializer);

        return $this->includeCollection($company->company_gateways, $transformer, CompanyGateway::class);
    }

    public function includeClients(Company $company)
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeCollection($company->clients, $transformer, Client::class);
    }

    public function includeProjects(Company $company)
    {
        $transformer = new ProjectTransformer($this->serializer);

        return $this->includeCollection($company->projects, $transformer, Project::class);
    }

    public function includeTasks(Company $company)
    {
        $transformer = new TaskTransformer($this->serializer);

        return $this->includeCollection($company->tasks, $transformer, Task::class);
    }

    public function includeExpenses(Company $company)
    {
        $transformer = new ExpenseTransformer($this->serializer);

        return $this->includeCollection($company->expenses, $transformer, Expense::class);
    }

    public function includeVendors(Company $company)
    {
        $transformer = new VendorTransformer($this->serializer);

        return $this->includeCollection($company->vendors, $transformer, Vendor::class);
    }

    public function includeGroups(Company $company)
    {
        $transformer = new GroupSettingTransformer($this->serializer);

        return $this->includeCollection($company->groups, $transformer, GroupSetting::class);
    }

    public function includeInvoices(Company $company)
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeCollection($company->invoices, $transformer, Invoice::class);
    }

    public function includeRecurringInvoices(Company $company)
    {
        $transformer = new RecurringInvoiceTransformer($this->serializer);

        return $this->includeCollection($company->recurring_invoices, $transformer, RecurringInvoice::class);
    }

    public function includeQuotes(Company $company)
    {
        $transformer = new QuoteTransformer($this->serializer);

        return $this->includeCollection($company->quotes, $transformer, Quote::class);
    }

    public function includeCredits(Company $company)
    {
        $transformer = new CreditTransformer($this->serializer);

        return $this->includeCollection($company->credits, $transformer, Credit::class);
    }

    public function includeAccount(Company $company)
    {
        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($company->account, $transformer, Account::class);
    }

    public function includeTaxRates(Company $company)
    {
        $transformer = new TaxRateTransformer($this->serializer);

        return $this->includeCollection($company->tax_rates, $transformer, TaxRate::class);
    }

    public function includeProducts(Company $company)
    {
        $transformer = new ProductTransformer($this->serializer);

        return $this->includeCollection($company->products, $transformer, Product::class);
    }

    public function includePayments(Company $company)
    {
        $transformer = new PaymentTransformer($this->serializer);

        return $this->includeCollection($company->payments, $transformer, Payment::class);
    }

    public function includeDesigns(Company $company)
    {
        $transformer = new DesignTransformer($this->serializer);

        return $this->includeCollection($company->designs()->get(), $transformer, Design::class);
    }

    public function includeLedger(Company $company)
    {
        $transformer = new CompanyLedgerTransformer($this->serializer);

        return $this->includeCollection($company->ledger, $transformer, CompanyLedger::class);
    }

    public function includePaymentTerms(Company $company)
    {
        $transformer = new PaymentTermTransformer($this->serializer);

        return $this->includeCollection($company->payment_terms()->get(), $transformer, PaymentTerm::class);
    }

    public function includeSystemLogs(Company $company)
    {
        $transformer = new SystemLogTransformer($this->serializer);

        return $this->includeCollection($company->system_logs, $transformer, SystemLog::class);
    }
}

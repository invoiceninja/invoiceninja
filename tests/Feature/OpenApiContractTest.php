<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Http\Middleware\PasswordProtection;
use App\Models\Gateway;
use App\Models\User;
use cebe\openapi\spec\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\RoutedServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Validates real API responses against openapi/api-docs.yaml, so the
 * published spec can't silently drift from what the API returns.
 */
class OpenApiContractTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const PASSWORD = 'Contract-Test-Passw0rd!';

    private const LINE_ITEMS = [['product_key' => 'Contract', 'notes' => 'Contract test item', 'cost' => 10, 'quantity' => 2]];

    /**
     * name => [collection path, store/update body, operations to skip].
     * A '{name}' body value is replaced by the hashed id of that entity, which is created on demand.
     *
     * Skipped operations are ones the API does not offer, or known API deviations the spec can't express:
     * - GET /tags/create returns an empty name and entity_type
     * - users have no edit or delete route, client gateway tokens no bulk route
     * - POST /companies returns the new company users instead of the company, so the
     *   company from MockAccountData is used and the store response has a test of its own.
     *   Deleting that company would take the test account with it
     */
    private const ENTITIES = [
        'client' => ['/api/v1/clients', ['name' => 'Contract Test Client']],
        'invoice' => ['/api/v1/invoices', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS]],
        'quote' => ['/api/v1/quotes', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS]],
        'credit' => ['/api/v1/credits', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS]],
        'recurring_invoice' => ['/api/v1/recurring_invoices', ['client_id' => '{client}', 'frequency_id' => '5', 'line_items' => self::LINE_ITEMS]],
        'recurring_quote' => ['/api/v1/recurring_quotes', ['client_id' => '{client}', 'frequency_id' => '5', 'line_items' => self::LINE_ITEMS]],
        'purchase_order' => ['/api/v1/purchase_orders', ['vendor_id' => '{vendor}', 'line_items' => self::LINE_ITEMS]],
        'payment' => ['/api/v1/payments', ['client_id' => '{client}', 'amount' => 10, 'date' => '2026-01-01']],
        'product' => ['/api/v1/products', ['product_key' => 'Contract Test Product', 'price' => 10]],
        'vendor' => ['/api/v1/vendors', ['name' => 'Contract Test Vendor']],
        'expense' => ['/api/v1/expenses', ['amount' => 10, 'vendor_id' => '{vendor}']],
        'recurring_expense' => ['/api/v1/recurring_expenses', ['amount' => 10, 'frequency_id' => '5']],
        'project' => ['/api/v1/projects', ['name' => 'Contract Test Project', 'client_id' => '{client}']],
        'task' => ['/api/v1/tasks', ['description' => 'Contract test task', 'client_id' => '{client}']],
        'expense_category' => ['/api/v1/expense_categories', ['name' => 'Contract Test Category']],
        'tax_rate' => ['/api/v1/tax_rates', ['name' => 'Contract Test Tax', 'rate' => 19]],
        'payment_term' => ['/api/v1/payment_terms', ['name' => 'Contract Test Term', 'num_days' => 45]],
        'task_status' => ['/api/v1/task_statuses', ['name' => 'Contract Test Status']],
        'design' => ['/api/v1/designs', ['name' => 'Contract Test Design', 'design' => ['body' => '<div></div>', 'includes' => '', 'product' => '', 'task' => '', 'footer' => '', 'header' => '']]],
        'group_setting' => ['/api/v1/group_settings', ['name' => 'Contract Test Group', 'settings' => ['currency_id' => '1']]],
        'location' => ['/api/v1/locations', ['name' => 'Contract Test Location', 'client_id' => '{client}']],
        'tag' => ['/api/v1/tags', ['name' => 'Contract Test Tag', 'entity_type' => 'App\\Models\\Invoice'], ['create']],
        'token' => ['/api/v1/tokens', ['name' => 'Contract Test Token']],
        'user' => ['/api/v1/users', ['first_name' => 'Contract', 'last_name' => 'Test', 'email' => 'contract-test@example.com'], ['edit', 'delete']],
        'webhook' => ['/api/v1/webhooks', ['target_url' => 'https://example.com/hook', 'event_id' => '1', 'rest_method' => 'post', 'format' => 'JSON', 'headers' => ['X-Contract-Test' => 'test']]],
        'subscription' => ['/api/v1/subscriptions', ['name' => 'Contract Test Subscription', 'product_ids' => '{product}', 'steps' => 'cart,auth.login-or-register']],
        'bank_integration' => ['/api/v1/bank_integrations', ['bank_account_name' => 'Contract Test Bank']],
        'bank_transaction' => ['/api/v1/bank_transactions', ['bank_integration_id' => '{bank_integration}', 'amount' => 10, 'description' => 'Contract test transaction', 'base_type' => 'CREDIT', 'date' => '2026-01-01']],
        'bank_transaction_rule' => ['/api/v1/bank_transaction_rules', ['name' => 'Contract Test Rule', 'rules' => [['search_key' => 'description', 'operator' => 'contains', 'value' => 'contract']], 'applies_to' => 'CREDIT']],
        'task_scheduler' => ['/api/v1/task_schedulers', ['name' => 'Contract Test Schedule', 'template' => 'client_statement', 'frequency_id' => 5, 'next_run' => '2030-01-01', 'parameters' => ['date_range' => 'last_month', 'show_payments_table' => true, 'show_aging_table' => true, 'status' => 'paid', 'clients' => []]]],
        'company' => ['/api/v1/companies', ['name' => 'Contract Test Company'], ['store', 'bulk', 'delete']],
        'company_gateway' => ['/api/v1/company_gateways', ['gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb', 'config' => '{"publishableKey":"pk_test","apiKey":"sk_test"}']],
        'client_gateway_token' => ['/api/v1/client_gateway_tokens', ['client_id' => '{client}', 'company_gateway_id' => '{company_gateway}', 'gateway_type_id' => '1', 'token' => 'tok_contract', 'meta' => ['brand' => 'visa', 'last4' => '4242']], ['bulk']],
    ];

    /** Collections that can only be listed, validated after an invoice with a document and a payment were created through the API. */
    private const READ_ONLY = ['/api/v1/activities', '/api/v1/company_ledger', '/api/v1/documents'];

    /** Login embeds every record created since created_at and refresh every one changed since updated_at; MockAccountData rows skip the API's defaults. */
    private const NOTHING_CHANGED = ['created_at' => 4102444800, 'updated_at' => 4102444800];

    private const REPORT = ['date_range' => 'all', 'report_keys' => [], 'send_email' => false];

    private const RANGE = ['start_date' => '2020-01-01', 'end_date' => '2035-01-01'];

    /**
     * Operations outside the CRUD pattern: name => [method, spec path, path parameters, body].
     * Path parameters and body values take the same placeholders as the entity bodies.
     */
    private const OTHER = [
        'ping' => ['get', '/api/v1/ping'],
        'health check' => ['get', '/api/v1/health_check'],
        'statics' => ['get', '/api/v1/statics'],
        'refresh' => ['post', '/api/v1/refresh', [], ['updated_at' => self::NOTHING_CHANGED['updated_at']]],
        'login' => ['post', '/api/v1/login', [], ['email' => '{me.email}', 'password' => self::PASSWORD] + self::NOTHING_CHANGED],
        'current company' => ['post', '/api/v1/companies/current'],
        'default company' => ['post', '/api/v1/companies/{company}/default', ['company' => '{company}']],
        'search' => ['post', '/api/v1/search', [], ['search' => 'Contract']],
        'one time token' => ['post', '/api/v1/one_time_token', [], ['context' => 'contract']],
        'last error' => ['get', '/api/v1/last_error'],
        'chart totals' => ['post', '/api/v1/charts/totals', [], self::RANGE],
        'chart totals v2' => ['post', '/api/v1/charts/totals_v2', [], self::RANGE],
        'chart summary v2' => ['post', '/api/v1/charts/chart_summary_v2', [], self::RANGE],
        'chart calculated fields' => ['post', '/api/v1/charts/calculated_fields', [], ['field' => 'active_invoices', 'calculation' => 'sum', 'period' => 'current', 'format' => 'money'] + self::RANGE],
        'invoice action' => ['get', '/api/v1/invoices/{id}/{action}', ['id' => '{invoice}', 'action' => 'mark_sent']],
        'quote action' => ['get', '/api/v1/quotes/{id}/{action}', ['id' => '{quote}', 'action' => 'mark_sent']],
        'purchase order action' => ['get', '/api/v1/purchase_orders/{id}/{action}', ['id' => '{purchase_order}', 'action' => 'mark_sent']],
        'invoice download' => ['get', '/api/v1/invoice/{invitation_key}/download', ['invitation_key' => '{invoice.invitations.0.key}']],
        'invoice download by id' => ['get', '/api/v1/invoices/{id}/download', ['id' => '{invoice}']],
        'quote download' => ['get', '/api/v1/quote/{invitation_key}/download', ['invitation_key' => '{quote.invitations.0.key}']],
        'credit download' => ['get', '/api/v1/credit/{invitation_key}/download', ['invitation_key' => '{credit.invitations.0.key}']],
        'purchase order download' => ['get', '/api/v1/purchase_order/{invitation_key}/download', ['invitation_key' => '{purchase_order.invitations.0.key}']],
        'delivery note' => ['get', '/api/v1/invoices/{id}/delivery_note', ['id' => '{invoice}']],
        'client statement' => ['post', '/api/v1/client_statement', [], ['client_id' => '{client}'] + self::RANGE],
        'preview' => ['post', '/api/v1/preview', [], ['entity' => 'invoice', 'entity_id' => '{invoice}', 'design' => ['design' => ['includes' => '', 'header' => '', 'body' => '<div>Contract</div>', 'product' => '', 'task' => '', 'footer' => '']]]],
        'preview purchase order' => ['post', '/api/v1/preview/purchase_order', [], ['entity' => 'purchase_order', 'entity_id' => '{purchase_order}', 'design' => ['design' => ['includes' => '', 'header' => '', 'body' => '<div>Contract</div>', 'product' => '', 'task' => '', 'footer' => '']]]],
        'template' => ['post', '/api/v1/templates', [], ['entity' => 'invoice', 'entity_id' => '{invoice}', 'template' => 'email_template_invoice', 'subject' => 'Contract', 'body' => 'Contract test']],
        'email' => ['post', '/api/v1/emails', [], ['entity' => 'invoice', 'entity_id' => '{invoice}', 'template' => 'email_template_invoice', 'subject' => 'Contract', 'body' => 'Contract test']],
        'activity notes' => ['post', '/api/v1/activities/notes', [], ['entity' => 'invoices', 'entity_id' => '{invoice}', 'notes' => 'Contract test note']],
        'activity entity' => ['post', '/api/v1/activities/entity', [], ['entity' => 'invoice', 'entity_id' => '{invoice}']],
        'refund' => ['post', '/api/v1/payments/refund', [], ['id' => '{payment}', 'amount' => 5, 'date' => '2026-01-02']],
        'sort tasks' => ['post', '/api/v1/tasks/sort', [], ['status_ids' => ['{task_status}'], 'task_ids' => ['{task_status}' => ['{task}']]]],
        'match transactions' => ['post', '/api/v1/bank_transactions/match', [], ['transactions' => [['id' => '{bank_transaction}', 'vendor_id' => '{vendor}']]]],
        'invite user' => ['post', '/api/v1/users/{user}/invite', ['user' => '{me.hashed_id}']],
        'reconfirm user' => ['post', '/api/v1/user/{user}/reconfirm', ['user' => '{me.hashed_id}']],
        'export' => ['post', '/api/v1/export'],
        'purge client' => ['post', '/api/v1/clients/{id}/purge', ['id' => '{client}']],
        'detach user' => ['delete', '/api/v1/users/{user}/detach_from_company', ['user' => '{user}']],
        'logout' => ['post', '/api/v1/logout'],
        'report contacts' => ['post', '/api/v1/reports/contacts', [], self::REPORT],
        'report clients' => ['post', '/api/v1/reports/clients', [], self::REPORT],
        'report credits' => ['post', '/api/v1/reports/credits', [], self::REPORT],
        'report documents' => ['post', '/api/v1/reports/documents', [], self::REPORT],
        'report expenses' => ['post', '/api/v1/reports/expenses', [], self::REPORT],
        'report invoice items' => ['post', '/api/v1/reports/invoice_items', [], self::REPORT],
        'report invoices' => ['post', '/api/v1/reports/invoices', [], self::REPORT],
        'report payments' => ['post', '/api/v1/reports/payments', [], self::REPORT],
        'report products' => ['post', '/api/v1/reports/products', [], self::REPORT],
        'report product sales' => ['post', '/api/v1/reports/product_sales', [], self::REPORT],
        'report profit and loss' => ['post', '/api/v1/reports/profitloss', [], ['is_income_billed' => true, 'is_expense_billed' => true, 'include_tax' => false] + self::REPORT],
        'report quote items' => ['post', '/api/v1/reports/quote_items', [], self::REPORT],
        'report quotes' => ['post', '/api/v1/reports/quotes', [], self::REPORT],
        'report recurring invoices' => ['post', '/api/v1/reports/recurring_invoices', [], self::REPORT],
        'report tasks' => ['post', '/api/v1/reports/tasks', [], self::REPORT],
        'report activities' => ['post', '/api/v1/reports/activities', [], self::REPORT],
        'report client contacts' => ['post', '/api/v1/reports/client_contacts', [], self::REPORT],
        'report ar detail' => ['post', '/api/v1/reports/ar_detail_report', [], self::REPORT],
        'report ar summary' => ['post', '/api/v1/reports/ar_summary_report', [], self::REPORT],
        'report client balance' => ['post', '/api/v1/reports/client_balance_report', [], self::REPORT],
        'report client sales' => ['post', '/api/v1/reports/client_sales_report', [], self::REPORT],
        'report tax summary' => ['post', '/api/v1/reports/tax_summary_report', [], self::REPORT],
        'report tax period' => ['post', '/api/v1/reports/tax_period_report', [], self::REPORT],
        'report user sales' => ['post', '/api/v1/reports/user_sales_report', [], self::REPORT],
        'report projects' => ['post', '/api/v1/reports/projects', [], self::REPORT],
        'report vendors' => ['post', '/api/v1/reports/vendors', [], self::REPORT],
        'report purchase orders' => ['post', '/api/v1/reports/purchase_orders', [], self::REPORT],
        'report purchase order items' => ['post', '/api/v1/reports/purchase_order_items', [], self::REPORT],
        'report recurring invoice items' => ['post', '/api/v1/reports/recurring_invoice_items', [], self::REPORT],
        'report locations' => ['post', '/api/v1/reports/locations', [], self::REPORT],
        'report client locations' => ['post', '/api/v1/reports/client_locations', [], self::REPORT],
        'chart summary' => ['post', '/api/v1/charts/chart_summary', [], self::RANGE],
        'chart analytics summary' => ['post', '/api/v1/charts/analytics_summary', [], self::RANGE],
        'chart analytics totals' => ['post', '/api/v1/charts/analytics_totals', [], self::RANGE],
        'chart cashflow forecast' => ['post', '/api/v1/charts/cashflow_forecast', [], self::RANGE],
        'chart client payment analytics' => ['post', '/api/v1/charts/client_payment_analytics', [], self::RANGE],
        'chart project analytics' => ['post', '/api/v1/charts/project_analytics/{project}', ['project' => '{project}'], self::RANGE],
        'chart project burnup' => ['post', '/api/v1/charts/project_burnup/{project}', ['project' => '{project}'], self::RANGE],
        'credit action' => ['get', '/api/v1/credits/{id}/{action}', ['id' => '{credit}', 'action' => 'mark_sent']],
        'default design' => ['post', '/api/v1/designs/set/default', [], ['entity' => 'invoice', 'design_id' => '{design}', 'settings_level' => 'company']],
        'client settings' => ['post', '/api/v1/clients/{client}/show_settings', ['client' => '{client}']],
        'update reminders' => ['post', '/api/v1/invoices/update_reminders'],
        'subscription steps' => ['get', '/api/v1/subscriptions/steps'],
        'refresh react' => ['post', '/api/v1/refresh_react', [], self::NOTHING_CHANGED],
        'login precheck' => ['post', '/api/v1/login/precheck', [], ['email' => '{me.email}']],
        'clone company gateway' => ['post', '/api/v1/company_gateways/{company_gateway}/clone', ['company_gateway' => '{company_gateway}']],
        'retry webhook' => ['post', '/api/v1/webhooks/{webhook}/retry', ['webhook' => '{webhook}'], ['entity' => 'invoice', 'entity_id' => '{invoice}']],
        'filters' => ['post', '/api/v1/filters/{entity}', ['entity' => 'invoices']],
        'live preview' => ['post', '/api/v1/live_preview', [], ['entity' => 'invoice', 'entity_id' => '{invoice}', 'client_id' => '{client}', 'line_items' => self::LINE_ITEMS]],
        'payment schedule' => ['post', '/api/v1/invoices/{invoice}/payment_schedule', ['invoice' => '{invoice}'], ['frequency_id' => 5, 'remaining_cycles' => 2, 'next_run' => '2030-01-01']],
        'company logo' => ['get', '/api/v1/companies/{company}/logo', ['company' => '{company}']],
        'show document' => ['get', '/api/v1/documents/{document}', ['document' => '{uploaded.documents.0.id}']],
        'update document' => ['put', '/api/v1/documents/{document}', ['document' => '{uploaded.documents.0.id}'], ['name' => 'contract-renamed.png', 'is_public' => true]],
        'download document' => ['get', '/api/v1/documents/{document}/download', ['document' => '{uploaded.documents.0.id}']],
        'delete document' => ['delete', '/api/v1/documents/{document}', ['document' => '{uploaded.documents.0.id}']],
        'update company user' => ['put', '/api/v1/company_users/{user}', ['user' => '{me.hashed_id}'], ['company_user' => ['notifications' => ['email' => ['invoice_sent']]]]],
    ];

    /**
     * Documented operations no test calls, by the reason why.
     * testEveryDocumentedOperationIsAccountedFor fails when an operation is neither tested nor listed here.
     */
    private const UNTESTED = [
        'calls an external service' => [
            'post /api/v1/bank_integrations/refresh_accounts', 'post /api/v1/bank_integrations/remove_account/{acc_id}',
            'post /api/v1/bank_integrations/get_transactions/{acc_id}', 'post /api/v1/yodlee/refresh', 'post /api/v1/claim_license',
            'post /api/v1/reactivate_email/{bounce_id}', 'post /api/v1/clients/{client}/updateTaxData', 'post /api/v1/connected_account',
            'post /api/v1/support/messages/send', 'post /api/v1/self-update', 'post /api/v1/self-update/check_version',
        ],
        'is a webhook for an external service' => [
            'post /api/v1/postmark_webhook', 'post /api/v1/confirm_purchase', 'post /api/v1/process_webhook',
        ],
        'needs a passkey device' => [
            'post /api/v1/passkeys/login/options', 'get /api/v1/settings/passkeys', 'post /api/v1/settings/passkeys',
            'post /api/v1/settings/passkeys/options', 'delete /api/v1/settings/passkeys/{passkey}',
        ],
        'deletes the company the test runs in' => [
            'delete /api/v1/companies/{id}', 'post /api/v1/companies/purge/{company}', 'post /api/v1/companies/purge_save_settings/{company}',
        ],
        'needs a file or queued job the API cannot produce in a test' => [
            'post /api/v1/preimport', 'post /api/v1/import', 'post /api/v1/import_json', 'post /api/v1/reports/preview/{hash}',
            'post /api/v1/exports/preview/{hash}', 'post /api/v1/templates/preview/{hash}', 'get /api/v1/activities/download_entity/{activity_id}',
            'get /api/v1/system_logs', 'get /api/v1/system_logs/{id}', 'get /api/v1/scheduler', 'get /webcron',
        ],
        'needs a second client' => [
            'post /api/v1/clients/{id}/{mergeable_client_hashed_id}/merge',
        ],
    ];

    /**
     * Routes under /api/v1 the spec does not document, as URI prefixes by the reason why.
     * testEveryRouteIsDocumentedOrListed fails when a route is neither documented nor listed here.
     */
    private const UNDOCUMENTED = [
        'e-invoicing network, needs a provider account' => [
            'einvoice/', 'clients/{client}/peppol_discovery', 'clients/{client}/check_vat', 'clients/{client}/vat_status',
        ],
        'third party integration, needs an account there' => [
            'quickbooks/', 'calendar_connection', 'stripe/', 'yodlee/', 'nordigen/', 'oidc/', 'oauth_login', 'connected_account/gmail',
            'company_gateways/{company_gateway}/import_customers', 'company_gateways/{company_gateway}/test', 'mailer/check', 'smtp/check',
            'companies/updateOriginTaxData/{company}',
        ],
        'webhook for an external service' => [
            'brevo_', 'mailgun_', 'postmark_inbound_webhook', 'ppcp/webhook', 'sns_webhook',
        ],
        'hosted platform only' => [
            'signup', 'check_subdomain', 'check_license', 'accounts/{account}', 'sms_reset', 'verify', 'feedback',
        ],
        'client portal and shop API, authenticated separately' => [
            'shop/', 'contact/',
        ],
        'not documented yet' => [
            'documents', 'system_logs', 'credit/{invitation_key}/download_e_credit', 'invoice/{invitation_key}/download_e_invoice',
            'purchase_order/{invitation_key}/download_e_purchase_order', 'quote/{invitation_key}/download_e_quote', 'password_timeout',
            'settings/enable_two_factor', 'settings/disable_two_factor', 'reset_password', 'users/{user}/disconnect_mailer',
            'users/{user}/disconnect_oauth', 'users/{user}/purge', 'vendors/{vendor}/{mergeable_vendor}/merge', 'live_design',
            'live_preview/purchase_order', 'protected_download/{hash}', 'emails/clientHistory/{client}', 'emails/entityHistory',
            'subscriptions/steps/check', 'clients/{client}/documents', 'client_gateway_tokens/{client_gateway_token}/setAsDefault',
            'company_users/{user}/preferences', 'invoices/{invoice}/payment_schedule', 'edocument/upload',
        ],
    ];

    /** [operation => [method, path suffix]], in the order they run */
    private const OPERATIONS = [
        'store' => ['post', ''],
        'list' => ['get', ''],
        'show' => ['get', '/{id}'],
        'edit' => ['get', '/{id}/edit'],
        'update' => ['put', '/{id}'],
        'create' => ['get', '/create'],
        'upload' => ['post', '/{id}/upload'],
        'bulk' => ['post', '/bulk'],
        'delete' => ['delete', '/{id}'],
    ];

    /** The entities documents can be uploaded to. */
    private const UPLOADS = [
        'client', 'company', 'credit', 'expense', 'group_setting', 'invoice', 'payment', 'product', 'project',
        'purchase_order', 'quote', 'recurring_expense', 'recurring_invoice', 'recurring_quote', 'task', 'vendor',
    ];

    /** hashed ids of the entities created through the API, keyed by name */
    private array $created = [];

    /** the stored representation of those entities, for placeholders such as '{invoice.invitations.0.key}' */
    private array $stored = [];

    private static ?ResponseValidator $parsed = null;

    private ResponseValidator $validator;

    private RoutedServerRequestValidator $requestValidator;

    protected function setUp(): void
    {
        parent::setUp();

        // CI queues on redis with no worker; activities are written by queued listeners.
        config(['queue.default' => 'sync']);

        $this->makeTestData();

        Model::reguard();

        $this->withoutMiddleware(PasswordProtection::class);

        $this->created['company'] = $this->company->hashed_id;

        // Lets the login row sign in as the MockAccountData user.
        $this->user->forceFill(['password' => Hash::make(self::PASSWORD)])->save();

        // On hosted, inviting the only user of an account sends the email from the
        // Invoice Ninja company (NINJA_COMPANY_ID), which tests don't have.
        User::factory()->create(['account_id' => $this->account->id]);

        // Parsing the spec takes a few seconds, so it is shared when tests run in one process.
        $this->validator = self::$parsed ??= (new ValidatorBuilder())->fromYamlFile(base_path('openapi/api-docs.yaml'))->getResponseValidator();
        $this->requestValidator = new RoutedServerRequestValidator($this->validator->getSchema());
    }

    /** Plain data only: phpunit.xml runs tests in separate processes, so rows get serialized. */
    public static function entities(): array
    {
        return array_combine(array_keys(self::ENTITIES), array_map(fn ($name) => [$name], array_keys(self::ENTITIES)));
    }

    #[DataProvider('entities')]
    public function testResponsesMatchSpec(string $entity): void
    {
        [$collection, $body] = self::ENTITIES[$entity];
        $mismatches = [];

        foreach (array_diff_key(self::OPERATIONS, array_flip(self::ENTITIES[$entity][2] ?? [])) as $operation => [$method, $suffix]) {
            if ($operation === 'upload' && ! in_array($entity, self::UPLOADS)) {
                continue;
            }

            $path = $collection.$suffix;
            $sent = match ($operation) {
                'store', 'update' => $body,
                'bulk' => ['action' => 'archive', 'ids' => [$this->created[$entity]]],
                default => null,
            };
            $url = str_replace('{id}', $this->created[$entity] ?? '', $path);
            $response = $operation === 'upload'
                ? $this->upload($url)
                : $this->apiRequest($method, $url.($operation === 'bulk' ? '?action=archive' : ''), $sent ?? []);

            if ($operation === 'store') {
                $this->created[$entity] = $response->json('data.id');
            }

            $mismatch = array_merge(
                $sent === null ? [] : $this->requestMismatch($method, $path, $sent),
                $this->specMismatch($method, $path, $response, $this->created[$entity] ?? null),
            );

            if ($mismatch) {
                $mismatches[] = strtoupper($method)." {$path}:\n  - ".implode("\n  - ", $mismatch);
            }

            // Without a stored entity the remaining operations have nothing to run against.
            if (empty($this->created[$entity])) {
                break;
            }
        }

        $this->assertEmpty($mismatches, "Responses do not match the spec:\n".implode("\n", $mismatches));
    }

    public static function readOnlyCollections(): array
    {
        return array_combine(self::READ_ONLY, array_map(fn ($path) => [$path], self::READ_ONLY));
    }

    #[DataProvider('readOnlyCollections')]
    public function testReadOnlyCollectionMatchesSpec(string $path): void
    {
        $this->upload('/api/v1/invoices/'.$this->created('invoice').'/upload')->assertSuccessful();
        $this->created('payment');

        $mismatch = $this->specMismatch('get', $path, $this->apiRequest('get', $path), null);

        $this->assertEmpty($mismatch, "GET {$path} does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    public static function otherOperations(): array
    {
        return array_combine(array_keys(self::OTHER), array_map(fn ($name) => [$name], array_keys(self::OTHER)));
    }

    #[DataProvider('otherOperations')]
    public function testOperationMatchesSpec(string $name): void
    {
        [$method, $path, $parameters, $body] = self::OTHER[$name] + [2 => [], 3 => []];

        $url = strtr($path, array_combine(
            array_map(fn ($key) => '{'.$key.'}', array_keys($parameters)),
            $this->resolve(array_values($parameters)),
        ));
        $mismatch = array_merge(
            $body ? $this->requestMismatch($method, $path, $body) : [],
            $this->specMismatch($method, $path, $this->apiRequest($method, $url, $body), null),
        );

        $this->assertEmpty($mismatch, strtoupper($method)." {$path} does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    public function testEveryDocumentedOperationIsAccountedFor(): void
    {
        $accounted = array_merge(...array_values(self::UNTESTED));
        $accounted[] = 'post /api/v1/companies';

        foreach (self::ENTITIES as [$collection]) {
            foreach (self::OPERATIONS as [$method, $suffix]) {
                // Skipped operations count: ENTITIES documents why each one is skipped.
                $accounted[] = $method.' '.$collection.$suffix;
            }
        }

        foreach (self::READ_ONLY as $path) {
            $accounted[] = 'get '.$path;
        }

        foreach (self::OTHER as [$method, $path]) {
            $accounted[] = $method.' '.$path;
        }

        $documented = [];
        foreach ($this->validator->getSchema()->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $method) {
                $documented[] = $method.' '.$path;
            }
        }

        $this->assertEmpty(
            array_diff($documented, $accounted),
            "Documented operations that are neither tested nor listed in UNTESTED:\n".implode("\n", array_diff($documented, $accounted)),
        );
        $this->assertEmpty(
            array_diff(array_merge(...array_values(self::UNTESTED)), $documented),
            "UNTESTED lists operations the spec does not document:\n".implode("\n", array_diff(array_merge(...array_values(self::UNTESTED)), $documented)),
        );
    }

    public function testEveryRouteIsDocumentedOrListed(): void
    {
        $normalize = fn (string $path) => preg_replace('/\{\w+\??\}/', '{}', trim($path, '/'));

        $documented = [];
        foreach ($this->validator->getSchema()->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $method) {
                $documented[strtoupper($method).' '.$normalize($path)] = true;
            }
        }

        $prefixes = array_merge(...array_values(self::UNDOCUMENTED));
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            $uri = substr($route->uri(), strlen('api/v1/'));

            foreach (array_intersect($route->methods(), ['GET', 'POST', 'PUT', 'DELETE']) as $method) {
                // Uploads are routed as PUT and documented as the POST that spoofs it.
                $verb = $method === 'PUT' && str_ends_with($uri, '/upload') ? 'POST' : $method;

                $listed = array_filter($prefixes, fn ($prefix) => str_starts_with($uri, $prefix));

                if (! isset($documented[$verb.' '.$normalize($route->uri())]) && ! $listed) {
                    $missing[] = $method.' '.$route->uri();
                }
            }
        }

        $this->assertEmpty($missing, "Routes that are neither documented nor listed in UNDOCUMENTED:\n".implode("\n", $missing));
    }

    public function testDocumentedOperationsAreRouted(): void
    {
        $unrouted = [];

        foreach ($this->validator->getSchema()->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $method) {
                // Uploads are documented as POST with a _method=PUT field, as PHP only parses multipart bodies on POST.
                $verb = str_ends_with($path, '/upload') ? 'PUT' : strtoupper($method);

                try {
                    $routed = ! Route::getRoutes()->match(Request::create(preg_replace('/\{\w+\}/', 'x', $path), $verb))->isFallback;
                } catch (HttpExceptionInterface) {
                    $routed = false;
                }

                if (! $routed) {
                    $unrouted[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertEmpty($unrouted, "The spec documents operations the API does not route:\n".implode("\n", $unrouted));
    }

    public function testCompanyStoreMatchesSpec(): void
    {
        $response = $this->apiRequest('post', '/api/v1/companies', self::ENTITIES['company'][1]);
        $mismatch = $this->specMismatch('post', '/api/v1/companies', $response, null);

        $this->assertEmpty($mismatch, "POST /api/v1/companies does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    /** Seeded data leaves out values real installations have: a license key, and gateways without a site_url. */
    public function testStaticsOfAConfiguredInstallationMatchSpec(): void
    {
        config(['ninja.license_key' => 'contract-test-license']);

        $gateways = Gateway::query()->orderBy('id')->get();
        $gateways->first()->site_url = null;
        app()->instance('gateways', $gateways);

        $mismatch = $this->specMismatch('get', '/api/v1/statics', $this->apiRequest('get', '/api/v1/statics'), null);

        $this->assertEmpty($mismatch, "GET /api/v1/statics does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    private function created(string $entity): string
    {
        [$collection, $body] = self::ENTITIES[$entity];

        if (! isset($this->created[$entity])) {
            $this->stored[$entity] = $this->apiRequest('post', $collection, $body)->assertSuccessful()->json('data');
            $this->created[$entity] = $this->stored[$entity]['id'];
        }

        return $this->created[$entity];
    }

    /**
     * Replaces placeholder values: '{invoice}' is the hashed id of that entity (created when needed),
     * '{invoice.number}' a field of it and '{me.hashed_id}' a field of the MockAccountData user.
     */
    private function resolve(array $body): array
    {
        array_walk_recursive($body, function (&$value) {
            if (is_string($value) && preg_match('/^\{(\w+)(?:\.([\w.]+))?\}$/', $value, $match)) {
                $value = $this->placeholder($match[1], $match[2] ?? null);
            }
        });

        return $body;
    }

    private function placeholder(string $name, ?string $field): mixed
    {
        if ($name === 'me') {
            return data_get($this->user, $field);
        }

        // An invoice with an uploaded document.
        if ($name === 'uploaded') {
            $this->stored[$name] ??= $this->upload('/api/v1/invoices/'.$this->created('invoice').'/upload')->assertSuccessful()->json('data');

            return data_get($this->stored[$name], $field);
        }

        $id = $this->created($name);

        return $field ? data_get($this->stored[$name] ?? [], $field) : $id;
    }

    /** Uploads are multipart POSTs that spoof PUT, as PHP only parses multipart bodies on POST. */
    private function upload(string $url): TestResponse
    {
        Storage::fake(config('filesystems.default'));

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post($url, ['_method' => 'PUT', 'documents' => [UploadedFile::fake()->image('contract.png')]]);
    }

    private function apiRequest(string $method, string $url, array $body = []): TestResponse
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->json(strtoupper($method), $url, $this->resolve($body));
    }

    /** The reasons the request body does not match the spec, empty when it does. */
    private function requestMismatch(string $method, string $path, array $body): array
    {
        if (empty($this->validator->getSchema()->paths->getPath($path)?->{$method}?->requestBody?->content)) {
            return ['the spec documents no request body'];
        }

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest(strtoupper($method), 'http://localhost'.$path)
            ->withQueryParams(isset($body['action']) ? ['action' => $body['action']] : [])
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-API-TOKEN', $this->token)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withBody($factory->createStream(json_encode($this->resolve($body))));

        try {
            $this->requestValidator->validate(new OperationAddress($path, $method), $request);
        } catch (ValidationFailed $e) {
            return array_map(fn ($reason) => 'request: '.$reason, $this->reasons($e));
        }

        return [];
    }

    private function reasons(ValidationFailed $e): array
    {
        $reasons = [];
        for ($ex = $e; $ex; $ex = $ex->getPrevious()) {
            $field = $ex instanceof SchemaMismatch && $ex->dataBreadCrumb() ? ' (at '.implode('.', $ex->dataBreadCrumb()->buildChain()).')' : '';
            $reasons[] = $ex->getMessage().$field;
        }

        return $reasons;
    }

    /** The reasons the response does not match the spec, empty when it does. */
    private function specMismatch(string $method, string $path, TestResponse $response, ?string $id): array
    {
        if (! $response->isSuccessful()) {
            return ["returned HTTP {$response->getStatusCode()}: ".substr($response->getContent(), 0, 300)];
        }

        $body = $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : (string) $response->getContent();
        $content = json_decode($body);
        $json = str_contains((string) $response->headers->get('Content-Type'), 'json');

        // MockAccountData rows skip the API's defaults (e.g. a client's settings.currency_id),
        // so a list is narrowed to the entity created through the API.
        if ($id && is_array($content->data ?? null)) {
            $content->data = array_values(array_filter($content->data, fn ($row) => $row->id === $id));
            $response->baseResponse->setContent(json_encode($content));
        }

        // An empty body validates against any schema, so it would pass without checking anything.
        if ($json ? $content === null || $content === [] || (is_object($content) && property_exists($content, 'data') && empty($content->data)) : $body === '') {
            return ['returned no data to validate'];
        }

        if (empty($this->validator->getSchema()->paths->getPath($path)?->{$method}?->responses?->getResponse('200')?->content)) {
            return ['the spec documents no response body'];
        }

        $factory = new Psr17Factory();
        $psr = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createResponse($response->baseResponse);

        try {
            $this->validator->validate(new OperationAddress($path, $method), $psr);
        } catch (ValidationFailed $e) {
            return $this->reasons($e);
        }

        if (! $json) {
            return [];
        }

        $schema = $this->validator->getSchema()->paths->getPath($path)->{$method}->responses->getResponse('200')->content['application/json']->schema;
        $enveloped = is_object($content) && property_exists($content, 'data');

        if ($enveloped && ! isset($schema->properties['data'])) {
            return ['wraps the response in data, which the spec does not'];
        }

        return array_map(
            fn ($field) => "returns {$field}, which the spec does not document",
            array_unique($enveloped
                ? $this->undocumented($schema->properties['data'] ?? null, $content->data, 'data')
                : $this->undocumented($schema, $content, 'body')),
        );
    }

    /** The returned fields the schema does not list. Validation alone ignores them. */
    private function undocumented(?Schema $schema, mixed $data, string $at): array
    {
        if (is_array($data)) {
            return array_merge([], ...array_map(fn ($row) => $this->undocumented($schema?->items, $row, $at.'.*'), $data));
        }

        // A schema with additionalProperties documents a map, whose keys are data.
        if (! $data instanceof \stdClass || empty($schema?->properties) || isset($schema->getSerializableData()->additionalProperties)) {
            return [];
        }

        $fields = [];
        foreach (get_object_vars($data) as $key => $value) {
            $fields = array_merge($fields, isset($schema->properties[$key])
                ? $this->undocumented($schema->properties[$key], $value, "{$at}.{$key}")
                : ["{$at}.{$key}"]);
        }

        return $fields;
    }
}

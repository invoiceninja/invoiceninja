<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientGatewayTokenController;
use App\Http\Controllers\ClientStatementController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyGatewayController;
use App\Http\Controllers\CompanyLedgerController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\GroupSettingController;
use App\Http\Controllers\HostedMigrationController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportJsonController;
use App\Http\Controllers\InAppPurchase\AppleController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\OneTimeTokenController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentNotificationWebhookController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\PostMarkController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PreviewPurchaseOrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\RecurringQuoteController;
use App\Http\Controllers\Reports\ClientContactReportController;
use App\Http\Controllers\Reports\ClientReportController;
use App\Http\Controllers\Reports\CreditReportController;
use App\Http\Controllers\Reports\DocumentReportController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\InvoiceItemReportController;
use App\Http\Controllers\Reports\InvoiceReportController;
use App\Http\Controllers\Reports\PaymentReportController;
use App\Http\Controllers\Reports\ProductReportController;
use App\Http\Controllers\Reports\ProfitAndLossController;
use App\Http\Controllers\Reports\QuoteItemReportController;
use App\Http\Controllers\Reports\QuoteReportController;
use App\Http\Controllers\Reports\RecurringInvoiceReportController;
use App\Http\Controllers\Reports\TaskReportController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SelfUpdateController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SubdomainController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Support\Messages\SendingController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskSchedulerController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\TwilioController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\WebCronController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['throttle:300,1', 'api_secret_check']], function () {
    Route::post('api/v1/signup', [AccountController::class, 'store'])->name('signup.submit');
    Route::post('api/v1/oauth_login', [LoginController::class, 'oauthApiLogin']);
});

Route::group(['middleware' => ['throttle:10,1','api_secret_check','email_db']], function () {
    Route::post('api/v1/login', [LoginController::class, 'apiLogin'])->name('login.submit')->middleware('throttle:20,1');
    Route::post('api/v1/reset_password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
});

Route::group(['middleware' => ['throttle:300,1', 'api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('account.update');
    Route::post('check_subdomain', [SubdomainController::class, 'index'])->name('check_subdomain');
    Route::get('ping', [PingController::class, 'index'])->name('ping');
    Route::get('health_check', [PingController::class, 'health'])->name('health_check');

    Route::get('activities', [ActivityController::class, 'index']);
    Route::get('activities/download_entity/{activity}', [ActivityController::class, 'downloadHistoricalEntity']);


    Route::post('charts/totals', [ChartController::class, 'totals'])->name('chart.totals');
    Route::post('charts/chart_summary', [ChartController::class, 'chart_summary'])->name('chart.chart_summary');

    Route::post('claim_license', [LicenseController::class, 'index'])->name('license.index');

    Route::resource('clients', ClientController::class); // name = (clients. index / create / show / update / destroy / edit
    Route::put('clients/{client}/adjust_ledger', [ClientController::class, 'adjustLedger'])->name('clients.adjust_ledger');
    Route::put('clients/{client}/upload', [ClientController::class, 'upload'])->name('clients.upload');
    Route::post('clients/{client}/purge', [ClientController::class, 'purge'])->name('clients.purge')->middleware('password_protected');
    Route::post('clients/{client}/{mergeable_client}/merge', [ClientController::class, 'merge'])->name('clients.merge')->middleware('password_protected');
    Route::post('clients/bulk', [ClientController::class, 'bulk'])->name('clients.bulk');

    Route::post('filters/{entity}', [FilterController::class, 'index'])->name('filters');

    Route::resource('client_gateway_tokens', ClientGatewayTokenController::class);

    Route::post('connected_account', [ConnectedAccountController::class, 'index']);
    Route::post('connected_account/gmail', [ConnectedAccountController::class, 'handleGmailOauth']);

    Route::post('client_statement', [ClientStatementController::class, 'statement'])->name('client.statement');

    Route::post('companies/purge/{company}', [MigrationController::class, 'purgeCompany'])->middleware('password_protected');
    Route::post('companies/purge_save_settings/{company}', [MigrationController::class, 'purgeCompanySaveSettings'])->middleware('password_protected');

    Route::resource('companies', CompanyController::class); // name = (companies. index / create / show / update / destroy / edit

    Route::put('companies/{company}/upload', [CompanyController::class, 'upload']);
    Route::post('companies/{company}/default', [CompanyController::class, 'default']);

    Route::get('company_ledger', [CompanyLedgerController::class, 'index'])->name('company_ledger.index');

    Route::resource('company_gateways', CompanyGatewayController::class);
    Route::post('company_gateways/bulk', [CompanyGatewayController::class, 'bulk'])->name('company_gateways.bulk');

    Route::put('company_users/{user}', [CompanyUserController::class, 'update']);

    Route::resource('credits', CreditController::class); // name = (credits. index / create / show / update / destroy / edit
    Route::put('credits/{credit}/upload', [CreditController::class, 'upload'])->name('credits.upload');
    Route::get('credits/{credit}/{action}', [CreditController::class, 'action'])->name('credits.action');
    Route::post('credits/bulk', [CreditController::class, 'bulk'])->name('credits.bulk');

    Route::resource('designs', DesignController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('designs/bulk', [DesignController::class, 'bulk'])->name('designs.bulk');
    Route::post('designs/set/default', [DesignController::class, 'default'])->name('designs.default');

    Route::resource('documents', DocumentController::class); // name = (documents. index / create / show / update / destroy / edit
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('documents/bulk', [DocumentController::class, 'bulk'])->name('documents.bulk');

    Route::post('emails', [EmailController::class, 'send'])->name('email.send')->middleware('user_verified');

    Route::resource('expenses', ExpenseController::class); // name = (expenses. index / create / show / update / destroy / edit
    Route::put('expenses/{expense}/upload', [ExpenseController::class, 'upload']);
    Route::post('expenses/bulk', [ExpenseController::class, 'bulk'])->name('expenses.bulk');

    Route::post('export', [ExportController::class, 'index'])->name('export.index');

    Route::resource('expense_categories', ExpenseCategoryController::class); // name = (expense_categories. index / create / show / update / destroy / edit
    Route::post('expense_categories/bulk', [ExpenseCategoryController::class, 'bulk'])->name('expense_categories.bulk');

    Route::resource('group_settings', GroupSettingController::class);
    Route::post('group_settings/bulk', [GroupSettingController::class, 'bulk']);
    Route::put('group_settings/{group_setting}/upload', [GroupSettingController::class, 'upload'])->name('group_settings.upload');

    Route::post('import', [ImportController::class, 'import'])->name('import.import');
    Route::post('import_json', [ImportJsonController::class, 'import'])->name('import.import_json');
    Route::post('preimport', [ImportController::class, 'preimport'])->name('import.preimport');

    Route::resource('invoices', InvoiceController::class); // name = (invoices. index / create / show / update / destroy / edit
    Route::get('invoices/{invoice}/delivery_note', [InvoiceController::class, 'deliveryNote'])->name('invoices.delivery_note');
    Route::get('invoices/{invoice}/{action}', [InvoiceController::class, 'action'])->name('invoices.action');
    Route::put('invoices/{invoice}/upload', [InvoiceController::class, 'upload'])->name('invoices.upload');
    Route::get('invoice/{invitation_key}/download', [InvoiceController::class, 'downloadPdf'])->name('invoices.downloadPdf');
    Route::post('invoices/bulk', [InvoiceController::class, 'bulk'])->name('invoices.bulk');
    Route::post('invoices/update_reminders', [InvoiceController::class, 'update_reminders'])->name('invoices.update_reminders');

    Route::post('logout', [LogoutController::class, 'index'])->name('logout');

    Route::post('migrate', [MigrationController::class, 'index'])->name('migrate.start');

    Route::post('migration/purge/{company}', [MigrationController::class, 'purgeCompany'])->middleware('password_protected');
    Route::post('migration/purge_save_settings/{company}', [MigrationController::class, 'purgeCompanySaveSettings'])->middleware('password_protected');
    Route::post('migration/start', [MigrationController::class, 'startMigration']);

    Route::post('one_time_token', [OneTimeTokenController::class, 'create']);

    Route::resource('payments', PaymentController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payments/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('payments/bulk', [PaymentController::class, 'bulk'])->name('payments.bulk');
    Route::put('payments/{payment}/upload', [PaymentController::class, 'upload']);

    Route::resource('payment_terms', PaymentTermController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payment_terms/bulk', [PaymentTermController::class, 'bulk'])->name('payment_terms.bulk');

    Route::post('preview', [PreviewController::class, 'show'])->name('preview.show');
    Route::post('live_preview', [PreviewController::class, 'live'])->name('preview.live');

    Route::post('preview/purchase_order', [PreviewPurchaseOrderController::class, 'show'])->name('preview_purchase_order.show');
    Route::post('live_preview/purchase_order', [PreviewPurchaseOrderController::class, 'live'])->name('preview_purchase_order.live');

    Route::resource('products', ProductController::class); // name = (products. index / create / show / update / destroy / edit
    Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
    Route::put('products/{product}/upload', [ProductController::class, 'upload']);

    Route::resource('projects', ProjectController::class); // name = (projects. index / create / show / update / destroy / edit
    Route::post('projects/bulk', [ProjectController::class, 'bulk'])->name('projects.bulk');
    Route::put('projects/{project}/upload', [ProjectController::class, 'upload'])->name('projects.upload');

    Route::resource('quotes', QuoteController::class); // name = (quotes. index / create / show / update / destroy / edit
    Route::get('quotes/{quote}/{action}', [QuoteController::class, 'action'])->name('quotes.action');
    Route::post('quotes/bulk', [QuoteController::class, 'bulk'])->name('quotes.bulk');
    Route::put('quotes/{quote}/upload', [QuoteController::class, 'upload']);

    Route::resource('recurring_expenses', RecurringExpenseController::class);
    Route::post('recurring_expenses/bulk', [RecurringExpenseController::class, 'bulk'])->name('recurring_expenses.bulk');
    Route::put('recurring_expenses/{recurring_expense}/upload', [RecurringExpenseController::class, 'upload']);


    Route::resource('recurring_invoices', RecurringInvoiceController::class); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_invoices/bulk', [RecurringInvoiceController::class, 'bulk'])->name('recurring_invoices.bulk');
    Route::put('recurring_invoices/{recurring_invoice}/upload', [RecurringInvoiceController::class, 'upload']);
    Route::resource('recurring_quotes', RecurringQuoteController::class); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_quotes/bulk', [RecurringQuoteController::class, 'bulk'])->name('recurring_quotes.bulk');
    Route::put('recurring_quotes/{recurring_quote}/upload', [RecurringQuoteController::class, 'upload']);

    Route::post('refresh', [LoginController::class, 'refresh'])->middleware('throttle:300,2');

    Route::post('reports/clients', ClientReportController::class);
    Route::post('reports/contacts', ClientContactReportController::class);
    Route::post('reports/credits', CreditReportController::class);
    Route::post('reports/documents', DocumentReportController::class);
    Route::post('reports/expenses', ExpenseReportController::class);
    Route::post('reports/invoices', InvoiceReportController::class);
    Route::post('reports/invoice_items', InvoiceItemReportController::class);
    Route::post('reports/quotes', QuoteReportController::class);
    Route::post('reports/quote_items', QuoteItemReportController::class);
    Route::post('reports/recurring_invoices', RecurringInvoiceReportController::class);
    Route::post('reports/payments', PaymentReportController::class);
    Route::post('reports/products', ProductReportController::class);
    Route::post('reports/tasks', TaskReportController::class);
    Route::post('reports/profitloss', ProfitAndLossController::class);

    Route::resource('task_scheduler', TaskSchedulerController::class)->except('edit')->parameters(['task_scheduler' => 'scheduler']);

    Route::get('scheduler', [SchedulerController::class, 'index']);
    Route::post('support/messages/send', SendingController::class);

    Route::post('self-update', [SelfUpdateController::class, 'update'])->middleware('password_protected');
    Route::post('self-update/check_version', [SelfUpdateController::class, 'checkVersion']);

    Route::resource('system_logs', SystemLogController::class);

    Route::resource('tasks', TaskController::class); // name = (tasks. index / create / show / update / destroy / edit
    Route::post('tasks/bulk', [TaskController::class, 'bulk'])->name('tasks.bulk');
    Route::put('tasks/{task}/upload', [TaskController::class, 'upload']);
    Route::post('tasks/sort', [TaskController::class, 'sort']);

    Route::resource('task_statuses', TaskStatusController::class); // name = (task_statuses. index / create / show / update / destroy / edit
    Route::post('task_statuses/bulk', [TaskStatusController::class, 'bulk'])->name('task_statuses.bulk');

    Route::resource('tax_rates', TaxRateController::class); // name = (tax_rates. index / create / show / update / destroy / edit
    Route::post('tax_rates/bulk', [TaxRateController::class, 'bulk'])->name('tax_rates.bulk');

    Route::post('templates', [TemplateController::class, 'show'])->name('templates.show');

    Route::resource('tokens', TokenController::class); // name = (tokens. index / create / show / update / destroy / edit
    Route::post('tokens/bulk', [TokenController::class, 'bulk'])->name('tokens.bulk');

    Route::get('settings/enable_two_factor', [TwoFactorController::class, 'setupTwoFactor']);
    Route::post('settings/enable_two_factor', [TwoFactorController::class, 'enableTwoFactor']);
    Route::post('settings/disable_two_factor', [TwoFactorController::class, 'disableTwoFactor']);


    Route::post('verify', [TwilioController::class, 'generate'])->name('verify.generate')->middleware('throttle:100,1');
    Route::post('verify/confirm', [TwilioController::class, 'confirm'])->name('verify.confirm');
    
    Route::resource('vendors', VendorController::class); // name = (vendors. index / create / show / update / destroy / edit
    Route::post('vendors/bulk', [VendorController::class, 'bulk'])->name('vendors.bulk');
    Route::put('vendors/{vendor}/upload', [VendorController::class, 'upload']);

    Route::resource('purchase_orders', PurchaseOrderController::class);
    Route::post('purchase_orders/bulk', [PurchaseOrderController::class, 'bulk'])->name('purchase_orders.bulk');
    Route::put('purchase_orders/{purchase_order}/upload', [PurchaseOrderController::class, 'upload']);

    Route::get('purchase_orders/{purchase_order}/{action}', [PurchaseOrderController::class, 'action'])->name('purchase_orders.action');

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/create', [UserController::class, 'create'])->middleware('password_protected');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('password_protected');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('password_protected');
    Route::post('users', [UserController::class, 'store'])->middleware('password_protected');
    //Route::post('users/{user}/attach_to_company', [UserController::class, 'attach')->middleware('password_protected');
    Route::delete('users/{user}/detach_from_company', [UserController::class, 'detach'])->middleware('password_protected');

    Route::post('users/bulk', [UserController::class, 'bulk'])->name('users.bulk')->middleware('password_protected');
    Route::post('/users/{user}/invite', [UserController::class, 'invite'])->middleware('password_protected');
    Route::post('/user/{user}/reconfirm', [UserController::class, 'reconfirm']);

    Route::resource('webhooks', WebhookController::class);
    Route::post('webhooks/bulk', [WebhookController::class, 'bulk'])->name('webhooks.bulk');

    /*Subscription and Webhook routes */
    // Route::post('hooks', [SubscriptionController::class, 'subscribe'])->name('hooks.subscribe');
    // Route::delete('hooks/{subscription_id}', [SubscriptionController::class, 'unsubscribe'])->name('hooks.unsubscribe');

    Route::post('stripe/update_payment_methods', [StripeController::class, 'update'])->middleware('password_protected')->name('stripe.update');
    Route::post('stripe/import_customers', [StripeController::class, 'import'])->middleware('password_protected')->name('stripe.import');

    Route::post('stripe/verify', [StripeController::class, 'verify'])->middleware('password_protected')->name('stripe.verify');
    Route::post('stripe/disconnect/{company_gateway_id}', [StripeController::class, 'disconnect'])->middleware('password_protected')->name('stripe.disconnect');

    Route::resource('subscriptions', SubscriptionController::class);
    Route::post('subscriptions/bulk', [SubscriptionController::class, 'bulk'])->name('subscriptions.bulk');
    Route::get('statics', StaticController::class);
    // Route::post('apple_pay/upload_file','ApplyPayController::class, 'upload');

});

Route::match(['get', 'post'], 'payment_webhook/{company_key}/{company_gateway_id}', PaymentWebhookController::class)
    ->middleware('throttle:1000,1')
    ->name('payment_webhook');

Route::match(['get', 'post'], 'payment_notification_webhook/{company_key}/{company_gateway_id}/{client}', PaymentNotificationWebhookController::class)
    ->middleware('throttle:1000,1')
    ->name('payment_notification_webhook');

Route::post('api/v1/postmark_webhook', [PostMarkController::class, 'webhook'])->middleware('throttle:1000,1');
Route::get('token_hash_router', [OneTimeTokenController::class, 'router'])->middleware('throttle:100,1');
Route::get('webcron', [WebCronController::class, 'index'])->middleware('throttle:100,1');
Route::post('api/v1/get_migration_account', [HostedMigrationController::class, 'getAccount'])->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/confirm_forwarding', [HostedMigrationController::class, 'confirmForwarding'])->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/process_webhook', [AppleController::class, 'process_webhook'])->middleware('throttle:1000,1');
Route::post('api/v1/confirm_purchase', [AppleController::class, 'confirm_purchase'])->middleware('throttle:1000,1');
Route::fallback([BaseController::class, 'notFound']);
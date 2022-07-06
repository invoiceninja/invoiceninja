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

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['throttle:300,1', 'api_secret_check']], function () {
    Route::post('api/v1/signup', 'AccountController@store')->name('signup.submit');
    Route::post('api/v1/oauth_login', 'Auth\LoginController@oauthApiLogin');
});

Route::group(['middleware' => ['throttle:10,1','api_secret_check','email_db']], function () {
    Route::post('api/v1/login', 'Auth\LoginController@apiLogin')->name('login.submit')->middleware('throttle:20,1');;
    Route::post('api/v1/reset_password', 'Auth\ForgotPasswordController@sendResetLinkEmail');
});

Route::group(['middleware' => ['throttle:100,1', 'api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {
    Route::put('accounts/{account}', 'AccountController@update')->name('account.update');
    Route::post('check_subdomain', 'SubdomainController@index')->name('check_subdomain');
    Route::get('ping', 'PingController@index')->name('ping');
    Route::get('health_check', 'PingController@health')->name('health_check');

    Route::get('activities', 'ActivityController@index');
    Route::get('activities/download_entity/{activity}', 'ActivityController@downloadHistoricalEntity');


    Route::post('charts/totals', 'ChartController@totals')->name('chart.totals');
    Route::post('charts/chart_summary', 'ChartController@chart_summary')->name('chart.chart_summary');

    Route::post('claim_license', 'LicenseController@index')->name('license.index');

    Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit
    Route::put('clients/{client}/adjust_ledger', 'ClientController@adjustLedger')->name('clients.adjust_ledger');
    Route::put('clients/{client}/upload', 'ClientController@upload')->name('clients.upload');
    Route::post('clients/{client}/purge', 'ClientController@purge')->name('clients.purge')->middleware('password_protected');
    Route::post('clients/bulk', 'ClientController@bulk')->name('clients.bulk');

    Route::post('filters/{entity}', 'FilterController@index')->name('filters');

    Route::resource('client_gateway_tokens', 'ClientGatewayTokenController');

    Route::post('connected_account', 'ConnectedAccountController@index');
    Route::post('connected_account/gmail', 'ConnectedAccountController@handleGmailOauth');

    Route::post('client_statement', 'ClientStatementController@statement')->name('client.statement');

    Route::post('companies/purge/{company}', 'MigrationController@purgeCompany')->middleware('password_protected');
    Route::post('companies/purge_save_settings/{company}', 'MigrationController@purgeCompanySaveSettings')->middleware('password_protected');

    Route::resource('companies', 'CompanyController'); // name = (companies. index / create / show / update / destroy / edit

    Route::put('companies/{company}/upload', 'CompanyController@upload');
    Route::post('companies/{company}/default', 'CompanyController@default');

    Route::get('company_ledger', 'CompanyLedgerController@index')->name('company_ledger.index');

    Route::resource('company_gateways', 'CompanyGatewayController');
    Route::post('company_gateways/bulk', 'CompanyGatewayController@bulk')->name('company_gateways.bulk');

    Route::put('company_users/{user}', 'CompanyUserController@update');

    Route::resource('credits', 'CreditController'); // name = (credits. index / create / show / update / destroy / edit
    Route::put('credits/{credit}/upload', 'CreditController@upload')->name('credits.upload');
    Route::get('credits/{credit}/{action}', 'CreditController@action')->name('credits.action');
    Route::post('credits/bulk', 'CreditController@bulk')->name('credits.bulk');

    Route::resource('designs', 'DesignController'); // name = (payments. index / create / show / update / destroy / edit
    Route::post('designs/bulk', 'DesignController@bulk')->name('designs.bulk');
    Route::post('designs/set/default', 'DesignController@default')->name('designs.default');

    Route::resource('documents', 'DocumentController'); // name = (documents. index / create / show / update / destroy / edit
    Route::get('documents/{document}/download', 'DocumentController@download')->name('documents.download');
    Route::post('documents/bulk', 'DocumentController@bulk')->name('documents.bulk');

    Route::post('emails', 'EmailController@send')->name('email.send')->middleware('user_verified');

    Route::resource('expenses', 'ExpenseController'); // name = (expenses. index / create / show / update / destroy / edit
    Route::put('expenses/{expense}/upload', 'ExpenseController@upload');
    Route::post('expenses/bulk', 'ExpenseController@bulk')->name('expenses.bulk');

    Route::post('export', 'ExportController@index')->name('export.index');

    Route::resource('expense_categories', 'ExpenseCategoryController'); // name = (expense_categories. index / create / show / update / destroy / edit
    Route::post('expense_categories/bulk', 'ExpenseCategoryController@bulk')->name('expense_categories.bulk');

    Route::resource('group_settings', 'GroupSettingController');
    Route::post('group_settings/bulk', 'GroupSettingController@bulk');
    Route::put('group_settings/{group_setting}/upload', 'GroupSettingController@upload')->name('group_settings.upload');

    Route::post('import', 'ImportController@import')->name('import.import');
    Route::post('import_json', 'ImportJsonController@import')->name('import.import_json');
    Route::post('preimport', 'ImportController@preimport')->name('import.preimport');

    Route::resource('invoices', 'InvoiceController'); // name = (invoices. index / create / show / update / destroy / edit
    Route::get('invoices/{invoice}/delivery_note', 'InvoiceController@deliveryNote')->name('invoices.delivery_note');
    Route::get('invoices/{invoice}/{action}', 'InvoiceController@action')->name('invoices.action');
    Route::put('invoices/{invoice}/upload', 'InvoiceController@upload')->name('invoices.upload');
    Route::get('invoice/{invitation_key}/download', 'InvoiceController@downloadPdf')->name('invoices.downloadPdf');
    Route::post('invoices/bulk', 'InvoiceController@bulk')->name('invoices.bulk');
    Route::post('invoices/update_reminders', 'InvoiceController@update_reminders')->name('invoices.update_reminders');

    Route::post('logout', 'LogoutController@index')->name('logout');

    Route::post('migrate', 'MigrationController@index')->name('migrate.start');

    Route::post('migration/purge/{company}', 'MigrationController@purgeCompany')->middleware('password_protected');
    Route::post('migration/purge_save_settings/{company}', 'MigrationController@purgeCompanySaveSettings')->middleware('password_protected');
    Route::post('migration/start', 'MigrationController@startMigration');

    Route::post('one_time_token', 'OneTimeTokenController@create');

    Route::resource('payments', 'PaymentController'); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payments/refund', 'PaymentController@refund')->name('payments.refund');
    Route::post('payments/bulk', 'PaymentController@bulk')->name('payments.bulk');
    Route::put('payments/{payment}/upload', 'PaymentController@upload');

    Route::resource('payment_terms', 'PaymentTermController'); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payment_terms/bulk', 'PaymentTermController@bulk')->name('payment_terms.bulk');

    Route::post('preview', 'PreviewController@show')->name('preview.show');
    Route::post('live_preview', 'PreviewController@live')->name('preview.live');

    Route::post('preview/purchase_order', 'PreviewPurchaseOrderController@show')->name('preview_purchase_order.show');
    Route::post('live_preview/purchase_order', 'PreviewPurchaseOrderController@live')->name('preview_purchase_order.live');

    Route::resource('products', 'ProductController'); // name = (products. index / create / show / update / destroy / edit
    Route::post('products/bulk', 'ProductController@bulk')->name('products.bulk');
    Route::put('products/{product}/upload', 'ProductController@upload');

    Route::resource('projects', 'ProjectController'); // name = (projects. index / create / show / update / destroy / edit
    Route::post('projects/bulk', 'ProjectController@bulk')->name('projects.bulk');
    Route::put('projects/{project}/upload', 'ProjectController@upload')->name('projects.upload');

    Route::resource('quotes', 'QuoteController'); // name = (quotes. index / create / show / update / destroy / edit
    Route::get('quotes/{quote}/{action}', 'QuoteController@action')->name('quotes.action');
    Route::post('quotes/bulk', 'QuoteController@bulk')->name('quotes.bulk');
    Route::put('quotes/{quote}/upload', 'QuoteController@upload');

    Route::resource('recurring_expenses', 'RecurringExpenseController');
    Route::post('recurring_expenses/bulk', 'RecurringExpenseController@bulk')->name('recurring_expenses.bulk');
    Route::put('recurring_expenses/{recurring_expense}/upload', 'RecurringExpenseController@upload');


    Route::resource('recurring_invoices', 'RecurringInvoiceController'); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_invoices/bulk', 'RecurringInvoiceController@bulk')->name('recurring_invoices.bulk');
    Route::put('recurring_invoices/{recurring_invoice}/upload', 'RecurringInvoiceController@upload');
    Route::resource('recurring_quotes', 'RecurringQuoteController'); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_quotes/bulk', 'RecurringQuoteController@bulk')->name('recurring_quotes.bulk');
    Route::put('recurring_quotes/{recurring_quote}/upload', 'RecurringQuoteController@upload');

    Route::post('refresh', 'Auth\LoginController@refresh')->middleware('throttle:150,3');

    Route::post('reports/clients', 'Reports\ClientReportController');
    Route::post('reports/contacts', 'Reports\ClientContactReportController');
    Route::post('reports/credits', 'Reports\CreditReportController');
    Route::post('reports/documents', 'Reports\DocumentReportController');
    Route::post('reports/expenses', 'Reports\ExpenseReportController');
    Route::post('reports/invoices', 'Reports\InvoiceReportController');
    Route::post('reports/invoice_items', 'Reports\InvoiceItemReportController');
    Route::post('reports/quotes', 'Reports\QuoteReportController');
    Route::post('reports/quote_items', 'Reports\QuoteItemReportController');
    Route::post('reports/recurring_invoices', 'Reports\RecurringInvoiceReportController');
    Route::post('reports/payments', 'Reports\PaymentReportController');
    Route::post('reports/products', 'Reports\ProductReportController');
    Route::post('reports/tasks', 'Reports\TaskReportController');
    Route::post('reports/profitloss', 'Reports\ProfitAndLossController');


    Route::resource('task_scheduler', 'TaskSchedulerController')->except('edit')->parameters(['task_scheduler' => 'scheduler']);


    Route::get('scheduler', 'SchedulerController@index');
    Route::post('support/messages/send', 'Support\Messages\SendingController');

    Route::post('self-update', 'SelfUpdateController@update')->middleware('password_protected');
    Route::post('self-update/check_version', 'SelfUpdateController@checkVersion');

    Route::resource('system_logs', 'SystemLogController');

    Route::resource('tasks', 'TaskController'); // name = (tasks. index / create / show / update / destroy / edit
    Route::post('tasks/bulk', 'TaskController@bulk')->name('tasks.bulk');
    Route::put('tasks/{task}/upload', 'TaskController@upload');
    Route::post('tasks/sort', 'TaskController@sort');

    Route::resource('task_statuses', 'TaskStatusController'); // name = (task_statuses. index / create / show / update / destroy / edit
    Route::post('task_statuses/bulk', 'TaskStatusController@bulk')->name('task_statuses.bulk');

    Route::resource('tax_rates', 'TaxRateController'); // name = (tax_rates. index / create / show / update / destroy / edit
    Route::post('tax_rates/bulk', 'TaxRateController@bulk')->name('tax_rates.bulk');

    Route::post('templates', 'TemplateController@show')->name('templates.show');

    Route::resource('tokens', 'TokenController'); // name = (tokens. index / create / show / update / destroy / edit
    Route::post('tokens/bulk', 'TokenController@bulk')->name('tokens.bulk');

    Route::get('settings/enable_two_factor', 'TwoFactorController@setupTwoFactor');
    Route::post('settings/enable_two_factor', 'TwoFactorController@enableTwoFactor');
    Route::post('settings/disable_two_factor', 'TwoFactorController@disableTwoFactor');

    Route::resource('vendors', 'VendorController'); // name = (vendors. index / create / show / update / destroy / edit
    Route::post('vendors/bulk', 'VendorController@bulk')->name('vendors.bulk');
    Route::put('vendors/{vendor}/upload', 'VendorController@upload');

    Route::resource('purchase_orders', 'PurchaseOrderController');
    Route::post('purchase_orders/bulk', 'PurchaseOrderController@bulk')->name('purchase_orders.bulk');
    Route::put('purchase_orders/{purchase_order}/upload', 'PurchaseOrderController@upload');

    Route::get('purchase_orders/{purchase_order}/{action}', 'PurchaseOrderController@action')->name('purchase_orders.action');

    Route::get('users', 'UserController@index');
    Route::get('users/create', 'UserController@create')->middleware('password_protected');
    Route::get('users/{user}', 'UserController@show')->middleware('password_protected');
    Route::put('users/{user}', 'UserController@update')->middleware('password_protected');
    Route::post('users', 'UserController@store')->middleware('password_protected');
    //Route::post('users/{user}/attach_to_company', 'UserController@attach')->middleware('password_protected');
    Route::delete('users/{user}/detach_from_company', 'UserController@detach')->middleware('password_protected');

    Route::post('users/bulk', 'UserController@bulk')->name('users.bulk')->middleware('password_protected');
    Route::post('/users/{user}/invite', 'UserController@invite')->middleware('password_protected');
    Route::post('/user/{user}/reconfirm', 'UserController@reconfirm');

    Route::resource('webhooks', 'WebhookController');
    Route::post('webhooks/bulk', 'WebhookController@bulk')->name('webhooks.bulk');

    /*Subscription and Webhook routes */
    // Route::post('hooks', 'SubscriptionController@subscribe')->name('hooks.subscribe');
    // Route::delete('hooks/{subscription_id}', 'SubscriptionController@unsubscribe')->name('hooks.unsubscribe');

    Route::post('stripe/update_payment_methods', 'StripeController@update')->middleware('password_protected')->name('stripe.update');
    Route::post('stripe/import_customers', 'StripeController@import')->middleware('password_protected')->name('stripe.import');

    Route::post('stripe/verify', 'StripeController@verify')->middleware('password_protected')->name('stripe.verify');
    Route::post('stripe/disconnect/{company_gateway_id}', 'StripeController@disconnect')->middleware('password_protected')->name('stripe.disconnect');

    Route::resource('subscriptions', 'SubscriptionController');
    Route::post('subscriptions/bulk', 'SubscriptionController@bulk')->name('subscriptions.bulk');
    Route::get('statics', 'StaticController');
    // Route::post('apple_pay/upload_file','ApplyPayController@upload');

});

Route::match(['get', 'post'], 'payment_webhook/{company_key}/{company_gateway_id}', 'PaymentWebhookController')
    ->middleware(['throttle:1000,1','guest'])
    ->name('payment_webhook');

Route::match(['get', 'post'], 'payment_notification_webhook/{company_key}/{company_gateway_id}/{client}', 'PaymentNotificationWebhookController')
    ->middleware(['throttle:1000,1', 'guest'])
    ->name('payment_notification_webhook');

Route::post('api/v1/postmark_webhook', 'PostMarkController@webhook')->middleware('throttle:1000,1');
Route::get('token_hash_router', 'OneTimeTokenController@router')->middleware('throttle:100,1');
Route::get('webcron', 'WebCronController@index')->middleware('throttle:100,1');
Route::post('api/v1/get_migration_account', 'HostedMigrationController@getAccount')->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/confirm_forwarding', 'HostedMigrationController@confirmForwarding')->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/process_webhook', 'InAppPurchase\AppleController@process_webhook')->middleware('throttle:1000,1');
Route::post('api/v1/confirm_purchase', 'InAppPurchase\AppleController@confirm_purchase')->middleware('throttle:1000,1');
Route::fallback('BaseController@notFound');

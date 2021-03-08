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

Route::group(['middleware' => ['api_secret_check']], function () {
    Route::post('api/v1/signup', 'AccountController@store')->name('signup.submit');
    Route::post('api/v1/oauth_login', 'Auth\LoginController@oauthApiLogin');
});

Route::group(['middleware' => ['api_secret_check', 'email_db']], function () {
    Route::post('api/v1/login', 'Auth\LoginController@apiLogin')->name('login.submit');
    Route::post('api/v1/reset_password', 'Auth\ForgotPasswordController@sendResetLinkEmail');
});

Route::group(['middleware' => ['api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {
    Route::get('ping', 'PingController@index')->name('ping');
    Route::get('health_check', 'PingController@health')->name('health_check');

    Route::get('activities', 'ActivityController@index');
    Route::get('activities/download_entity/{activity}', 'ActivityController@downloadHistoricalEntity');

    Route::post('claim_license', 'LicenseController@index')->name('license.index');

    Route::resource('clients', 'ClientController'); // name = (clients. index / create / show / update / destroy / edit
    Route::put('clients/{client}/upload', 'ClientController@upload')->name('clients.upload');
    Route::post('clients/bulk', 'ClientController@bulk')->name('clients.bulk');

    Route::post('connected_account', 'ConnectedAccountController@index');

    Route::resource('client_statement', 'ClientStatementController@statement'); // name = (client_statement. index / create / show / update / destroy / edit

    Route::post('companies/purge/{company}', 'MigrationController@purgeCompany')->middleware('password_protected');
    Route::post('companies/purge_save_settings/{company}', 'MigrationController@purgeCompanySaveSettings')->middleware('password_protected');
    Route::resource('companies', 'CompanyController'); // name = (companies. index / create / show / update / destroy / edit
    Route::put('companies/{company}/upload', 'CompanyController@upload');

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


    Route::resource('documents', 'DocumentController'); // name = (documents. index / create / show / update / destroy / edit
    Route::get('documents/{document}/download', 'DocumentController@download')->name('documents.download');
    Route::post('documents/bulk', 'DocumentController@bulk')->name('documents.bulk');

    Route::post('emails', 'EmailController@send')->name('email.send')->middleware('user_verified');

    Route::resource('expenses', 'ExpenseController'); // name = (expenses. index / create / show / update / destroy / edit
    Route::put('expenses/{expense}/upload', 'ExpenseController@upload');
    Route::post('expenses/bulk', 'ExpenseController@bulk')->name('expenses.bulk');

    Route::resource('expense_categories', 'ExpenseCategoryController'); // name = (expense_categories. index / create / show / update / destroy / edit
    Route::post('expense_categories/bulk', 'ExpenseCategoryController@bulk')->name('expense_categories.bulk');

    Route::resource('group_settings', 'GroupSettingController');
    Route::post('group_settings/bulk', 'GroupSettingController@bulk');

    Route::post('import', 'ImportController@import')->name('import.import');
    Route::post('preimport', 'ImportController@preimport')->name('import.preimport');

    Route::resource('invoices', 'InvoiceController'); // name = (invoices. index / create / show / update / destroy / edit
    Route::get('invoices/{invoice}/delivery_note', 'InvoiceController@deliveryNote')->name('invoices.delivery_note');
    Route::get('invoices/{invoice}/{action}', 'InvoiceController@action')->name('invoices.action');
    Route::put('invoices/{invoice}/upload', 'InvoiceController@upload')->name('invoices.upload');
    Route::get('invoice/{invitation_key}/download', 'InvoiceController@downloadPdf')->name('invoices.downloadPdf');
    Route::post('invoices/bulk', 'InvoiceController@bulk')->name('invoices.bulk');

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

    Route::resource('recurring_invoices', 'RecurringInvoiceController'); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_invoices/bulk', 'RecurringInvoiceController@bulk')->name('recurring_invoices.bulk');
    Route::put('recurring_invoices/{recurring_invoice}/upload', 'RecurringInvoiceController@upload');
    Route::resource('recurring_quotes', 'RecurringQuoteController'); // name = (recurring_invoices. index / create / show / update / destroy / edit

    Route::post('recurring_quotes/bulk', 'RecurringQuoteController@bulk')->name('recurring_quotes.bulk');

    Route::post('refresh', 'Auth\LoginController@refresh');

    Route::get('scheduler', 'SchedulerController@index');
    Route::post('support/messages/send', 'Support\Messages\SendingController');

    Route::post('self-update', 'SelfUpdateController@update')->middleware('password_protected');
    Route::post('self-update/check_version', 'SelfUpdateController@checkVersion');

    Route::resource('system_logs', 'SystemLogController');

    Route::resource('tasks', 'TaskController'); // name = (tasks. index / create / show / update / destroy / edit
    Route::post('tasks/bulk', 'TaskController@bulk')->name('tasks.bulk');
    Route::put('tasks/{task}/upload', 'TaskController@upload');

    Route::resource('task_statuses', 'TaskStatusController'); // name = (task_statuses. index / create / show / update / destroy / edit
    Route::post('task_statuses/bulk', 'TaskStatusController@bulk')->name('task_statuses.bulk');

    Route::resource('tax_rates', 'TaxRateController'); // name = (tax_rates. index / create / show / update / destroy / edit
    Route::post('tax_rates/bulk', 'TaxRateController@bulk')->name('tax_rates.bulk');

    Route::post('templates', 'TemplateController@show')->name('templates.show');

    Route::resource('tokens', 'TokenController')->middleware('password_protected'); // name = (tokens. index / create / show / update / destroy / edit
    Route::post('tokens/bulk', 'TokenController@bulk')->name('tokens.bulk')->middleware('password_protected');

    Route::get('settings/enable_two_factor', 'TwoFactorController@setupTwoFactor');
    Route::post('settings/enable_two_factor', 'TwoFactorController@enableTwoFactor');

    Route::resource('vendors', 'VendorController'); // name = (vendors. index / create / show / update / destroy / edit
    Route::post('vendors/bulk', 'VendorController@bulk')->name('vendors.bulk');
    Route::put('vendors/{vendor}/upload', 'VendorController@upload');

    Route::get('users', 'UserController@index');
    Route::put('users/{user}', 'UserController@update')->middleware('password_protected');
    Route::post('users', 'UserController@store')->middleware('password_protected');
    //Route::post('users/{user}/attach_to_company', 'UserController@attach')->middleware('password_protected');
    Route::delete('users/{user}/detach_from_company', 'UserController@detach')->middleware('password_protected');

    Route::post('users/bulk', 'UserController@bulk')->name('users.bulk')->middleware('password_protected');
    Route::post('/users/{user}/invite', 'UserController@invite')->middleware('password_protected');

    Route::resource('webhooks', 'WebhookController');
    Route::post('webhooks/bulk', 'WebhookController@bulk')->name('webhooks.bulk');

    /*Subscription and Webhook routes */
    // Route::post('hooks', 'SubscriptionController@subscribe')->name('hooks.subscribe');
    // Route::delete('hooks/{subscription_id}', 'SubscriptionController@unsubscribe')->name('hooks.unsubscribe');

    Route::resource('billing_subscriptions', 'BillingSubscriptionController');
});

Route::match(['get', 'post'], 'payment_webhook/{company_key}/{company_gateway_id}', 'PaymentWebhookController')
    ->middleware(['guest', 'api_db'])
    ->name('payment_webhook');

Route::post('api/v1/postmark_webhook', 'PostMarkController@webhook');
Route::get('token_hash_router', 'OneTimeTokenController@router');
Route::get('webcron', 'WebCronController@index');
Route::fallback('BaseController@notFound');

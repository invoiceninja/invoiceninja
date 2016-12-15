<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

//Crypt::decrypt();
//apc_clear_cache();
//dd(DB::getQueryLog());
//dd(Client::getPrivateId(1));
//dd(new DateTime());
//dd(App::environment());
//dd(gethostname());
//Log::error('test');

// Application setup
Route::get('/setup', 'AppController@showSetup');
Route::post('/setup', 'AppController@doSetup');
Route::get('/install', 'AppController@install');
Route::get('/update', 'AppController@update');

// Public pages
Route::get('/', 'HomeController@showIndex');
Route::get('/terms', 'HomeController@showTerms');
Route::get('/log_error', 'HomeController@logError');
Route::get('/invoice_now', 'HomeController@invoiceNow');
Route::get('/keep_alive', 'HomeController@keepAlive');
Route::post('/get_started', 'AccountController@getStarted');

// Client visible pages
Route::group(['middleware' => 'auth:client'], function() {
    Route::get('view/{invitation_key}', 'ClientPortalController@view');
    Route::get('download/{invitation_key}', 'ClientPortalController@download');
    Route::put('sign/{invitation_key}', 'ClientPortalController@sign');
    Route::get('view', 'HomeController@viewLogo');
    Route::get('approve/{invitation_key}', 'QuoteController@approve');
    Route::get('payment/{invitation_key}/{gateway_type?}/{source_id?}', 'OnlinePaymentController@showPayment');
    Route::post('payment/{invitation_key}', 'OnlinePaymentController@doPayment');
    Route::match(['GET', 'POST'], 'complete/{invitation_key?}/{gateway_type?}', 'OnlinePaymentController@offsitePayment');
    Route::get('bank/{routing_number}', 'OnlinePaymentController@getBankInfo');
    Route::get('client/payment_methods', 'ClientPortalController@paymentMethods');
    Route::post('client/payment_methods/verify', 'ClientPortalController@verifyPaymentMethod');
    //Route::get('client/payment_methods/add/{gateway_type}/{source_id?}', 'ClientPortalController@addPaymentMethod');
    //Route::post('client/payment_methods/add/{gateway_type}', 'ClientPortalController@postAddPaymentMethod');
    Route::post('client/payment_methods/default', 'ClientPortalController@setDefaultPaymentMethod');
    Route::post('client/payment_methods/{source_id}/remove', 'ClientPortalController@removePaymentMethod');
    Route::get('client/quotes', 'ClientPortalController@quoteIndex');
    Route::get('client/credits', 'ClientPortalController@creditIndex');
    Route::get('client/invoices', 'ClientPortalController@invoiceIndex');
    Route::get('client/invoices/recurring', 'ClientPortalController@recurringInvoiceIndex');
    Route::post('client/invoices/auto_bill', 'ClientPortalController@setAutoBill');
    Route::get('client/documents', 'ClientPortalController@documentIndex');
    Route::get('client/payments', 'ClientPortalController@paymentIndex');
    Route::get('client/dashboard/{contact_key?}', 'ClientPortalController@dashboard');
    Route::get('client/documents/js/{documents}/{filename}', 'ClientPortalController@getDocumentVFSJS');
    Route::get('client/documents/{invitation_key}/{documents}/{filename?}', 'ClientPortalController@getDocument');
    Route::get('client/documents/{invitation_key}/{filename?}', 'ClientPortalController@getInvoiceDocumentsZip');

    Route::get('api/client.quotes', ['as'=>'api.client.quotes', 'uses'=>'ClientPortalController@quoteDatatable']);
    Route::get('api/client.credits', ['as'=>'api.client.credits', 'uses'=>'ClientPortalController@creditDatatable']);
    Route::get('api/client.invoices', ['as'=>'api.client.invoices', 'uses'=>'ClientPortalController@invoiceDatatable']);
    Route::get('api/client.recurring_invoices', ['as'=>'api.client.recurring_invoices', 'uses'=>'ClientPortalController@recurringInvoiceDatatable']);
    Route::get('api/client.documents', ['as'=>'api.client.documents', 'uses'=>'ClientPortalController@documentDatatable']);
    Route::get('api/client.payments', ['as'=>'api.client.payments', 'uses'=>'ClientPortalController@paymentDatatable']);
    Route::get('api/client.activity', ['as'=>'api.client.activity', 'uses'=>'ClientPortalController@activityDatatable']);
});


Route::get('license', 'NinjaController@show_license_payment');
Route::post('license', 'NinjaController@do_license_payment');
Route::get('claim_license', 'NinjaController@claim_license');

Route::post('signup/validate', 'AccountController@checkEmail');
Route::post('signup/submit', 'AccountController@submitSignup');

Route::get('/auth/{provider}', 'Auth\AuthController@authLogin');
Route::get('/auth_unlink', 'Auth\AuthController@authUnlink');
Route::match(['GET', 'POST'], '/buy_now/{gateway_type?}', 'OnlinePaymentController@handleBuyNow');

Route::post('/hook/email_bounced', 'AppController@emailBounced');
Route::post('/hook/email_opened', 'AppController@emailOpened');
Route::post('/hook/bot/{platform?}', 'BotController@handleMessage');
Route::post('/payment_hook/{accountKey}/{gatewayId}', 'OnlinePaymentController@handlePaymentWebhook');

// Laravel auth routes
Route::get('/signup', ['as' => 'signup', 'uses' => 'Auth\AuthController@getRegister']);
Route::post('/signup', ['as' => 'signup', 'uses' => 'Auth\AuthController@postRegister']);
Route::get('/login', ['as' => 'login', 'uses' => 'Auth\AuthController@getLoginWrapper']);
Route::post('/login', ['as' => 'login', 'uses' => 'Auth\AuthController@postLoginWrapper']);
Route::get('/logout', ['as' => 'logout', 'uses' => 'Auth\AuthController@getLogoutWrapper']);
Route::get('/recover_password', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@getEmail']);
Route::post('/recover_password', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@postEmail']);
Route::get('/password/reset/{token}', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@getReset']);
Route::post('/password/reset', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@postReset']);
Route::get('/user/confirm/{code}', 'UserController@confirm');

// Client auth
Route::get('/client/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@getLogin']);
Route::post('/client/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@postLogin']);
Route::get('/client/logout', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getLogout']);
Route::get('/client/sessionexpired', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getSessionExpired']);
Route::get('/client/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getEmail']);
Route::post('/client/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postEmail']);
Route::get('/client/password/reset/{invitation_key}/{token}', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getReset']);
Route::post('/client/password/reset', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postReset']);


if (Utils::isNinja()) {
    Route::post('/signup/register', 'AccountController@doRegister');
    Route::get('/news_feed/{user_type}/{version}/', 'HomeController@newsFeed');
    Route::get('/demo', 'AccountController@demo');
}

if (Utils::isReseller()) {
    Route::post('/reseller_stats', 'AppController@stats');
}

Route::group(['middleware' => 'auth:user'], function() {
    Route::get('dashboard', 'DashboardController@index');
    Route::get('dashboard_chart_data/{group_by}/{start_date}/{end_date}/{currency_id}/{include_expenses}', 'DashboardController@chartData');
    Route::get('set_entity_filter/{entity_type}/{filter?}', 'AccountController@setEntityFilter');
    Route::get('hide_message', 'HomeController@hideMessage');
    Route::get('force_inline_pdf', 'UserController@forcePDFJS');
    Route::get('account/get_search_data', ['as' => 'get_search_data', 'uses' => 'AccountController@getSearchData']);
    Route::get('check_invoice_number/{invoice_id?}', 'InvoiceController@checkInvoiceNumber');
    Route::post('save_sidebar_state', 'UserController@saveSidebarState');

    Route::get('settings/user_details', 'AccountController@showUserDetails');
    Route::post('settings/user_details', 'AccountController@saveUserDetails');
    Route::post('settings/payment_gateway_limits', 'AccountController@savePaymentGatewayLimits');
    Route::post('users/change_password', 'UserController@changePassword');

    Route::resource('clients', 'ClientController');
    Route::get('api/clients', 'ClientController@getDatatable');
    Route::get('api/activities/{client_id?}', 'ActivityController@getDatatable');
    Route::post('clients/bulk', 'ClientController@bulk');

    Route::resource('tasks', 'TaskController');
    Route::get('api/tasks/{client_id?}', 'TaskController@getDatatable');
    Route::get('tasks/create/{client_id?}', 'TaskController@create');
    Route::post('tasks/bulk', 'TaskController@bulk');
    Route::get('projects', 'ProjectController@index');
    Route::get('api/projects', 'ProjectController@getDatatable');
    Route::get('projects/create/{client_id?}', 'ProjectController@create');
    Route::post('projects', 'ProjectController@store');
    Route::put('projects/{projects}', 'ProjectController@update');
    Route::get('projects/{projects}/edit', 'ProjectController@edit');
    Route::post('projects/bulk', 'ProjectController@bulk');

    Route::get('api/recurring_invoices/{client_id?}', 'InvoiceController@getRecurringDatatable');

    Route::get('invoices/invoice_history/{invoice_id}', 'InvoiceController@invoiceHistory');
    Route::get('quotes/quote_history/{invoice_id}', 'InvoiceController@invoiceHistory');

    Route::resource('invoices', 'InvoiceController');
    Route::get('api/invoices/{client_id?}', 'InvoiceController@getDatatable');
    Route::get('invoices/create/{client_id?}', 'InvoiceController@create');
    Route::get('recurring_invoices/create/{client_id?}', 'InvoiceController@createRecurring');
    Route::get('recurring_invoices', 'RecurringInvoiceController@index');
    Route::get('recurring_invoices/{invoices}/edit', 'InvoiceController@edit');
    Route::get('invoices/{invoices}/clone', 'InvoiceController@cloneInvoice');
    Route::post('invoices/bulk', 'InvoiceController@bulk');
    Route::post('recurring_invoices/bulk', 'InvoiceController@bulk');

    Route::get('documents/{documents}/{filename?}', 'DocumentController@get');
    Route::get('documents/js/{documents}/{filename}', 'DocumentController@getVFSJS');
    Route::get('documents/preview/{documents}/{filename?}', 'DocumentController@getPreview');
    Route::post('documents', 'DocumentController@postUpload');
    Route::delete('documents/{documents}', 'DocumentController@delete');

    Route::get('quotes/create/{client_id?}', 'QuoteController@create');
    Route::get('quotes/{invoices}/clone', 'InvoiceController@cloneInvoice');
    Route::get('quotes/{invoices}/edit', 'InvoiceController@edit');
    Route::put('quotes/{invoices}', 'InvoiceController@update');
    Route::get('quotes/{invoices}', 'InvoiceController@edit');
    Route::post('quotes', 'InvoiceController@store');
    Route::get('quotes', 'QuoteController@index');
    Route::get('api/quotes/{client_id?}', 'QuoteController@getDatatable');
    Route::post('quotes/bulk', 'QuoteController@bulk');

    Route::resource('payments', 'PaymentController');
    Route::get('payments/create/{client_id?}/{invoice_id?}', 'PaymentController@create');
    Route::get('api/payments/{client_id?}', 'PaymentController@getDatatable');
    Route::post('payments/bulk', 'PaymentController@bulk');

    Route::resource('credits', 'CreditController');
    Route::get('credits/create/{client_id?}/{invoice_id?}', 'CreditController@create');
    Route::get('api/credits/{client_id?}', 'CreditController@getDatatable');
    Route::post('credits/bulk', 'CreditController@bulk');

    Route::get('api/products', 'ProductController@getDatatable');
    Route::resource('products', 'ProductController');
    Route::post('products/bulk', 'ProductController@bulk');


    Route::get('/resend_confirmation', 'AccountController@resendConfirmation');
    Route::post('/update_setup', 'AppController@updateSetup');


    // vendor
    Route::resource('vendors', 'VendorController');
    Route::get('api/vendors', 'VendorController@getDatatable');
    Route::post('vendors/bulk', 'VendorController@bulk');

    // Expense
    Route::resource('expenses', 'ExpenseController');
    Route::get('expenses/create/{vendor_id?}/{client_id?}', 'ExpenseController@create');
    Route::get('api/expenses', 'ExpenseController@getDatatable');
    Route::get('api/expenses/{id}', 'ExpenseController@getDatatableVendor');
    Route::post('expenses/bulk', 'ExpenseController@bulk');
    Route::get('expense_categories', 'ExpenseCategoryController@index');
    Route::get('api/expense_categories', 'ExpenseCategoryController@getDatatable');
    Route::get('expense_categories/create', 'ExpenseCategoryController@create');
    Route::post('expense_categories', 'ExpenseCategoryController@store');
    Route::put('expense_categories/{expense_categories}', 'ExpenseCategoryController@update');
    Route::get('expense_categories/{expense_categories}/edit', 'ExpenseCategoryController@edit');
    Route::post('expense_categories/bulk', 'ExpenseCategoryController@bulk');

	// BlueVine
	Route::post('bluevine/signup', 'BlueVineController@signup');
	Route::get('bluevine/hide_message', 'BlueVineController@hideMessage');
	Route::get('bluevine/completed', 'BlueVineController@handleCompleted');
});

Route::group([
    'middleware' => ['auth:user', 'permissions.required'],
    'permissions' => 'admin',
], function() {
    Route::get('api/users', 'UserController@getDatatable');
    Route::resource('users', 'UserController');
    Route::post('users/bulk', 'UserController@bulk');
    Route::get('send_confirmation/{user_id}', 'UserController@sendConfirmation');
    Route::get('start_trial/{plan}', 'AccountController@startTrial')
        ->where(['plan'=>'pro']);
    Route::get('/switch_account/{user_id}', 'UserController@switchAccount');
    Route::get('/unlink_account/{user_account_id}/{user_id}', 'UserController@unlinkAccount');
    Route::get('/manage_companies', 'UserController@manageCompanies');

    Route::get('api/tokens', 'TokenController@getDatatable');
    Route::resource('tokens', 'TokenController');
    Route::post('tokens/bulk', 'TokenController@bulk');

    Route::get('api/tax_rates', 'TaxRateController@getDatatable');
    Route::resource('tax_rates', 'TaxRateController');
    Route::post('tax_rates/bulk', 'TaxRateController@bulk');

    Route::get('settings/email_preview', 'AccountController@previewEmail');
    Route::get('company/{section}/{subSection?}', 'AccountController@redirectLegacy');
    Route::get('settings/data_visualizations', 'ReportController@d3');
    Route::get('settings/reports', 'ReportController@showReports');
    Route::post('settings/reports', 'ReportController@showReports');

    Route::post('settings/change_plan', 'AccountController@changePlan');
    Route::post('settings/cancel_account', 'AccountController@cancelAccount');
    Route::post('settings/company_details', 'AccountController@updateDetails');
    Route::post('settings/{section?}', 'AccountController@doSection');

    Route::post('user/setTheme', 'UserController@setTheme');
    Route::post('remove_logo', 'AccountController@removeLogo');

    Route::post('/export', 'ExportController@doExport');
    Route::post('/import', 'ImportController@doImport');
    Route::post('/import_csv', 'ImportController@doImportCSV');

    Route::get('gateways/create/{show_wepay?}', 'AccountGatewayController@create');
    Route::resource('gateways', 'AccountGatewayController');
    Route::get('gateways/{public_id}/resend_confirmation', 'AccountGatewayController@resendConfirmation');
    Route::get('api/gateways', 'AccountGatewayController@getDatatable');
    Route::post('account_gateways/bulk', 'AccountGatewayController@bulk');

    Route::get('bank_accounts/import_ofx', 'BankAccountController@showImportOFX');
    Route::post('bank_accounts/import_ofx', 'BankAccountController@doImportOFX');
    Route::resource('bank_accounts', 'BankAccountController');
    Route::get('api/bank_accounts', 'BankAccountController@getDatatable');
    Route::post('bank_accounts/bulk', 'BankAccountController@bulk');
    Route::post('bank_accounts/validate', 'BankAccountController@validateAccount');
    Route::post('bank_accounts/import_expenses/{bank_id}', 'BankAccountController@importExpenses');
    Route::get('self-update', 'SelfUpdateController@index');
    Route::post('self-update', 'SelfUpdateController@update');
    Route::get('self-update/download', 'SelfUpdateController@download');
});

Route::group(['middleware' => 'auth:user'], function() {
    Route::get('settings/{section?}', 'AccountController@showSection');
});

// Route groups for API
Route::group(['middleware' => 'api', 'prefix' => 'api/v1'], function()
{
    Route::get('ping', 'AccountApiController@ping');
    Route::post('login', 'AccountApiController@login');
    Route::post('oauth_login', 'AccountApiController@oauthLogin');
    Route::post('register', 'AccountApiController@register');
    Route::get('static', 'AccountApiController@getStaticData');
    Route::get('accounts', 'AccountApiController@show');
    Route::put('accounts', 'AccountApiController@update');
    Route::resource('clients', 'ClientApiController');
    Route::get('quotes', 'QuoteApiController@index');
    Route::get('invoices', 'InvoiceApiController@index');
    Route::get('download/{invoice_id}', 'InvoiceApiController@download');
    Route::resource('invoices', 'InvoiceApiController');
    Route::resource('payments', 'PaymentApiController');
    Route::get('tasks', 'TaskApiController@index');
    Route::resource('tasks', 'TaskApiController');
    Route::post('hooks', 'IntegrationController@subscribe');
    Route::post('email_invoice', 'InvoiceApiController@emailInvoice');
    Route::get('user_accounts', 'AccountApiController@getUserAccounts');
    Route::resource('products', 'ProductApiController');
    Route::resource('tax_rates', 'TaxRateApiController');
    Route::resource('users', 'UserApiController');
    Route::resource('expenses','ExpenseApiController');
    Route::post('add_token', 'AccountApiController@addDeviceToken');
    Route::post('update_notifications', 'AccountApiController@updatePushNotifications');
    Route::get('dashboard', 'DashboardApiController@index');
    Route::resource('documents', 'DocumentAPIController');
    Route::resource('vendors', 'VendorApiController');
    Route::resource('expense_categories', 'ExpenseCategoryApiController');
});

// Redirects for legacy links
Route::get('/rocksteady', function() {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/about', function() {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/contact', function() {
    return Redirect::to(NINJA_WEB_URL.'/contact', 301);
});
Route::get('/plans', function() {
    return Redirect::to(NINJA_WEB_URL.'/pricing', 301);
});
Route::get('/faq', function() {
    return Redirect::to(NINJA_WEB_URL.'/how-it-works', 301);
});
Route::get('/features', function() {
    return Redirect::to(NINJA_WEB_URL.'/features', 301);
});
Route::get('/testimonials', function() {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/compare-online-invoicing{sites?}', function() {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/forgot', function() {
    return Redirect::to(NINJA_APP_URL.'/recover_password', 301);
});
Route::get('/feed', function() {
    return Redirect::to(NINJA_WEB_URL.'/feed', 301);
});
Route::get('/comments/feed', function() {
    return Redirect::to(NINJA_WEB_URL.'/comments/feed', 301);
});

if (!defined('CONTACT_EMAIL')) {
    define('CONTACT_EMAIL', config('mail.from.address'));
    define('CONTACT_NAME', config('mail.from.name'));
    define('SITE_URL', config('app.url'));

    define('ENV_DEVELOPMENT', 'local');
    define('ENV_STAGING', 'staging');

    define('RECENTLY_VIEWED', 'recent_history');

    define('ENTITY_CLIENT', 'client');
    define('ENTITY_CONTACT', 'contact');
    define('ENTITY_INVOICE', 'invoice');
    define('ENTITY_DOCUMENT', 'document');
    define('ENTITY_INVOICE_ITEM', 'invoice_item');
    define('ENTITY_INVITATION', 'invitation');
    define('ENTITY_RECURRING_INVOICE', 'recurring_invoice');
    define('ENTITY_PAYMENT', 'payment');
    define('ENTITY_CREDIT', 'credit');
    define('ENTITY_QUOTE', 'quote');
    define('ENTITY_TASK', 'task');
    define('ENTITY_ACCOUNT_GATEWAY', 'account_gateway');
    define('ENTITY_USER', 'user');
    define('ENTITY_TOKEN', 'token');
    define('ENTITY_TAX_RATE', 'tax_rate');
    define('ENTITY_PRODUCT', 'product');
    define('ENTITY_ACTIVITY', 'activity');
    define('ENTITY_VENDOR', 'vendor');
    define('ENTITY_VENDOR_ACTIVITY', 'vendor_activity');
    define('ENTITY_EXPENSE', 'expense');
    define('ENTITY_PAYMENT_TERM', 'payment_term');
    define('ENTITY_EXPENSE_ACTIVITY', 'expense_activity');
    define('ENTITY_BANK_ACCOUNT', 'bank_account');
    define('ENTITY_BANK_SUBACCOUNT', 'bank_subaccount');
    define('ENTITY_EXPENSE_CATEGORY', 'expense_category');
    define('ENTITY_PROJECT', 'project');

    define('INVOICE_TYPE_STANDARD', 1);
    define('INVOICE_TYPE_QUOTE', 2);

    define('PERSON_CONTACT', 'contact');
    define('PERSON_USER', 'user');
    define('PERSON_VENDOR_CONTACT','vendorcontact');

    define('BASIC_SETTINGS', 'basic_settings');
    define('ADVANCED_SETTINGS', 'advanced_settings');

    define('ACCOUNT_COMPANY_DETAILS', 'company_details');
    define('ACCOUNT_USER_DETAILS', 'user_details');
    define('ACCOUNT_LOCALIZATION', 'localization');
    define('ACCOUNT_NOTIFICATIONS', 'notifications');
    define('ACCOUNT_IMPORT_EXPORT', 'import_export');
    define('ACCOUNT_MANAGEMENT', 'account_management');
    define('ACCOUNT_PAYMENTS', 'online_payments');
    define('ACCOUNT_BANKS', 'bank_accounts');
    define('ACCOUNT_IMPORT_EXPENSES', 'import_expenses');
    define('ACCOUNT_MAP', 'import_map');
    define('ACCOUNT_EXPORT', 'export');
    define('ACCOUNT_TAX_RATES', 'tax_rates');
    define('ACCOUNT_PRODUCTS', 'products');
    define('ACCOUNT_ADVANCED_SETTINGS', 'advanced_settings');
    define('ACCOUNT_INVOICE_SETTINGS', 'invoice_settings');
    define('ACCOUNT_INVOICE_DESIGN', 'invoice_design');
    define('ACCOUNT_CLIENT_PORTAL', 'client_portal');
    define('ACCOUNT_EMAIL_SETTINGS', 'email_settings');
    define('ACCOUNT_REPORTS', 'reports');
    define('ACCOUNT_USER_MANAGEMENT', 'user_management');
    define('ACCOUNT_DATA_VISUALIZATIONS', 'data_visualizations');
    define('ACCOUNT_TEMPLATES_AND_REMINDERS', 'templates_and_reminders');
    define('ACCOUNT_API_TOKENS', 'api_tokens');
    define('ACCOUNT_CUSTOMIZE_DESIGN', 'customize_design');
    define('ACCOUNT_SYSTEM_SETTINGS', 'system_settings');
    define('ACCOUNT_PAYMENT_TERMS','payment_terms');

    define('ACTION_RESTORE', 'restore');
    define('ACTION_ARCHIVE', 'archive');
    define('ACTION_CLONE', 'clone');
    define('ACTION_CONVERT', 'convert');
    define('ACTION_DELETE', 'delete');

    define('ACTIVITY_TYPE_CREATE_CLIENT', 1);
    define('ACTIVITY_TYPE_ARCHIVE_CLIENT', 2);
    define('ACTIVITY_TYPE_DELETE_CLIENT', 3);
    define('ACTIVITY_TYPE_CREATE_INVOICE', 4);
    define('ACTIVITY_TYPE_UPDATE_INVOICE', 5);
    define('ACTIVITY_TYPE_EMAIL_INVOICE', 6);
    define('ACTIVITY_TYPE_VIEW_INVOICE', 7);
    define('ACTIVITY_TYPE_ARCHIVE_INVOICE', 8);
    define('ACTIVITY_TYPE_DELETE_INVOICE', 9);
    define('ACTIVITY_TYPE_CREATE_PAYMENT', 10);
    //define('ACTIVITY_TYPE_UPDATE_PAYMENT', 11);
    define('ACTIVITY_TYPE_ARCHIVE_PAYMENT', 12);
    define('ACTIVITY_TYPE_DELETE_PAYMENT', 13);
    define('ACTIVITY_TYPE_CREATE_CREDIT', 14);
    //define('ACTIVITY_TYPE_UPDATE_CREDIT', 15);
    define('ACTIVITY_TYPE_ARCHIVE_CREDIT', 16);
    define('ACTIVITY_TYPE_DELETE_CREDIT', 17);
    define('ACTIVITY_TYPE_CREATE_QUOTE', 18);
    define('ACTIVITY_TYPE_UPDATE_QUOTE', 19);
    define('ACTIVITY_TYPE_EMAIL_QUOTE', 20);
    define('ACTIVITY_TYPE_VIEW_QUOTE', 21);
    define('ACTIVITY_TYPE_ARCHIVE_QUOTE', 22);
    define('ACTIVITY_TYPE_DELETE_QUOTE', 23);
    define('ACTIVITY_TYPE_RESTORE_QUOTE', 24);
    define('ACTIVITY_TYPE_RESTORE_INVOICE', 25);
    define('ACTIVITY_TYPE_RESTORE_CLIENT', 26);
    define('ACTIVITY_TYPE_RESTORE_PAYMENT', 27);
    define('ACTIVITY_TYPE_RESTORE_CREDIT', 28);
    define('ACTIVITY_TYPE_APPROVE_QUOTE', 29);
    define('ACTIVITY_TYPE_CREATE_VENDOR', 30);
    define('ACTIVITY_TYPE_ARCHIVE_VENDOR', 31);
    define('ACTIVITY_TYPE_DELETE_VENDOR', 32);
    define('ACTIVITY_TYPE_RESTORE_VENDOR', 33);
    define('ACTIVITY_TYPE_CREATE_EXPENSE', 34);
    define('ACTIVITY_TYPE_ARCHIVE_EXPENSE', 35);
    define('ACTIVITY_TYPE_DELETE_EXPENSE', 36);
    define('ACTIVITY_TYPE_RESTORE_EXPENSE', 37);
    define('ACTIVITY_TYPE_VOIDED_PAYMENT', 39);
    define('ACTIVITY_TYPE_REFUNDED_PAYMENT', 40);
    define('ACTIVITY_TYPE_FAILED_PAYMENT', 41);
    define('ACTIVITY_TYPE_CREATE_TASK', 42);
    define('ACTIVITY_TYPE_UPDATE_TASK', 43);
    define('ACTIVITY_TYPE_ARCHIVE_TASK', 44);
    define('ACTIVITY_TYPE_DELETE_TASK', 45);
    define('ACTIVITY_TYPE_RESTORE_TASK', 46);
    define('ACTIVITY_TYPE_UPDATE_EXPENSE', 47);


    define('DEFAULT_INVOICE_NUMBER', '0001');
    define('RECENTLY_VIEWED_LIMIT', 20);
    define('LOGGED_ERROR_LIMIT', 100);
    define('RANDOM_KEY_LENGTH', 32);
    define('MAX_NUM_USERS', 20);
    define('MAX_IMPORT_ROWS', 500);
    define('MAX_SUBDOMAIN_LENGTH', 30);
    define('MAX_IFRAME_URL_LENGTH', 250);
    define('MAX_LOGO_FILE_SIZE', 200); // KB
    define('MAX_FAILED_LOGINS', 10);
    define('MAX_INVOICE_ITEMS', env('MAX_INVOICE_ITEMS', 100));
    define('MAX_DOCUMENT_SIZE', env('MAX_DOCUMENT_SIZE', 10000));// KB
    define('MAX_EMAIL_DOCUMENTS_SIZE', env('MAX_EMAIL_DOCUMENTS_SIZE', 10000));// Total KB
    define('MAX_ZIP_DOCUMENTS_SIZE', env('MAX_EMAIL_DOCUMENTS_SIZE', 30000));// Total KB (uncompressed)
    define('DOCUMENT_PREVIEW_SIZE', env('DOCUMENT_PREVIEW_SIZE', 300));// pixels
    define('DEFAULT_FONT_SIZE', 9);
    define('DEFAULT_HEADER_FONT', 1);// Roboto
    define('DEFAULT_BODY_FONT', 1);// Roboto
    define('DEFAULT_SEND_RECURRING_HOUR', 8);

    define('IMPORT_CSV', 'CSV');
    define('IMPORT_JSON', 'JSON');
    define('IMPORT_FRESHBOOKS', 'FreshBooks');
    define('IMPORT_WAVE', 'Wave');
    define('IMPORT_RONIN', 'Ronin');
    define('IMPORT_HIVEAGE', 'Hiveage');
    define('IMPORT_ZOHO', 'Zoho');
    define('IMPORT_NUTCACHE', 'Nutcache');
    define('IMPORT_INVOICEABLE', 'Invoiceable');
    define('IMPORT_HARVEST', 'Harvest');

    define('MAX_NUM_CLIENTS', 100);
    define('MAX_NUM_CLIENTS_PRO', 20000);
    define('MAX_NUM_CLIENTS_LEGACY', 500);
    define('MAX_INVOICE_AMOUNT', 1000000000);
    define('LEGACY_CUTOFF', 57800);
    define('ERROR_DELAY', 3);

    define('MAX_NUM_VENDORS', 100);
    define('MAX_NUM_VENDORS_PRO', 20000);

    define('STATUS_ACTIVE', 'active');
    define('STATUS_ARCHIVED', 'archived');
    define('STATUS_DELETED', 'deleted');

    define('INVOICE_STATUS_DRAFT', 1);
    define('INVOICE_STATUS_SENT', 2);
    define('INVOICE_STATUS_VIEWED', 3);
    define('INVOICE_STATUS_APPROVED', 4);
    define('INVOICE_STATUS_PARTIAL', 5);
    define('INVOICE_STATUS_PAID', 6);
    define('INVOICE_STATUS_OVERDUE', 7);

    define('PAYMENT_STATUS_PENDING', 1);
    define('PAYMENT_STATUS_VOIDED', 2);
    define('PAYMENT_STATUS_FAILED', 3);
    define('PAYMENT_STATUS_COMPLETED', 4);
    define('PAYMENT_STATUS_PARTIALLY_REFUNDED', 5);
    define('PAYMENT_STATUS_REFUNDED', 6);

    define('TASK_STATUS_LOGGED', 1);
    define('TASK_STATUS_RUNNING', 2);
    define('TASK_STATUS_INVOICED', 3);
    define('TASK_STATUS_PAID', 4);

    define('EXPENSE_STATUS_LOGGED', 1);
    define('EXPENSE_STATUS_INVOICED', 2);
    define('EXPENSE_STATUS_PAID', 3);

    define('CUSTOM_DESIGN', 11);

    define('FREQUENCY_WEEKLY', 1);
    define('FREQUENCY_TWO_WEEKS', 2);
    define('FREQUENCY_FOUR_WEEKS', 3);
    define('FREQUENCY_MONTHLY', 4);
    define('FREQUENCY_THREE_MONTHS', 5);
    define('FREQUENCY_SIX_MONTHS', 6);
    define('FREQUENCY_ANNUALLY', 7);

    define('SESSION_TIMEZONE', 'timezone');
    define('SESSION_CURRENCY', 'currency');
    define('SESSION_CURRENCY_DECORATOR', 'currency_decorator');
    define('SESSION_DATE_FORMAT', 'dateFormat');
    define('SESSION_DATE_PICKER_FORMAT', 'datePickerFormat');
    define('SESSION_DATETIME_FORMAT', 'datetimeFormat');
    define('SESSION_COUNTER', 'sessionCounter');
    define('SESSION_LOCALE', 'sessionLocale');
    define('SESSION_USER_ACCOUNTS', 'userAccounts');
    define('SESSION_REFERRAL_CODE', 'referralCode');
    define('SESSION_LEFT_SIDEBAR', 'showLeftSidebar');
    define('SESSION_RIGHT_SIDEBAR', 'showRightSidebar');

    define('SESSION_LAST_REQUEST_PAGE', 'SESSION_LAST_REQUEST_PAGE');
    define('SESSION_LAST_REQUEST_TIME', 'SESSION_LAST_REQUEST_TIME');

    define('CURRENCY_DOLLAR', 1);
    define('CURRENCY_EURO', 3);

    define('DEFAULT_TIMEZONE', 'US/Eastern');
    define('DEFAULT_COUNTRY', 840); // United Stated
    define('DEFAULT_CURRENCY', CURRENCY_DOLLAR);
    define('DEFAULT_LANGUAGE', 1); // English
    define('DEFAULT_DATE_FORMAT', 'M j, Y');
    define('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy');
    define('DEFAULT_DATETIME_FORMAT', 'F j, Y g:i a');
    define('DEFAULT_DATETIME_MOMENT_FORMAT', 'MMM D, YYYY h:mm:ss a');
    define('DEFAULT_LOCALE', 'en');
    define('DEFAULT_MAP_ZOOM', 10);

    define('RESULT_SUCCESS', 'success');
    define('RESULT_FAILURE', 'failure');


    define('PAYMENT_LIBRARY_OMNIPAY', 1);
    define('PAYMENT_LIBRARY_PHP_PAYMENTS', 2);

    define('GATEWAY_AUTHORIZE_NET', 1);
    define('GATEWAY_EWAY', 4);
    define('GATEWAY_MOLLIE', 9);
    define('GATEWAY_PAYFAST', 13);
    define('GATEWAY_PAYPAL_EXPRESS', 17);
    define('GATEWAY_PAYPAL_PRO', 18);
    define('GATEWAY_SAGE_PAY_DIRECT', 20);
    define('GATEWAY_SAGE_PAY_SERVER', 21);
    define('GATEWAY_STRIPE', 23);
    define('GATEWAY_GOCARDLESS', 6);
    define('GATEWAY_TWO_CHECKOUT', 27);
    define('GATEWAY_BEANSTREAM', 29);
    define('GATEWAY_PSIGATE', 30);
    define('GATEWAY_MOOLAH', 31);
    define('GATEWAY_BITPAY', 42);
    define('GATEWAY_DWOLLA', 43);
    define('GATEWAY_CHECKOUT_COM', 47);
    define('GATEWAY_CYBERSOURCE', 49);
    define('GATEWAY_WEPAY', 60);
    define('GATEWAY_BRAINTREE', 61);
    define('GATEWAY_CUSTOM', 62);

    // The customer exists, but only as a local concept
    // The remote gateway doesn't understand the concept of customers
    define('CUSTOMER_REFERENCE_LOCAL', 'local');

    define('EVENT_CREATE_CLIENT', 1);
    define('EVENT_CREATE_INVOICE', 2);
    define('EVENT_CREATE_QUOTE', 3);
    define('EVENT_CREATE_PAYMENT', 4);
    define('EVENT_CREATE_VENDOR',5);

    define('REQUESTED_PRO_PLAN', 'REQUESTED_PRO_PLAN');
    define('DEMO_ACCOUNT_ID', 'DEMO_ACCOUNT_ID');
    define('PREV_USER_ID', 'PREV_USER_ID');
    define('NINJA_ACCOUNT_KEY', 'zg4ylmzDkdkPOT8yoKQw9LTWaoZJx79h');
    define('NINJA_GATEWAY_ID', GATEWAY_STRIPE);
    define('NINJA_GATEWAY_CONFIG', 'NINJA_GATEWAY_CONFIG');
    define('NINJA_WEB_URL', env('NINJA_WEB_URL', 'https://www.invoiceninja.com'));
    define('NINJA_APP_URL', env('NINJA_APP_URL', 'https://app.invoiceninja.com'));
    define('NINJA_DOCS_URL', env('NINJA_DOCS_URL', 'http://docs.invoiceninja.com/en/latest'));
    define('NINJA_DATE', '2000-01-01');
    define('NINJA_VERSION', '2.9.0' . env('NINJA_VERSION_SUFFIX'));

    define('SOCIAL_LINK_FACEBOOK', env('SOCIAL_LINK_FACEBOOK', 'https://www.facebook.com/invoiceninja'));
    define('SOCIAL_LINK_TWITTER', env('SOCIAL_LINK_TWITTER', 'https://twitter.com/invoiceninja'));
    define('SOCIAL_LINK_GITHUB', env('SOCIAL_LINK_GITHUB', 'https://github.com/invoiceninja/invoiceninja/'));

    define('NINJA_FORUM_URL', env('NINJA_FORUM_URL', 'https://www.invoiceninja.com/forums/forum/support/'));
    define('NINJA_CONTACT_URL', env('NINJA_CONTACT_URL', 'https://www.invoiceninja.com/contact/'));
    define('NINJA_FROM_EMAIL', env('NINJA_FROM_EMAIL', 'maildelivery@invoiceninja.com'));
    define('RELEASES_URL', env('RELEASES_URL', 'https://trello.com/b/63BbiVVe/invoice-ninja'));
    define('ZAPIER_URL', env('ZAPIER_URL', 'https://zapier.com/zapbook/invoice-ninja'));
    define('OUTDATE_BROWSER_URL', env('OUTDATE_BROWSER_URL', 'http://browsehappy.com/'));
    define('PDFMAKE_DOCS', env('PDFMAKE_DOCS', 'http://pdfmake.org/playground.html'));
    define('PHANTOMJS_CLOUD', env('PHANTOMJS_CLOUD', 'http://api.phantomjscloud.com/api/browser/v2/'));
    define('PHP_DATE_FORMATS', env('PHP_DATE_FORMATS', 'http://php.net/manual/en/function.date.php'));
    define('REFERRAL_PROGRAM_URL', env('REFERRAL_PROGRAM_URL', 'https://www.invoiceninja.com/referral-program/'));
    define('EMAIL_MARKUP_URL', env('EMAIL_MARKUP_URL', 'https://developers.google.com/gmail/markup'));
    define('OFX_HOME_URL', env('OFX_HOME_URL', 'http://www.ofxhome.com/index.php/home/directory/all'));
    define('GOOGLE_ANALYITCS_URL', env('GOOGLE_ANALYITCS_URL', 'https://www.google-analytics.com/collect'));
    define('TRANSIFEX_URL', env('TRANSIFEX_URL', 'https://www.transifex.com/invoice-ninja/invoice-ninja'));
    define('CHROME_PDF_HELP_URL', 'https://support.google.com/chrome/answer/6213030?hl=en');
    define('FIREFOX_PDF_HELP_URL', 'https://support.mozilla.org/en-US/kb/view-pdf-files-firefox');

    define('MSBOT_LOGIN_URL', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    define('MSBOT_LUIS_URL', 'https://api.projectoxford.ai/luis/v1/application');
    define('SKYPE_API_URL', 'https://apis.skype.com/v3');
    define('MSBOT_STATE_URL', 'https://state.botframework.com/v3');

    define('BLANK_IMAGE', 'data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

    define('COUNT_FREE_DESIGNS', 4);
    define('COUNT_FREE_DESIGNS_SELF_HOST', 5); // include the custom design
    define('PRODUCT_ONE_CLICK_INSTALL', 1);
    define('PRODUCT_INVOICE_DESIGNS', 2);
    define('PRODUCT_WHITE_LABEL', 3);
    define('PRODUCT_SELF_HOST', 4);
    define('WHITE_LABEL_AFFILIATE_KEY', '92D2J5');
    define('INVOICE_DESIGNS_AFFILIATE_KEY', 'T3RS74');
    define('SELF_HOST_AFFILIATE_KEY', '8S69AD');

    define('PLAN_PRICE_PRO_MONTHLY', env('PLAN_PRICE_PRO_MONTHLY', 8));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_2', env('PLAN_PRICE_ENTERPRISE_MONTHLY_2', 12));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_5', env('PLAN_PRICE_ENTERPRISE_MONTHLY_5', 18));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_10', env('PLAN_PRICE_ENTERPRISE_MONTHLY_10', 24));
    define('WHITE_LABEL_PRICE', env('WHITE_LABEL_PRICE', 20));
    define('INVOICE_DESIGNS_PRICE', env('INVOICE_DESIGNS_PRICE', 10));

    define('USER_TYPE_SELF_HOST', 'SELF_HOST');
    define('USER_TYPE_CLOUD_HOST', 'CLOUD_HOST');
    define('NEW_VERSION_AVAILABLE', 'NEW_VERSION_AVAILABLE');

    define('TEST_USERNAME', 'user@example.com');
    define('TEST_PASSWORD', 'password');
    define('API_SECRET', 'API_SECRET');
    define('DEFAULT_API_PAGE_SIZE', 15);
    define('MAX_API_PAGE_SIZE', 500);

    define('IOS_PUSH_CERTIFICATE', env('IOS_PUSH_CERTIFICATE', ''));

    define('TOKEN_BILLING_DISABLED', 1);
    define('TOKEN_BILLING_OPT_IN', 2);
    define('TOKEN_BILLING_OPT_OUT', 3);
    define('TOKEN_BILLING_ALWAYS', 4);

    define('PAYMENT_TYPE_CREDIT', 1);
    define('PAYMENT_TYPE_ACH', 5);
    define('PAYMENT_TYPE_VISA', 6);
    define('PAYMENT_TYPE_MASTERCARD', 7);
    define('PAYMENT_TYPE_AMERICAN_EXPRESS', 8);
    define('PAYMENT_TYPE_DISCOVER', 9);
    define('PAYMENT_TYPE_DINERS', 10);
    define('PAYMENT_TYPE_EUROCARD', 11);
    define('PAYMENT_TYPE_NOVA', 12);
    define('PAYMENT_TYPE_CREDIT_CARD_OTHER', 13);
    define('PAYMENT_TYPE_PAYPAL', 14);
    define('PAYMENT_TYPE_CARTE_BLANCHE', 17);
    define('PAYMENT_TYPE_UNIONPAY', 18);
    define('PAYMENT_TYPE_JCB', 19);
    define('PAYMENT_TYPE_LASER', 20);
    define('PAYMENT_TYPE_MAESTRO', 21);
    define('PAYMENT_TYPE_SOLO', 22);
    define('PAYMENT_TYPE_SWITCH', 23);

    define('PAYMENT_METHOD_STATUS_NEW', 'new');
    define('PAYMENT_METHOD_STATUS_VERIFICATION_FAILED', 'verification_failed');
    define('PAYMENT_METHOD_STATUS_VERIFIED', 'verified');

    define('GATEWAY_TYPE_CREDIT_CARD', 1);
    define('GATEWAY_TYPE_BANK_TRANSFER', 2);
    define('GATEWAY_TYPE_PAYPAL', 3);
    define('GATEWAY_TYPE_BITCOIN', 4);
    define('GATEWAY_TYPE_DWOLLA', 5);
    define('GATEWAY_TYPE_CUSTOM', 6);
    define('GATEWAY_TYPE_TOKEN', 'token');

    define('REMINDER1', 'reminder1');
    define('REMINDER2', 'reminder2');
    define('REMINDER3', 'reminder3');

    define('REMINDER_DIRECTION_AFTER', 1);
    define('REMINDER_DIRECTION_BEFORE', 2);

    define('REMINDER_FIELD_DUE_DATE', 1);
    define('REMINDER_FIELD_INVOICE_DATE', 2);

    define('FILTER_INVOICE_DATE', 'invoice_date');
    define('FILTER_PAYMENT_DATE', 'payment_date');

    define('SOCIAL_GOOGLE', 'Google');
    define('SOCIAL_FACEBOOK', 'Facebook');
    define('SOCIAL_GITHUB', 'GitHub');
    define('SOCIAL_LINKEDIN', 'LinkedIn');

    define('USER_STATE_ACTIVE', 'active');
    define('USER_STATE_PENDING', 'pending');
    define('USER_STATE_DISABLED', 'disabled');
    define('USER_STATE_ADMIN', 'admin');
    define('USER_STATE_OWNER', 'owner');

    define('API_SERIALIZER_ARRAY', 'array');
    define('API_SERIALIZER_JSON', 'json');

    define('EMAIL_DESIGN_PLAIN', 1);
    define('EMAIL_DESIGN_LIGHT', 2);
    define('EMAIL_DESIGN_DARK', 3);

    define('BANK_LIBRARY_OFX', 1);

    define('CURRENCY_DECORATOR_CODE', 'code');
    define('CURRENCY_DECORATOR_SYMBOL', 'symbol');
    define('CURRENCY_DECORATOR_NONE', 'none');

    define('RESELLER_REVENUE_SHARE', 'A');
    define('RESELLER_LIMITED_USERS', 'B');

    define('AUTO_BILL_OFF', 1);
    define('AUTO_BILL_OPT_IN', 2);
    define('AUTO_BILL_OPT_OUT', 3);
    define('AUTO_BILL_ALWAYS', 4);

    // These must be lowercase
    define('PLAN_FREE', 'free');
    define('PLAN_PRO', 'pro');
    define('PLAN_ENTERPRISE', 'enterprise');
    define('PLAN_WHITE_LABEL', 'white_label');
    define('PLAN_TERM_MONTHLY', 'month');
    define('PLAN_TERM_YEARLY', 'year');

    // Pro
    define('FEATURE_CUSTOMIZE_INVOICE_DESIGN', 'customize_invoice_design');
    define('FEATURE_REMOVE_CREATED_BY', 'remove_created_by');
    define('FEATURE_DIFFERENT_DESIGNS', 'different_designs');
    define('FEATURE_EMAIL_TEMPLATES_REMINDERS', 'email_templates_reminders');
    define('FEATURE_INVOICE_SETTINGS', 'invoice_settings');
    define('FEATURE_CUSTOM_EMAILS', 'custom_emails');
    define('FEATURE_PDF_ATTACHMENT', 'pdf_attachment');
    define('FEATURE_MORE_INVOICE_DESIGNS', 'more_invoice_designs');
    define('FEATURE_QUOTES', 'quotes');
    define('FEATURE_TASKS', 'tasks');
    define('FEATURE_EXPENSES', 'expenses');
    define('FEATURE_REPORTS', 'reports');
    define('FEATURE_BUY_NOW_BUTTONS', 'buy_now_buttons');
    define('FEATURE_API', 'api');
    define('FEATURE_CLIENT_PORTAL_PASSWORD', 'client_portal_password');
    define('FEATURE_CUSTOM_URL', 'custom_url');

    define('FEATURE_MORE_CLIENTS', 'more_clients'); // No trial allowed

    // Whitelabel
    define('FEATURE_CLIENT_PORTAL_CSS', 'client_portal_css');
    define('FEATURE_WHITE_LABEL', 'feature_white_label');

    // Enterprise
    define('FEATURE_DOCUMENTS', 'documents');

    // No Trial allowed
    define('FEATURE_USERS', 'users');// Grandfathered for old Pro users
    define('FEATURE_USER_PERMISSIONS', 'user_permissions');

    // Pro users who started paying on or before this date will be able to manage users
    define('PRO_USERS_GRANDFATHER_DEADLINE', '2016-06-04');
    define('EXTRAS_GRANDFATHER_COMPANY_ID', 35089);

    // WePay
    define('WEPAY_PRODUCTION', 'production');
    define('WEPAY_STAGE', 'stage');
    define('WEPAY_CLIENT_ID', env('WEPAY_CLIENT_ID'));
    define('WEPAY_CLIENT_SECRET', env('WEPAY_CLIENT_SECRET'));
    define('WEPAY_AUTO_UPDATE', env('WEPAY_AUTO_UPDATE', false));
    define('WEPAY_ENVIRONMENT', env('WEPAY_ENVIRONMENT', WEPAY_PRODUCTION));
    define('WEPAY_ENABLE_CANADA', env('WEPAY_ENABLE_CANADA', false));
    define('WEPAY_THEME', env('WEPAY_THEME','{"name":"Invoice Ninja","primary_color":"0b4d78","secondary_color":"0b4d78","background_color":"f8f8f8","button_color":"33b753"}'));

    define('SKYPE_CARD_RECEIPT', 'message/card.receipt');
    define('SKYPE_CARD_CAROUSEL', 'message/card.carousel');
    define('SKYPE_CARD_HERO', '');

    define('BOT_STATE_GET_EMAIL', 'get_email');
    define('BOT_STATE_GET_CODE', 'get_code');
    define('BOT_STATE_READY', 'ready');
    define('SIMILAR_MIN_THRESHOLD', 50);

    // https://docs.botframework.com/en-us/csharp/builder/sdkreference/attachments.html
    define('SKYPE_BUTTON_OPEN_URL', 'openUrl');
    define('SKYPE_BUTTON_IM_BACK', 'imBack');
    define('SKYPE_BUTTON_POST_BACK', 'postBack');
    define('SKYPE_BUTTON_CALL', 'call'); // "tel:123123123123"
    define('SKYPE_BUTTON_PLAY_AUDIO', 'playAudio');
    define('SKYPE_BUTTON_PLAY_VIDEO', 'playVideo');
    define('SKYPE_BUTTON_SHOW_IMAGE', 'showImage');
    define('SKYPE_BUTTON_DOWNLOAD_FILE', 'downloadFile');

    define('INVOICE_FIELDS_CLIENT', 'client_fields');
    define('INVOICE_FIELDS_INVOICE', 'invoice_fields');
    define('INVOICE_FIELDS_ACCOUNT', 'account_fields');

    $creditCards = [
                1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
                2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
                4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
                8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
                16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover']
            ];
    define('CREDIT_CARDS', serialize($creditCards));

    $cachedTables = [
        'currencies' => 'App\Models\Currency',
        'sizes' => 'App\Models\Size',
        'industries' => 'App\Models\Industry',
        'timezones' => 'App\Models\Timezone',
        'dateFormats' => 'App\Models\DateFormat',
        'datetimeFormats' => 'App\Models\DatetimeFormat',
        'languages' => 'App\Models\Language',
        'paymentTerms' => 'App\Models\PaymentTerm',
        'paymentTypes' => 'App\Models\PaymentType',
        'countries' => 'App\Models\Country',
        'invoiceDesigns' => 'App\Models\InvoiceDesign',
        'invoiceStatus' => 'App\Models\InvoiceStatus',
        'frequencies' => 'App\Models\Frequency',
        'gateways' => 'App\Models\Gateway',
        'gatewayTypes' => 'App\Models\GatewayType',
        'fonts' => 'App\Models\Font',
        'banks' => 'App\Models\Bank',
    ];
    define('CACHED_TABLES', serialize($cachedTables));

    function uctrans($text)
    {
        return ucwords(trans($text));
    }

    // optional trans: only return the string if it's translated
    function otrans($text)
    {
        $locale = Session::get(SESSION_LOCALE);

        if ($locale == 'en') {
            return trans($text);
        } else {
            $string = trans($text);
            $english = trans($text, [], 'en');
            return $string != $english ? $string : '';
        }
    }

    // include modules in translations
    function mtrans($entityType, $text = false)
    {
        if ( ! $text) {
            $text = $entityType;
        }

        if ( ! Utils::isNinjaProd() && $module = Module::find($entityType)) {
            return trans("{$module->getLowerName()}::texts.{$text}");
        } else {
            return trans("texts.{$text}");
        }
    }
}


/*
if (Utils::isNinjaDev())
{
  //ini_set('memory_limit','1024M');
  //set_time_limit(0);
  Auth::loginUsingId(1);
}
*/

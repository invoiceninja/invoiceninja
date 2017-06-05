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

// Application setup
Route::get('/setup', 'AppController@showSetup');
Route::post('/setup', 'AppController@doSetup');
Route::get('/install', 'AppController@install');
Route::get('/update', 'AppController@update');

// Public pages
Route::get('/', 'HomeController@showIndex');
Route::get('/log_error', 'HomeController@logError');
Route::get('/invoice_now', 'HomeController@invoiceNow');
Route::get('/keep_alive', 'HomeController@keepAlive');
Route::post('/get_started', 'AccountController@getStarted');

// Client visible pages
Route::group(['middleware' => ['lookup:contact', 'auth:client']], function () {
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

    Route::get('api/client.quotes', ['as' => 'api.client.quotes', 'uses' => 'ClientPortalController@quoteDatatable']);
    Route::get('api/client.credits', ['as' => 'api.client.credits', 'uses' => 'ClientPortalController@creditDatatable']);
    Route::get('api/client.invoices', ['as' => 'api.client.invoices', 'uses' => 'ClientPortalController@invoiceDatatable']);
    Route::get('api/client.recurring_invoices', ['as' => 'api.client.recurring_invoices', 'uses' => 'ClientPortalController@recurringInvoiceDatatable']);
    Route::get('api/client.documents', ['as' => 'api.client.documents', 'uses' => 'ClientPortalController@documentDatatable']);
    Route::get('api/client.payments', ['as' => 'api.client.payments', 'uses' => 'ClientPortalController@paymentDatatable']);
    Route::get('api/client.activity', ['as' => 'api.client.activity', 'uses' => 'ClientPortalController@activityDatatable']);
});

Route::group(['middleware' => 'lookup:license'], function () {
    Route::get('license', 'NinjaController@show_license_payment');
    Route::post('license', 'NinjaController@do_license_payment');
    Route::get('claim_license', 'NinjaController@claim_license');
});

Route::group(['middleware' => 'cors'], function () {
    Route::match(['GET', 'POST', 'OPTIONS'], '/buy_now/{gateway_type?}', 'OnlinePaymentController@handleBuyNow');
});

Route::group(['middleware' => 'lookup:postmark'], function () {
    Route::post('/hook/email_bounced', 'AppController@emailBounced');
    Route::post('/hook/email_opened', 'AppController@emailOpened');
});

Route::group(['middleware' => 'lookup:account'], function () {
    Route::post('/payment_hook/{account_key}/{gateway_id}', 'OnlinePaymentController@handlePaymentWebhook');
});

//Route::post('/hook/bot/{platform?}', 'BotController@handleMessage');

// Laravel auth routes
Route::get('/signup', ['as' => 'signup', 'uses' => 'Auth\AuthController@getRegister']);
Route::post('/signup', ['as' => 'signup', 'uses' => 'Auth\AuthController@postRegister']);
Route::get('/login', ['as' => 'login', 'uses' => 'Auth\AuthController@getLoginWrapper']);
Route::get('/logout', ['as' => 'logout', 'uses' => 'Auth\AuthController@getLogoutWrapper']);
Route::get('/recover_password', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@getEmail']);
Route::get('/password/reset/{token}', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@getReset']);
Route::get('/auth/{provider}', 'Auth\AuthController@authLogin');

Route::group(['middleware' => ['lookup:user']], function () {
    Route::get('/user/confirm/{confirmation_code}', 'UserController@confirm');
    Route::post('/login', ['as' => 'login', 'uses' => 'Auth\AuthController@postLoginWrapper']);
    Route::post('/recover_password', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@postEmail']);
    Route::post('/password/reset', ['as' => 'forgot', 'uses' => 'Auth\PasswordController@postReset']);
});

// Client auth
Route::get('/client/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@getLogin']);
Route::get('/client/logout', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getLogout']);
Route::get('/client/sessionexpired', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getSessionExpired']);
Route::get('/client/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getEmail']);
Route::get('/client/password/reset/{token}', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getReset']);

Route::group(['middleware' => ['lookup:contact']], function () {
    Route::post('/client/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@postLogin']);
    Route::post('/client/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postEmail']);
    Route::post('/client/password/reset', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postReset']);
});

if (Utils::isNinja()) {
    Route::post('/signup/register', 'AccountController@doRegister');
    Route::get('/news_feed/{user_type}/{version}/', 'HomeController@newsFeed');
}

if (Utils::isReseller()) {
    Route::post('/reseller_stats', 'AppController@stats');
}

if (Utils::isTravis()) {
    Route::get('/check_data', 'AppController@checkData');
}

Route::group(['middleware' => ['lookup:user', 'auth:user']], function () {
    Route::get('dashboard', 'DashboardController@index');
    Route::get('dashboard_chart_data/{group_by}/{start_date}/{end_date}/{currency_id}/{include_expenses}', 'DashboardController@chartData');
    Route::get('set_entity_filter/{entity_type}/{filter?}', 'AccountController@setEntityFilter');
    Route::get('hide_message', 'HomeController@hideMessage');
    Route::get('force_inline_pdf', 'UserController@forcePDFJS');
    Route::get('account/get_search_data', ['as' => 'get_search_data', 'uses' => 'AccountController@getSearchData']);
    Route::get('check_invoice_number/{invoice_id?}', 'InvoiceController@checkInvoiceNumber');
    Route::post('save_sidebar_state', 'UserController@saveSidebarState');
    Route::post('contact_us', 'HomeController@contactUs');
    Route::post('handle_command', 'BotController@handleCommand');

    Route::post('signup/validate', 'AccountController@checkEmail');
    Route::post('signup/submit', 'AccountController@submitSignup');
    Route::get('auth_unlink', 'Auth\AuthController@authUnlink');

    Route::get('settings/user_details', 'AccountController@showUserDetails');
    Route::post('settings/user_details', 'AccountController@saveUserDetails');
    Route::post('settings/payment_gateway_limits', 'AccountGatewayController@savePaymentGatewayLimits');
    Route::post('users/change_password', 'UserController@changePassword');

    Route::resource('clients', 'ClientController');
    Route::get('api/clients', 'ClientController@getDatatable');
    Route::get('api/activities/{client_id?}', 'ActivityController@getDatatable');
    Route::post('clients/bulk', 'ClientController@bulk');
    Route::get('clients/statement/{client_id}', 'ClientController@statement');

    Route::resource('tasks', 'TaskController');
    Route::get('api/tasks/{client_id?}', 'TaskController@getDatatable');
    Route::get('tasks/create/{client_id?}/{project_id?}', 'TaskController@create');
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
    Route::get('expenses/create/{vendor_id?}/{client_id?}/{category_id?}', 'ExpenseController@create');
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

    Route::get('white_label/hide_message', 'NinjaController@hideWhiteLabelMessage');
    Route::get('white_label/purchase', 'NinjaController@purchaseWhiteLabel');

    Route::get('reports', 'ReportController@showReports');
    Route::post('reports', 'ReportController@showReports');
});

Route::group([
    'middleware' => ['lookup:user', 'auth:user', 'permissions.required'],
    'permissions' => 'admin',
], function () {
    Route::get('api/users', 'UserController@getDatatable');
    Route::resource('users', 'UserController');
    Route::post('users/bulk', 'UserController@bulk');
    Route::get('send_confirmation/{user_id}', 'UserController@sendConfirmation');
    Route::get('/switch_account/{user_id}', 'UserController@switchAccount');
    Route::get('/account/{account_key}', 'UserController@viewAccountByKey');
    Route::get('/unlink_account/{user_account_id}/{user_id}', 'UserController@unlinkAccount');
    Route::get('/manage_companies', 'UserController@manageCompanies');

    Route::get('api/tokens', 'TokenController@getDatatable');
    Route::resource('tokens', 'TokenController');
    Route::post('tokens/bulk', 'TokenController@bulk');

    Route::get('api/tax_rates', 'TaxRateController@getDatatable');
    Route::resource('tax_rates', 'TaxRateController');
    Route::post('tax_rates/bulk', 'TaxRateController@bulk');

    Route::get('settings/email_preview', 'AccountController@previewEmail');
    Route::post('settings/client_portal', 'AccountController@saveClientPortalSettings');
    Route::post('settings/email_settings', 'AccountController@saveEmailSettings');
    Route::get('company/{section}/{subSection?}', 'AccountController@redirectLegacy');
    Route::get('settings/data_visualizations', 'ReportController@d3');

    Route::post('settings/change_plan', 'AccountController@changePlan');
    Route::post('settings/cancel_account', 'AccountController@cancelAccount');
    Route::post('settings/purge_data', 'AccountController@purgeData');
    Route::post('settings/company_details', 'AccountController@updateDetails');
    Route::post('settings/{section?}', 'AccountController@doSection');

    Route::post('user/setTheme', 'UserController@setTheme');
    Route::post('remove_logo', 'AccountController@removeLogo');

    Route::post('/export', 'ExportController@doExport');
    Route::post('/import', 'ImportController@doImport');
    Route::get('/cancel_import', 'ImportController@cancelImport');
    Route::post('/import_csv', 'ImportController@doImportCSV');

    Route::get('gateways/create/{show_wepay?}', 'AccountGatewayController@create');
    Route::resource('gateways', 'AccountGatewayController');
    Route::get('gateways/{public_id}/resend_confirmation', 'AccountGatewayController@resendConfirmation');
    Route::get('api/gateways', 'AccountGatewayController@getDatatable');
    Route::post('account_gateways/bulk', 'AccountGatewayController@bulk');

    Route::get('payment_terms', 'PaymentTermController@index');
    Route::get('api/payment_terms', 'PaymentTermController@getDatatable');
    Route::get('payment_terms/create', 'PaymentTermController@create');
    Route::post('payment_terms', 'PaymentTermController@store');
    Route::put('payment_terms/{payment_terms}', 'PaymentTermController@update');
    Route::get('payment_terms/{payment_terms}/edit', 'PaymentTermController@edit');
    Route::post('payment_terms/bulk', 'PaymentTermController@bulk');

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

Route::group(['middleware' => ['lookup:user', 'auth:user']], function () {
    Route::get('settings/{section?}', 'AccountController@showSection');
});

// Route groups for API
Route::group(['middleware' => ['lookup:api', 'api'], 'prefix' => 'api/v1'], function () {
    Route::get('ping', 'AccountApiController@ping');
    Route::post('login', 'AccountApiController@login');
    Route::post('oauth_login', 'AccountApiController@oauthLogin');
    Route::post('register', 'AccountApiController@register');
    Route::get('static', 'AccountApiController@getStaticData');
    Route::get('accounts', 'AccountApiController@show');
    Route::put('accounts', 'AccountApiController@update');
    Route::resource('clients', 'ClientApiController');
    Route::resource('contacts', 'ContactApiController');
    Route::get('quotes', 'QuoteApiController@index');
    Route::get('download/{invoice_id}', 'InvoiceApiController@download');
    Route::resource('invoices', 'InvoiceApiController');
    Route::resource('payments', 'PaymentApiController');
    Route::resource('tasks', 'TaskApiController');
    Route::resource('credits', 'CreditApiController');
    Route::post('hooks', 'IntegrationController@subscribe');
    Route::post('email_invoice', 'InvoiceApiController@emailInvoice');
    Route::get('user_accounts', 'AccountApiController@getUserAccounts');
    Route::resource('products', 'ProductApiController');
    Route::resource('projects', 'ProjectApiController');
    Route::resource('tax_rates', 'TaxRateApiController');
    Route::resource('users', 'UserApiController');
    Route::resource('expenses', 'ExpenseApiController');
    Route::post('add_token', 'AccountApiController@addDeviceToken');
    Route::post('update_notifications', 'AccountApiController@updatePushNotifications');
    Route::get('dashboard', 'DashboardApiController@index');
    Route::resource('documents', 'DocumentAPIController');
    Route::resource('vendors', 'VendorApiController');
    Route::resource('expense_categories', 'ExpenseCategoryApiController');
});

// Redirects for legacy links
Route::get('/rocksteady', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/about', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/contact', function () {
    return Redirect::to(NINJA_WEB_URL.'/contact', 301);
});
Route::get('/plans', function () {
    return Redirect::to(NINJA_WEB_URL.'/pricing', 301);
});
Route::get('/faq', function () {
    return Redirect::to(NINJA_WEB_URL.'/how-it-works', 301);
});
Route::get('/features', function () {
    return Redirect::to(NINJA_WEB_URL.'/features', 301);
});
Route::get('/testimonials', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/compare-online-invoicing{sites?}', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/forgot', function () {
    return Redirect::to(NINJA_APP_URL.'/recover_password', 301);
});
Route::get('/feed', function () {
    return Redirect::to(NINJA_WEB_URL.'/feed', 301);
});
Route::get('/comments/feed', function () {
    return Redirect::to(NINJA_WEB_URL.'/comments/feed', 301);
});
Route::get('/terms', function () {
    return Redirect::to(NINJA_WEB_URL.'/terms', 301);
});

/*
if (Utils::isNinjaDev())
{
  //ini_set('memory_limit','1024M');
  //set_time_limit(0);
  Auth::loginUsingId(1);
}
*/

// Include static app constants
require_once app_path() . '/Constants.php';

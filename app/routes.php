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

//Cache::flush();
//apc_clear_cache();
//dd(DB::getQueryLog());
//dd(Client::getPrivateId(1));
//dd(new DateTime());
//Event::fire('user.signup');
//dd(App::environment());
//dd(gethostname());
//Log::error('test');

// Application setup
Route::get('setup', 'AppController@showSetup');
Route::post('setup', 'AppController@doSetup');
Route::get('install', 'AppController@install');
Route::get('update', 'AppController@update');

// Public pages
Route::get('/', 'HomeController@showIndex');
Route::get('/rocksteady', 'HomeController@showIndex');
Route::get('/about', 'HomeController@showAboutUs');
Route::get('/terms', 'HomeController@showTerms');
Route::get('/contact', 'HomeController@showContactUs');
Route::get('/plans', 'HomeController@showPlans');
Route::post('/contact_submit', 'HomeController@doContactUs');
Route::get('/faq', 'HomeController@showFaq');
Route::get('/features', 'HomeController@showFeatures');
Route::get('/testimonials', 'HomeController@showTestimonials');
Route::get('/compare-online-invoicing{sites?}', 'HomeController@showCompare');

Route::get('log_error', 'HomeController@logError');
Route::get('invoice_now', 'HomeController@invoiceNow');
Route::post('get_started', 'AccountController@getStarted');

// Client visible pages
Route::get('view/{invitation_key}', 'InvoiceController@view');
Route::get('payment/{invitation_key}', 'PaymentController@show_payment');
Route::post('payment/{invitation_key}', 'PaymentController@do_payment');
Route::get('complete', 'PaymentController@offsite_payment');
Route::get('client/quotes', 'QuoteController@clientIndex');
Route::get('client/invoices', 'InvoiceController@clientIndex');
Route::get('client/payments', 'PaymentController@clientIndex');
Route::get('api/client.quotes', array('as'=>'api.client.quotes', 'uses'=>'QuoteController@getClientDatatable'));
Route::get('api/client.invoices', array('as'=>'api.client.invoices', 'uses'=>'InvoiceController@getClientDatatable'));
Route::get('api/client.payments', array('as'=>'api.client.payments', 'uses'=>'PaymentController@getClientDatatable'));

Route::get('license', 'PaymentController@show_license_payment');
Route::post('license', 'PaymentController@do_license_payment');
Route::get('claim_license', 'PaymentController@claim_license');

Route::post('signup/validate', 'AccountController@checkEmail');
Route::post('signup/submit', 'AccountController@submitSignup');

// Confide routes
Route::get('login', 'UserController@login');
Route::post('login', 'UserController@do_login');
Route::get('user/confirm/{code}', 'UserController@confirm');
Route::get('forgot_password', 'UserController@forgot_password');
Route::post('forgot_password', 'UserController@do_forgot_password');
Route::get('user/reset/{token?}', 'UserController@reset_password');
Route::post('user/reset', 'UserController@do_reset_password');
Route::get('logout', 'UserController@logout');

if (Utils::isNinja()) {
    Route::post('/signup/register', 'AccountController@doRegister');
    Route::get('/news_feed/{user_type}/{version}/', 'HomeController@newsFeed');
    Route::get('/demo', 'AccountController@demo');
}

Route::group(array('before' => 'auth'), function() {
    Route::get('dashboard', 'DashboardController@index');
    Route::get('view_archive/{entity_type}/{visible}', 'AccountController@setTrashVisible');
    Route::get('hide_message', 'HomeController@hideMessage');
    Route::get('force_inline_pdf', 'UserController@forcePDFJS');

    Route::get('api/users', array('as'=>'api.users', 'uses'=>'UserController@getDatatable'));
    Route::resource('users', 'UserController');
    Route::post('users/delete', 'UserController@delete');

    Route::get('api/products', array('as'=>'api.products', 'uses'=>'ProductController@getDatatable'));
    Route::resource('products', 'ProductController');
    Route::get('products/{product_id}/archive', 'ProductController@archive');

    Route::get('company/advanced_settings/data_visualizations', 'ReportController@d3');
    Route::get('company/advanced_settings/chart_builder', 'ReportController@report');
    Route::post('company/advanced_settings/chart_builder', 'ReportController@report');

    Route::post('company/cancel_account', 'AccountController@cancelAccount');
    Route::get('account/getSearchData', array('as' => 'getSearchData', 'uses' => 'AccountController@getSearchData'));
    Route::get('company/{section?}/{sub_section?}', 'AccountController@showSection');
    Route::post('company/{section?}/{sub_section?}', 'AccountController@doSection');
    Route::post('user/setTheme', 'UserController@setTheme');
    Route::post('remove_logo', 'AccountController@removeLogo');
    Route::post('account/go_pro', 'AccountController@enableProPlan');

    Route::resource('clients', 'ClientController');
    Route::get('api/clients', array('as'=>'api.clients', 'uses'=>'ClientController@getDatatable'));
    Route::get('api/activities/{client_id?}', array('as'=>'api.activities', 'uses'=>'ActivityController@getDatatable'));
    Route::post('clients/bulk', 'ClientController@bulk');

    Route::get('recurring_invoices', 'InvoiceController@recurringIndex');
    Route::get('api/recurring_invoices/{client_id?}', array('as'=>'api.recurring_invoices', 'uses'=>'InvoiceController@getRecurringDatatable'));

    Route::get('invoices/invoice_history/{invoice_id}', 'InvoiceController@invoiceHistory');
    Route::get('quotes/quote_history/{invoice_id}', 'InvoiceController@invoiceHistory');
    
    Route::resource('invoices', 'InvoiceController');
    Route::get('api/invoices/{client_id?}', array('as'=>'api.invoices', 'uses'=>'InvoiceController@getDatatable'));
    Route::get('invoices/create/{client_id?}', 'InvoiceController@create');
    Route::get('invoices/{public_id}/clone', 'InvoiceController@cloneInvoice');
    Route::post('invoices/bulk', 'InvoiceController@bulk');

    Route::get('quotes/create/{client_id?}', 'QuoteController@create');
    Route::get('quotes/{public_id}/clone', 'InvoiceController@cloneInvoice');
    Route::get('quotes/{public_id}/edit', 'InvoiceController@edit');
    Route::put('quotes/{public_id}', 'InvoiceController@update');
    Route::get('quotes/{public_id}', 'InvoiceController@edit');
    Route::post('quotes', 'InvoiceController@store');
    Route::get('quotes', 'QuoteController@index');
    Route::get('api/quotes/{client_id?}', array('as'=>'api.quotes', 'uses'=>'QuoteController@getDatatable'));
    Route::post('quotes/bulk', 'QuoteController@bulk');

    Route::get('payments/{id}/edit', function() {
        return View::make('header');
    });
    Route::resource('payments', 'PaymentController');
    Route::get('payments/create/{client_id?}/{invoice_id?}', 'PaymentController@create');
    Route::get('api/payments/{client_id?}', array('as'=>'api.payments', 'uses'=>'PaymentController@getDatatable'));
    Route::post('payments/bulk', 'PaymentController@bulk');

    Route::get('credits/{id}/edit', function() {
        return View::make('header');
    });
    Route::resource('credits', 'CreditController');
    Route::get('credits/create/{client_id?}/{invoice_id?}', 'CreditController@create');
    Route::get('api/credits/{client_id?}', array('as'=>'api.credits', 'uses'=>'CreditController@getDatatable'));
    Route::post('credits/bulk', 'CreditController@bulk');

    //Route::resource('timesheets', 'TimesheetController');
});

// Route group for API
Route::group(array('prefix' => 'api/v1', 'before' => 'auth.basic'), function()
{
    Route::resource('ping', 'ClientApiController@ping');
    Route::resource('clients', 'ClientApiController');
    Route::resource('invoices', 'InvoiceApiController');
    Route::resource('quotes', 'QuoteApiController');
    Route::resource('payments', 'PaymentApiController');
    Route::post('api/hooks', 'IntegrationController@subscribe');
});

define('CONTACT_EMAIL', Config::get('mail.from.address'));
define('CONTACT_NAME', Config::get('mail.from.name'));
define('SITE_URL', Config::get('app.url'));

define('ENV_DEVELOPMENT', 'local');
define('ENV_STAGING', 'staging');
define('ENV_PRODUCTION', 'fortrabbit');

define('RECENTLY_VIEWED', 'RECENTLY_VIEWED');
define('ENTITY_CLIENT', 'client');
define('ENTITY_INVOICE', 'invoice');
define('ENTITY_RECURRING_INVOICE', 'recurring_invoice');
define('ENTITY_PAYMENT', 'payment');
define('ENTITY_CREDIT', 'credit');
define('ENTITY_QUOTE', 'quote');

define('PERSON_CONTACT', 'contact');
define('PERSON_USER', 'user');

define('ACCOUNT_DETAILS', 'details');
define('ACCOUNT_NOTIFICATIONS', 'notifications');
define('ACCOUNT_IMPORT_EXPORT', 'import_export');
define('ACCOUNT_PAYMENTS', 'payments');
define('ACCOUNT_MAP', 'import_map');
define('ACCOUNT_EXPORT', 'export');
define('ACCOUNT_PRODUCTS', 'products');
define('ACCOUNT_ADVANCED_SETTINGS', 'advanced_settings');
define('ACCOUNT_INVOICE_SETTINGS', 'invoice_settings');
define('ACCOUNT_INVOICE_DESIGN', 'invoice_design');
define('ACCOUNT_CHART_BUILDER', 'chart_builder');
define('ACCOUNT_USER_MANAGEMENT', 'user_management');
define('ACCOUNT_DATA_VISUALIZATIONS', 'data_visualizations');

define("ACTIVITY_TYPE_CREATE_CLIENT", 1);
define("ACTIVITY_TYPE_ARCHIVE_CLIENT", 2);
define("ACTIVITY_TYPE_DELETE_CLIENT", 3);

define("ACTIVITY_TYPE_CREATE_INVOICE", 4);
define("ACTIVITY_TYPE_UPDATE_INVOICE", 5);
define("ACTIVITY_TYPE_EMAIL_INVOICE", 6);
define("ACTIVITY_TYPE_VIEW_INVOICE", 7);
define("ACTIVITY_TYPE_ARCHIVE_INVOICE", 8);
define("ACTIVITY_TYPE_DELETE_INVOICE", 9);

define("ACTIVITY_TYPE_CREATE_PAYMENT", 10);
define("ACTIVITY_TYPE_UPDATE_PAYMENT", 11);
define("ACTIVITY_TYPE_ARCHIVE_PAYMENT", 12);
define("ACTIVITY_TYPE_DELETE_PAYMENT", 13);

define("ACTIVITY_TYPE_CREATE_CREDIT", 14);
define("ACTIVITY_TYPE_UPDATE_CREDIT", 15);
define("ACTIVITY_TYPE_ARCHIVE_CREDIT", 16);
define("ACTIVITY_TYPE_DELETE_CREDIT", 17);

define("ACTIVITY_TYPE_CREATE_QUOTE", 18);
define("ACTIVITY_TYPE_UPDATE_QUOTE", 19);
define("ACTIVITY_TYPE_EMAIL_QUOTE", 20);
define("ACTIVITY_TYPE_VIEW_QUOTE", 21);
define("ACTIVITY_TYPE_ARCHIVE_QUOTE", 22);
define("ACTIVITY_TYPE_DELETE_QUOTE", 23);

define("ACTIVITY_TYPE_RESTORE_QUOTE", 24);
define("ACTIVITY_TYPE_RESTORE_INVOICE", 25);
define("ACTIVITY_TYPE_RESTORE_CLIENT", 26);
define("ACTIVITY_TYPE_RESTORE_PAYMENT", 27);
define("ACTIVITY_TYPE_RESTORE_CREDIT", 28);

define('DEFAULT_INVOICE_NUMBER', '0001');
define('RECENTLY_VIEWED_LIMIT', 8);
define('LOGGED_ERROR_LIMIT', 100);
define('RANDOM_KEY_LENGTH', 32);
define('MAX_NUM_CLIENTS', 500);
define('MAX_NUM_CLIENTS_PRO', 20000);
define('MAX_NUM_USERS', 20);

define('INVOICE_STATUS_DRAFT', 1);
define('INVOICE_STATUS_SENT', 2);
define('INVOICE_STATUS_VIEWED', 3);
define('INVOICE_STATUS_PARTIAL', 4);
define('INVOICE_STATUS_PAID', 5);

define('PAYMENT_TYPE_CREDIT', 1);

define('FREQUENCY_WEEKLY', 1);
define('FREQUENCY_TWO_WEEKS', 2);
define('FREQUENCY_FOUR_WEEKS', 3);
define('FREQUENCY_MONTHLY', 4);
define('FREQUENCY_THREE_MONTHS', 5);
define('FREQUENCY_SIX_MONTHS', 6);
define('FREQUENCY_ANNUALLY', 7);

define('SESSION_TIMEZONE', 'timezone');
define('SESSION_CURRENCY', 'currency');
define('SESSION_DATE_FORMAT', 'dateFormat');
define('SESSION_DATE_PICKER_FORMAT', 'datePickerFormat');
define('SESSION_DATETIME_FORMAT', 'datetimeFormat');
define('SESSION_COUNTER', 'sessionCounter');
define('SESSION_LOCALE', 'sessionLocale');

define('DEFAULT_TIMEZONE', 'US/Eastern');
define('DEFAULT_CURRENCY', 1); // US Dollar
define('DEFAULT_DATE_FORMAT', 'M j, Y');
define('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy');
define('DEFAULT_DATETIME_FORMAT', 'F j, Y, g:i a');
define('DEFAULT_QUERY_CACHE', 120); // minutes
define('DEFAULT_LOCALE', 'en');

define('RESULT_SUCCESS', 'success');
define('RESULT_FAILURE', 'failure');


define('PAYMENT_LIBRARY_OMNIPAY', 1);
define('PAYMENT_LIBRARY_PHP_PAYMENTS', 2);

define('GATEWAY_AUTHORIZE_NET', 1);
define('GATEWAY_AUTHORIZE_NET_SIM', 2);
define('GATEWAY_PAYPAL_EXPRESS', 17);
define('GATEWAY_STRIPE', 23);
define('GATEWAY_TWO_CHECKOUT', 27);
define('GATEWAY_BEANSTREAM', 29);
define('GATEWAY_PSIGATE', 30);
define('GATEWAY_MOOLAH', 31);

define('EVENT_CREATE_CLIENT', 1);
define('EVENT_CREATE_INVOICE', 2);
define('EVENT_CREATE_QUOTE', 3);
define('EVENT_CREATE_PAYMENT', 4);

define('REQUESTED_PRO_PLAN', 'REQUESTED_PRO_PLAN');
define('DEMO_ACCOUNT_ID', 'DEMO_ACCOUNT_ID');
define('NINJA_ACCOUNT_KEY', 'zg4ylmzDkdkPOT8yoKQw9LTWaoZJx79h');
define('NINJA_GATEWAY_ID', GATEWAY_AUTHORIZE_NET);
define('NINJA_GATEWAY_CONFIG', '{"apiLoginId":"626vWcD5","transactionKey":"4bn26TgL9r4Br4qJ","testMode":"","developerMode":""}');
define('NINJA_WEB_URL', 'https://www.invoiceninja.com');
define('NINJA_APP_URL', 'https://app.invoiceninja.com');
define('NINJA_VERSION', '1.6.0');
define('NINJA_DATE', '2000-01-01');
define('NINJA_FROM_EMAIL', 'maildelivery@invoiceninja.com');
define('RELEASES_URL', 'https://github.com/hillelcoren/invoice-ninja/releases/');

define('COUNT_FREE_DESIGNS', 4);
define('PRO_PLAN_PRICE', 50);
define('PRODUCT_ONE_CLICK_INSTALL', 1);
define('PRODUCT_INVOICE_DESIGNS', 2);
define('PRODUCT_WHITE_LABEL', 3);
define('PRODUCT_SELF_HOST', 4);
define('WHITE_LABEL_AFFILIATE_KEY', '92D2J5');
define('INVOICE_DESIGNS_AFFILIATE_KEY', 'T3RS74');
define('SELF_HOST_AFFILIATE_KEY', '8S69AD');

define('USER_TYPE_SELF_HOST', 'SELF_HOST');
define('USER_TYPE_CLOUD_HOST', 'CLOUD_HOST');
define('NEW_VERSION_AVAILABLE', 'NEW_VERSION_AVAILABLE');

/*
define('GATEWAY_AMAZON', 30);
define('GATEWAY_BLUEPAY', 31);
define('GATEWAY_BRAINTREE', 32);
define('GATEWAY_GOOGLE', 33);
define('GATEWAY_QUICKBOOKS', 35);
*/

/**
 * TEST VALUES FOR THE CREDIT CARDS
 * NUMBER IS FOR THE BINARY COUNT FOR WHICH IMAGES TO DISPLAY
 * card IS FOR CARD IMAGE AND text IS FOR CARD NAME (TO ADD TO alt FOR IMAGE)
**/
$creditCards = [
            1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
            2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
            4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
            8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
            16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover']
        ];

define('CREDIT_CARDS', serialize($creditCards));


HTML::macro('nav_link', function($url, $text, $url2 = '', $extra = '') {
    $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2) ) ? ' class="active"' : '';
    $title = ucwords(trans("texts.$text")) . Utils::getProLabel($text);
    return '<li'.$class.'><a href="'.URL::to($url).'" '.$extra.'>'.$title.'</a></li>';
});

HTML::macro('tab_link', function($url, $text, $active = false) {
    $class = $active ? ' class="active"' : '';
    return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
});

HTML::macro('menu_link', function($type) {
    $types = $type.'s';
    $Type = ucfirst($type);
    $Types = ucfirst($types);
    $class = ( Request::is($types) || Request::is('*'.$type.'*')) && !Request::is('*advanced_settings*') ? ' active' : '';

    return '<li class="dropdown '.$class.'">
           <a href="'.URL::to($types).'" class="dropdown-toggle">'.trans("texts.$types").'</a>
           <ul class="dropdown-menu" id="menu1">
             <li><a href="'.URL::to($types.'/create').'">'.trans("texts.new_$type").'</a></li>
            </ul>
          </li>';
});

HTML::macro('image_data', function($imagePath) {
    return 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path().'/'.$imagePath));
});


HTML::macro('breadcrumbs', function() {
    $str = '<ol class="breadcrumb">';

    // Get the breadcrumbs by exploding the current path.
    $basePath = Utils::basePath();
    $parts = explode('?', $_SERVER['REQUEST_URI']);
    $path = $parts[0];

    if ($basePath != '/') {
        $path = str_replace($basePath, '', $path);
    }
    $crumbs = explode('/', $path);

    foreach ($crumbs as $key => $val) {
        if (is_numeric($val)) {
            unset($crumbs[$key]);
        }
    }

    $crumbs = array_values($crumbs);
    for ($i=0; $i<count($crumbs); $i++) {
        $crumb = trim($crumbs[$i]);
        if (!$crumb) {
            continue;
        }
        if ($crumb == 'company') {
            return '';
        }
        $name = trans("texts.$crumb");
        if ($i==count($crumbs)-1) {
            $str .= "<li class='active'>$name</li>";
        } else {
            $str .= '<li>'.link_to($crumb, $name).'</li>';
        }
    }
    return $str . '</ol>';
});

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

Validator::extend('positive', function($attribute, $value, $parameters) {
    return Utils::parseFloat($value) >= 0;
});

Validator::extend('has_credit', function($attribute, $value, $parameters) {
    $publicClientId = $parameters[0];
    $amount = $parameters[1];

    $client = Client::scope($publicClientId)->firstOrFail();
    $credit = $client->getTotalCredit();

    return $credit >= $amount;
});


/*
// Log all SQL queries to laravel.log
Event::listen('illuminate.query', function($query, $bindings, $time, $name)
{
    $data = compact('bindings', 'time', 'name');

    // Format binding data for sql insertion
    foreach ($bindings as $i => $binding)
    {   
        if ($binding instanceof \DateTime)
        {   
            $bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
        }
        else if (is_string($binding))
        {   
            $bindings[$i] = "'$binding'";
        }   
    }       

    // Insert bindings into query
    $query = str_replace(array('%', '?'), array('%%', '%s'), $query);
    $query = vsprintf($query, $bindings); 

    Log::info($query, $data);
});
*/

/*
if (Auth::check() && Auth::user()->id === 1)
{
  Auth::loginUsingId(1);
}
*/

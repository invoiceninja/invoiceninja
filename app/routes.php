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

//apc_clear_cache();
//Cache::flush();

//dd(DB::getQueryLog());
//dd(Client::getPrivateId(1));
//dd(new DateTime());
//Event::fire('user.signup');
//dd(App::environment());
//dd(gethostname());
//Log::error('test');

/*
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
Route::get('/send_emails', function() {
	Artisan::call('ninja:send-invoices');	
});
*/

Route::get('/', 'HomeController@showWelcome');
Route::get('/rocksteady', 'HomeController@showWelcome');
Route::get('/about', 'HomeController@showAboutUs');
Route::get('/terms', 'HomeController@showTerms');
Route::get('/contact', 'HomeController@showContactUs');
Route::post('/contact', 'HomeController@doContactUs');

Route::get('log_error', 'HomeController@logError');
Route::post('get_started', 'AccountController@getStarted');

Route::get('view/{invitation_key}', 'InvoiceController@view');
Route::get('payment/{invitation_key}', 'PaymentController@show_payment');
Route::post('payment/{invitation_key}', 'PaymentController@do_payment');
Route::get('complete', 'PaymentController@offsite_payment');

Route::post('signup/validate', 'AccountController@checkEmail');
Route::post('signup/submit', 'AccountController@submitSignup');

// Confide routes
Route::get('login', 'UserController@login');
Route::post('login', 'UserController@do_login');
Route::get('user/confirm/{code}', 'UserController@confirm');
Route::get('forgot_password', 'UserController@forgot_password');
Route::post('forgot_password', 'UserController@do_forgot_password');
Route::get('user/reset/{token}', 'UserController@reset_password');
Route::post('user/reset', 'UserController@do_reset_password');
Route::get('logout', 'UserController@logout');


Route::group(array('before' => 'auth'), function()
{   
	Route::get('dashboard', 'DashboardController@index');
  Route::get('view_archive/{entity_type}/{visible}', 'AccountController@setTrashVisible');

	Route::get('account/getSearchData', array('as' => 'getSearchData', 'uses' => 'AccountController@getSearchData'));
	Route::get('company/{section?}', 'AccountController@showSection');	
	Route::post('company/{section?}', 'AccountController@doSection');
	Route::post('user/setTheme', 'UserController@setTheme');
  Route::post('remove_logo', 'AccountController@removeLogo');

	Route::resource('clients', 'ClientController');
	Route::get('api/clients', array('as'=>'api.clients', 'uses'=>'ClientController@getDatatable'));
	Route::get('api/activities/{client_id?}', array('as'=>'api.activities', 'uses'=>'ActivityController@getDatatable'));	
	Route::post('clients/bulk', 'ClientController@bulk');

	Route::get('recurring_invoices', 'InvoiceController@recurringIndex');
	Route::get('api/recurring_invoices/{client_id?}', array('as'=>'api.recurring_invoices', 'uses'=>'InvoiceController@getRecurringDatatable'));	

	Route::resource('invoices', 'InvoiceController');
	Route::get('api/invoices/{client_id?}', array('as'=>'api.invoices', 'uses'=>'InvoiceController@getDatatable'));	
	Route::get('invoices/create/{client_id?}', 'InvoiceController@create');
	Route::post('invoices/bulk', 'InvoiceController@bulk');

	Route::get('payments/{id}/edit', function() { return View::make('header'); });
	Route::resource('payments', 'PaymentController');
	Route::get('payments/create/{client_id?}/{invoice_id?}', 'PaymentController@create');
	Route::get('api/payments/{client_id?}', array('as'=>'api.payments', 'uses'=>'PaymentController@getDatatable'));
	Route::post('payments/bulk', 'PaymentController@bulk');
	
	Route::get('credits/{id}/edit', function() { return View::make('header'); });
	Route::resource('credits', 'CreditController');
	Route::get('credits/create/{client_id?}/{invoice_id?}', 'CreditController@create');
	Route::get('api/credits/{client_id?}', array('as'=>'api.credits', 'uses'=>'CreditController@getDatatable'));	
	Route::post('credits/bulk', 'CreditController@bulk');
	
	Route::get('reports', 'ReportController@report');
	Route::post('reports', 'ReportController@report');
});




HTML::macro('nav_link', function($url, $text, $url2 = '', $extra = '') {
    $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2) ) ? ' class="active"' : '';
    return '<li'.$class.'><a href="'.URL::to($url).'" '.$extra.'>'.$text.'</a></li>';
});

HTML::macro('tab_link', function($url, $text, $active = false) {
    $class = $active ? ' class="active"' : '';
    return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
});

HTML::macro('menu_link', function($type) {
	$types = $type.'s';
	$Type = ucfirst($type);
	$Types = ucfirst($types);
	$class = ( Request::is($types) || Request::is('*'.$type.'*')) ? ' active' : '';
    $str= '<li class="dropdown '.$class.'">
			  <a href="'.URL::to($types).'" class="dropdown-toggle">'.$Types.'</a>
			  <ul class="dropdown-menu" id="menu1">
			  <li><a href="'.URL::to($types.'/create').'">New '.$Type.'</a></li>';
			    //<li><a href="'.URL::to($types).'">View '.$Types.'</a></li>';
			  
	/*
	if ($Type == 'Invoice') {
		$str .= '<li><a href="'.URL::to('recurring_invoices').'">Recurring Invoices</a></li>';
	}
	*/
	
	return $str . '</ul>
			</li>';
});

HTML::macro('image_data', function($imagePath) {
	return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($imagePath));
});


HTML::macro('breadcrumbs', function() {
  $str = '<ol class="breadcrumb">';

  // Get the breadcrumbs by exploding the current path.
  $basePath = Utils::basePath();
  $path = $_SERVER['REQUEST_URI'];
  if ($basePath != '/')
  {
    $path = str_replace($basePath, '', $path);
  }
  $crumbs = explode('/', $path);

  foreach ($crumbs as $key => $val)
  {
    if (is_numeric($val))
    {
      unset($crumbs[$key]);
    }
  }

  $crumbs = array_values($crumbs);
  for ($i=0; $i<count($crumbs); $i++) {
    $crumb = trim($crumbs[$i]);
    if (!$crumb) continue;
    if ($crumb == 'company') return '';
    $name = ucwords($crumb);
    if ($i==count($crumbs)-1) 
    {
      $str .= "<li class='active'>$name</li>";  
    }
    else
    {
      $str .= '<li>'.link_to($crumb, $name).'</li>';   
    }
  }
  return $str . '</ol>';
});

define('CONTACT_EMAIL', 'contact@invoiceninja.com');

define('ENV_DEVELOPMENT', 'local');
define('ENV_STAGING', 'staging');
define('ENV_PRODUCTION', 'fortrabbit');

define('RECENTLY_VIEWED', 'RECENTLY_VIEWED');
define('ENTITY_CLIENT', 'client');
define('ENTITY_INVOICE', 'invoice');
define('ENTITY_RECURRING_INVOICE', 'recurring_invoice');
define('ENTITY_PAYMENT', 'payment');
define('ENTITY_CREDIT', 'credit');

define('PERSON_CONTACT', 'contact');
define('PERSON_USER', 'user');

define('ACCOUNT_DETAILS', 'details');
define('ACCOUNT_NOTIFICATIONS', 'notifications');
define('ACCOUNT_IMPORT_EXPORT', 'import_export');
define('ACCOUNT_PAYMENTS', 'payments');
define('ACCOUNT_MAP', 'import_map');
define('ACCOUNT_EXPORT', 'export');

define('DEFAULT_INVOICE_NUMBER', '0001');
define('RECENTLY_VIEWED_LIMIT', 8);
define('LOGGED_ERROR_LIMIT', 100);
define('RANDOM_KEY_LENGTH', 32);
define('MAX_NUM_CLIENTS', 1000);

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

define('DEFAULT_TIMEZONE', 'US/Eastern');
define('DEFAULT_CURRENCY', 1); // US Dollar
define('DEFAULT_DATE_FORMAT', 'M j, Y');
define('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy');
define('DEFAULT_DATETIME_FORMAT', 'F j, Y, g:i a');
define('DEFAULT_QUERY_CACHE', 120);

define('GATEWAY_PAYPAL_EXPRESS', 17);


if (Auth::check() && !Session::has(SESSION_TIMEZONE)) 
{
	Event::fire('user.refresh');
}


Validator::extend('positive', function($attribute, $value, $parameters)
{
    return Utils::parseFloat($value) > 0;
});

Validator::extend('has_credit', function($attribute, $value, $parameters)
{
	$publicClientId = $parameters[0];
	$amount = $parameters[1];
	
	$client = Client::scope($publicClientId)->firstOrFail();
	$credit = $client->getTotalCredit();
    
    return $credit >= $amount;
});
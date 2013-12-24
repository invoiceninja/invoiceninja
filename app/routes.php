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

//dd(DB::getQueryLog());
//dd(Client::getPrivateId(1));
//dd(new DateTime());
//Event::fire('user.signup');
//dd(App::environment());


include_once(app_path().'/libraries/utils.php');  // TODO_FIX
include_once(app_path().'/handlers/UserEventHandler.php');  // TODO_FIX

Route::get('/send_emails', function() {
	Artisan::call('ninja:send-invoices');	
});


Route::get('/', 'HomeController@showWelcome');
Route::post('get_started', 'AccountController@getStarted');

Route::get('view/{invoice_key}', 'InvoiceController@view');
Route::get('payment/{invoice_key}', 'InvoiceController@show_payment');
Route::get('complete', 'InvoiceController@do_payment');

Route::post('signup/validate', 'AccountController@checkEmail');
Route::post('signup/submit', 'AccountController@submitSignup');

// Confide routes
Route::get('login', 'UserController@login');
Route::post('login', 'UserController@do_login');
//Route::get( 'user/confirm/{code}', 'UserController@confirm');
Route::get('forgot_password', 'UserController@forgot_password');
Route::post('forgot_password', 'UserController@do_forgot_password');
//Route::get('user/reset_password/{token}', 'UserController@reset_password');
//Route::post('user/reset_password', 'UserController@do_reset_password');
Route::get('logout', 'UserController@logout');


Route::filter('auth', function()
{
	if (!Auth::check())
    {
        return Redirect::to('/');
    }
});

Route::group(array('before' => 'auth'), function()
{   
	Route::get('home', function() { return View::make('header'); });
	Route::get('account/{section?}', 'AccountController@showSection');
	Route::post('account/{section?}', 'AccountController@doSection');
	Route::post('user/setTheme', 'UserController@setTheme');

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
	Route::get('payments/create/{client_id?}', 'PaymentController@create');
	Route::get('api/payments/{client_id?}', array('as'=>'api.payments', 'uses'=>'PaymentController@getDatatable'));
	Route::post('payments/bulk', 'PaymentController@bulk');
	
	Route::get('credits/{id}/edit', function() { return View::make('header'); });
	Route::resource('credits', 'CreditController');
	Route::get('credits/create/{client_id?}', 'CreditController@create');
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



define("ENV_DEVELOPMENT", "local");
define("ENV_STAGING", "staging");
define("ENV_PRODUCTION", "production");

define("RECENTLY_VIEWED", "RECENTLY_VIEWED");
define("ENTITY_CLIENT", "client");
define("ENTITY_INVOICE", "invoice");
define("ENTITY_RECURRING_INVOICE", "recurring_invoice");
define("ENTITY_PAYMENT", "payment");
define("ENTITY_CREDIT", "credit");

define("PERSON_CONTACT", "contact");
define("PERSON_USER", "user");

define("ACCOUNT_DETAILS", "details");
define("ACCOUNT_SETTINGS", "settings");
define("ACCOUNT_IMPORT", "import");
define("ACCOUNT_MAP", "import_map");
define("ACCOUNT_EXPORT", "export");

define("DEFAULT_INVOICE_NUMBER", "0001");
define("RECENTLY_VIEWED_LIMIT", 8);

define('INVOICE_STATUS_DRAFT', 1);
define('INVOICE_STATUS_SENT', 2);
define('INVOICE_STATUS_VIEWED', 3);
define('INVOICE_STATUS_PARTIAL', 4);
define('INVOICE_STATUS_PAID', 5);

define('FREQUENCY_WEEKLY', 1);
define('FREQUENCY_TWO_WEEKS', 2);
define('FREQUENCY_FOUR_WEEKS', 3);
define('FREQUENCY_MONTHLY', 4);
define('FREQUENCY_THREE_MONTHS', 5);
define('FREQUENCY_SIX_MONTHS', 6);
define('FREQUENCY_ANNUALLY', 7);

define('SESSION_TIMEZONE', 'timezone');
define('SESSION_DATE_FORMAT', 'dateFormat');
define('SESSION_DATETIME_FORMAT', 'datetimeFormat');

define('DEFAULT_TIMEZONE', 'US/Eastern');
define('DEFAULT_DATE_FORMAT', 'F j, Y');
define('DEFAULT_DATETIME_FORMAT', 'F j, Y, g:i a');

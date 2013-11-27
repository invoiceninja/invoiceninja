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

Route::get('/', 'HomeController@showWelcome');
Route::post('get_started', 'AccountController@getStarted');

Route::get('view/{invoice_key}', 'InvoiceController@view');
Route::get('payment/{invoice_key}', 'InvoiceController@show_payment');
Route::get('complete', 'InvoiceController@do_payment');


Route::filter('auth', function()
{
    if (!Auth::check())
    {
        return Redirect::to('/');
    }
});

Route::group(array('before' => 'auth'), function()
{   
	Route::get('account/{section?}', 'AccountController@showSection');
	Route::post('account/{section?}', 'AccountController@doSection');

	Route::resource('clients', 'ClientController');
	Route::get('api/clients', array('as'=>'api.clients', 'uses'=>'ClientController@getDatatable'));

	Route::resource('invoices', 'InvoiceController');
	Route::get('api/invoices', array('as'=>'api.invoices', 'uses'=>'InvoiceController@getDatatable'));
	Route::get('invoices/create/{client_id}', 'InvoiceController@create');

	Route::get('payments', 'PaymentController@index');
	Route::get('api/payments', array('as'=>'api.payments', 'uses'=>'PaymentController@getDatatable'));

	Route::get('home', function() { return View::make('header'); });
	Route::get('reports', function() { return View::make('header'); });
	Route::get('payments/create', function() { return View::make('header'); });
});

// Confide routes
//Route::get( 'user/create',                 'UserController@create');
//Route::post('user',                        'UserController@store');
Route::get('login',                  'UserController@login');
Route::post('login',                  'UserController@do_login');
//Route::get( 'user/confirm/{code}',         'UserController@confirm');
//Route::get( 'user/forgot_password',        'UserController@forgot_password');
//Route::post('user/forgot_password',        'UserController@do_forgot_password');
//Route::get( 'user/reset_password/{token}', 'UserController@reset_password');
//Route::post('user/reset_password',         'UserController@do_reset_password');
Route::get('logout',                 'UserController@logout');




HTML::macro('nav_link', function($url, $text, $url2 = '') {
    $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2) ) ? ' class="active"' : '';
    return '<li'.$class.'><a href="'.URL::to($url).'">'.$text.'</a></li>';
});

HTML::macro('menu_link', function($type) {
	$types = $type.'s';
	$Type = ucfirst($type);
	$Types = ucfirst($types);
	$class = ( Request::is($types) || Request::is($types.'/*')) ? ' active' : '';
    return '<li class="dropdown '.$class.'">
			  <a href="'.URL::to($types).'" class="dropdown-toggle">'.$Types.'</a>
			  <ul class="dropdown-menu" id="menu1">
			    <!-- <li><a href="'.URL::to($types).'">List '.$Types.'</a></li> -->
			    <li><a href="'.URL::to($types.'/create').'">New '.$Type.'</a></li>
			  </ul>
			</li>';
});

HTML::macro('image_data', function($imagePath) {
	return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($imagePath));
});


function pluralize($string, $count) 
{
	$string = str_replace('?', $count, $string);
	return $count == 1 ? $string : $string . 's';
}

function toArray($data)
{
	return json_decode(json_encode((array) $data), true);
}

define("ENV_DEVELOPMENT", "local");
define("ENV_STAGING", "staging");
define("ENV_PRODUCTION", "production");

define("ACCOUNT_DETAILS", "details");
define("ACCOUNT_SETTINGS", "settings");
define("ACCOUNT_IMPORT", "import");
define("ACCOUNT_MAP", "import_map");
define("ACCOUNT_EXPORT", "export");
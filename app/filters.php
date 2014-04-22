<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

App::before(function($request)
{
  $count = Session::get(SESSION_COUNTER, 0);
  Session::put(SESSION_COUNTER, ++$count);
  
  if (App::environment() == ENV_PRODUCTION)
  {
    if (!Request::secure()) 
    {
      return Redirect::secure(Request::getRequestUri());      
    }
  }

  if (Input::has('lang'))
  {
    $locale = Input::get('lang');
    App::setLocale($locale);
    Session::set(SESSION_LOCALE, $locale);    

    if (Auth::check())
    {
      if ($language = Language::whereLocale($locale)->first())
      {
        $account = Auth::user()->account;
        $account->language_id = $language->id;
        $account->save();
      }
    }
  } 
  else if (Auth::check())
  {
    $locale = Session::get(SESSION_LOCALE, DEFAULT_LOCALE);
    App::setLocale($locale);    
  }
});


App::after(function($request, $response)
{
	//
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function()
{
	if (Auth::guest()) return Redirect::guest('/');
});


Route::filter('auth.basic', function()
{
	return Auth::basic();
});

/*
|--------------------------------------------------------------------------
| Guest Filter
|--------------------------------------------------------------------------
|
| The "guest" filter is the counterpart of the authentication filters as
| it simply checks that the current user is not logged in. A redirect
| response will be issued if they are, which you may freely change.
|
*/

Route::filter('guest', function()
{
	if (Auth::check()) return Redirect::to('/');
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function()
{
	$token = Request::ajax() ? Request::header('X-CSRF-Token') : Input::get('_token');
	
   	if (Session::token() != $token) 
   	{
   		return Redirect::to('/');
			//throw new Illuminate\Session\TokenMismatchException;
   	}
});
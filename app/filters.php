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
  if (App::environment() == ENV_PRODUCTION)
  {
    if (!Request::secure()) 
    {
      return Redirect::secure(Request::getRequestUri());      
    }
  }

  if (Auth::check())
  {
    $count = Session::get(SESSION_COUNTER, 0);
    Session::put(SESSION_COUNTER, ++$count);
    
    // check the application is up to date and for any news feed messages
    if (!Utils::startsWith($_SERVER['REQUEST_URI'], '/news_feed') && !Session::has('news_feed_id')) {
      $data = false;
      if (Utils::isNinja()) {
        $data = Utils::getNewsFeedResponse();
      } else {
        $file = @file_get_contents(NINJA_URL . '/news_feed/' . Utils::getUserType() . '/' . NINJA_VERSION);
        $data = @json_decode($file);
      }      
      if ($data) {        
        if ($data->version != NINJA_VERSION) {
          $params = [
            'user_version' => NINJA_VERSION, 
            'latest_version'=> $data->version,
            'releases_link' => link_to(RELEASES_URL, 'Invoice Ninja', ['target' => '_blank'])
          ];
          Session::put('news_feed_id', NEW_VERSION_AVAILABLE);
          Session::put('news_feed_message', trans('texts.new_version_available', $params));
        } else {
          Session::put('news_feed_id', $data->id);
          if ($data->message && $data->id > Auth::user()->news_feed_id) {
            Session::put('news_feed_message', $data->message);
          }
        }        
      } else {
        Session::put('news_feed_id', true);
      }
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
  if (Auth::guest()) 
  {
    if (Utils::isNinja() || Account::count() == 0)
    {
      return Redirect::guest('/');
    } 
    else 
    {
      return Redirect::guest('/login');
    }
  }
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
    Session::flash('warning', trans('texts.session_expired'));   

 		return Redirect::to('/');
		//throw new Illuminate\Session\TokenMismatchException;
 	}
});
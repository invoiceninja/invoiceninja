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
  // Ensure all request are over HTTPS in production
  if (App::environment() == ENV_PRODUCTION)
  {
    if (!Request::secure()) 
    {
      return Redirect::secure(Request::getRequestUri());      
    }
  }

  // If the database doens't yet exist we'll skip the rest
  if (!Utils::isNinja() && !Utils::isDatabaseSetup())
  {
    return;
  }

  // check the application is up to date and for any news feed messages
  if (Auth::check())
  {
    $count = Session::get(SESSION_COUNTER, 0);
    Session::put(SESSION_COUNTER, ++$count);
    
    if (!Utils::startsWith($_SERVER['REQUEST_URI'], '/news_feed') && !Session::has('news_feed_id')) {
      $data = false;
      if (Utils::isNinja()) {
        $data = Utils::getNewsFeedResponse();
      } else {
        $file = @file_get_contents(NINJA_APP_URL . '/news_feed/' . Utils::getUserType() . '/' . NINJA_VERSION);
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

  // Check if we're requesting to change the account's language
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

  // Make sure the account/user localization settings are in the session
  if (Auth::check() && !Session::has(SESSION_TIMEZONE)) 
  {
    Event::fire('user.refresh');
  }

  // Check if the user is claiming a license (ie, additional invoices, white label, etc.)
  $claimingLicense = Utils::startsWith($_SERVER['REQUEST_URI'], '/claim_license');
  if (!$claimingLicense && Input::has('license_key') && Input::has('product_id'))
  {
    $licenseKey = Input::get('license_key');
    $productId = Input::get('product_id');

    $data = trim(file_get_contents((Utils::isNinjaDev() ? 'http://ninja.dev' : NINJA_APP_URL) . "/claim_license?license_key={$licenseKey}&product_id={$productId}"));

    if ($productId == PRODUCT_INVOICE_DESIGNS)
    {
      if ($data = json_decode($data))
      {
        foreach ($data as $item)
        {
          $design = new InvoiceDesign();
          $design->id = $item->id;
          $design->name = $item->name;
          $design->javascript = $item->javascript;
          $design->save();
        }

        if (!Utils::isNinjaProd()) {
          Cache::forget('invoice_designs_cache_' . Auth::user()->maxInvoiceDesignId());
        }

        Session::flash('message', trans('texts.bought_designs'));
      }
    }
    else if ($productId == PRODUCT_WHITE_LABEL)
    {
      if ($data == 'valid')
      {
        $account = Auth::user()->account;
        $account->pro_plan_paid = NINJA_DATE;
        $account->save();

        Session::flash('message', trans('texts.bought_white_label'));
      }
    }
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

Route::filter('api.access', function()
{
    $headers = Utils::getApiHeaders();

    // check for a valid token
    $token = AccountToken::where('token', '=', Request::header('X-Ninja-Token'))->first(['id', 'user_id']);

    if ($token) {
        Auth::loginUsingId($token->user_id);
        Session::set('token_id', $token->id);
    } else {
        sleep(3);
        return Response::make('Invalid token', 403, $headers);
    }
        
    if (!Utils::isPro()) {
        return Response::make('API requires pro plan', 403, $headers);
    } else {
        $accountId = Auth::user()->account->id;

        // http://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
        $hour = 60 * 60;
        $hour_limit = 100; # users are limited to 100 requests/hour
        $hour_throttle = Cache::get("hour_throttle:{$accountId}", null);
        $last_api_request = Cache::get("last_api_request:{$accountId}", 0);
        $last_api_diff = time() - $last_api_request;
        
        if (is_null($hour_throttle)) {
            $new_hour_throttle = 0;
        } else {
            $new_hour_throttle = $hour_throttle - $last_api_diff;
            $new_hour_throttle = $new_hour_throttle < 0 ? 0 : $new_hour_throttle;
            $new_hour_throttle += $hour / $hour_limit;
            $hour_hits_remaining = floor(( $hour - $new_hour_throttle ) * $hour_limit / $hour);
            $hour_hits_remaining = $hour_hits_remaining >= 0 ? $hour_hits_remaining : 0;
        }

        if ($new_hour_throttle > $hour) {
            $wait = ceil($new_hour_throttle - $hour);
            sleep(1);
            return Response::make("Please wait {$wait} second(s)", 403, $headers);
        }

        Cache::put("hour_throttle:{$accountId}", $new_hour_throttle, 10);
        Cache::put("last_api_request:{$accountId}", time(), 10);
    }

    return null;
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
  if ($_SERVER['REQUEST_URI'] != '/signup/register')
  {
  	$token = Request::ajax() ? Request::header('X-CSRF-Token') : Input::get('_token');
  	
   	if (Session::token() != $token) 
   	{      
      Session::flash('warning', trans('texts.session_expired'));   

   		return Redirect::to('/');
  		//throw new Illuminate\Session\TokenMismatchException;
   	}
  }
});
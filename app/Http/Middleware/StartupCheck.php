<?php namespace App\Http\Middleware;

use Closure;
use Utils;
use App;

class StartupCheck {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
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
			return $next($request);
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

		return $next($request);
	}

}

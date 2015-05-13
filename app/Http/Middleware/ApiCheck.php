<?php namespace App\Http\Middleware;

use Closure;
use Utils;
use Request;
use Session;
use Response;
use Auth;
use Cache;

use App\Models\AccountToken;

class ApiCheck {

    /**
    * Handle an incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @return mixed
    */
    public function handle($request, Closure $next)
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

        if (!Utils::isNinja()) {
            return $next($request);
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


        return $next($request);
    }

}
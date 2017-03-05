<?php

namespace App\Http\Middleware;

use App\Models\AccountToken;
use Auth;
use Cache;
use Closure;
use Request;
use Response;
use Session;
use Utils;

/**
 * Class ApiCheck.
 */
class ApiCheck
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $loggingIn = $request->is('api/v1/login')
            || $request->is('api/v1/register')
            || $request->is('api/v1/oauth_login');

        $headers = Utils::getApiHeaders();
        $hasApiSecret = false;

        if ($secret = env(API_SECRET)) {
            $requestSecret = Request::header('X-Ninja-Secret') ?: ($request->api_secret ?: '');
            $hasApiSecret = hash_equals($requestSecret, $secret);
        }

        if ($loggingIn) {
            // check API secret
            if (! $hasApiSecret) {
                sleep(ERROR_DELAY);
                $error['error'] = ['message' => 'Invalid value for API_SECRET'];

                return Response::json($error, 403, $headers);
            }
        } else {
            // check for a valid token
            $token = AccountToken::where('token', '=', Request::header('X-Ninja-Token'))->first(['id', 'user_id']);

            // check if user is archived
            if ($token && $token->user) {
                Auth::onceUsingId($token->user_id);
                Session::set('token_id', $token->id);
            } elseif ($hasApiSecret && $request->is('api/v1/ping')) {
                // do nothing: allow ping with api_secret or account token
            } else {
                sleep(ERROR_DELAY);
                $error['error'] = ['message' => 'Invalid token'];

                return Response::json($error, 403, $headers);
            }
        }

        if (! Utils::isNinja() && ! $loggingIn) {
            return $next($request);
        }

        if (! Utils::hasFeature(FEATURE_API) && ! $hasApiSecret) {
            $error['error'] = ['message' => 'API requires pro plan'];

            return Response::json($error, 403, $headers);
        } else {
            $key = Auth::check() ? Auth::user()->account->id : $request->getClientIp();

            // http://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
            $hour = 60 * 60;
            $hour_limit = 100; // users are limited to 100 requests/hour
            $hour_throttle = Cache::get("hour_throttle:{$key}", null);
            $last_api_request = Cache::get("last_api_request:{$key}", 0);
            $last_api_diff = time() - $last_api_request;

            if (is_null($hour_throttle)) {
                $new_hour_throttle = 0;
            } else {
                $new_hour_throttle = $hour_throttle - $last_api_diff;
                $new_hour_throttle = $new_hour_throttle < 0 ? 0 : $new_hour_throttle;
                $new_hour_throttle += $hour / $hour_limit;
                $hour_hits_remaining = floor(($hour - $new_hour_throttle) * $hour_limit / $hour);
                $hour_hits_remaining = $hour_hits_remaining >= 0 ? $hour_hits_remaining : 0;
            }

            if ($new_hour_throttle > $hour) {
                $wait = ceil($new_hour_throttle - $hour);
                sleep(1);

                return Response::json("Please wait {$wait} second(s)", 403, $headers);
            }

            Cache::put("hour_throttle:{$key}", $new_hour_throttle, 10);
            Cache::put("last_api_request:{$key}", time(), 10);
        }

        return $next($request);
    }
}

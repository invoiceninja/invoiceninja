<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

class PasswordProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $error = [
            'message' => 'Invalid Password',
            'errors' => new stdClass,
        ];

        if ($request->header('X-API-PASSWORD')) {
            if (! Hash::check($request->header('X-API-PASSWORD'), auth()->user()->password)) {
                return response()->json($error, 403);
            }
        } elseif (Cache::get(auth()->user()->email.'_logged_in')) {
            Cache::pull(auth()->user()->email.'_logged_in');
            Cache::add(auth()->user()->email.'_logged_in', Str::random(64), now()->addMinutes(30));

            return $next($request);
        } else {
            $error = [
                'message' => 'Access denied',
                'errors' => new stdClass,
            ];

            return response()->json($error, 412);
        }

        Cache::add(auth()->user()->email.'_logged_in', Str::random(64), now()->addMinutes(30));

        return $next($request);
    }
}

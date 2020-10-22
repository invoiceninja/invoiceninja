<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use Closure;
use DB;
use Illuminate\Http\Request;
use Log;

/**
 * Class QueryLogging.
 */
class QueryLogging
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        $timeStart = microtime(true);

        // Enable query logging for development
        if (config('ninja.app_env') != 'production') {
            DB::enableQueryLog();
        }

        $response = $next($request);

        if (config('ninja.app_env') != 'production') {

            // hide requests made by debugbar
            if (strstr($request->url(), '_debugbar') === false) {
                $queries = DB::getQueryLog();
                $count = count($queries);
                $timeEnd = microtime(true);
                $time = $timeEnd - $timeStart;

                Log::info($request->method().' - '.$request->url().": $count queries - ".$time);

                //  if($count > 50)
                //   Log::info($queries);
                
            }
        }

        return $response;
    }
}

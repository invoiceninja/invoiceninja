<?php namespace App\Http\Middleware;

use DB;
use Log;
use Utils;
use Closure;

class QueryLogging
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Enable query logging for development
        if (Utils::isNinjaDev()) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        if (Utils::isNinjaDev()) {
            // hide requests made by debugbar
            if (strstr($request->url(), '_debugbar') === false) {
                $queries = DB::getQueryLog();
                $count = count($queries);
                Log::info($request->method() . ' - ' . $request->url() . ": $count queries");
                //Log::info(json_encode($queries));
            }
        }

        return $response;
    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Closure;


/**
 * Class StartupCheck.
 */
class StartupCheck
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
       // $start = microtime(true);
       // Log::error('start up check');

        $cached_tables = config('ninja.cached_tables');

        if (Input::has('clear_cache')) 
            Session::flash('message', 'Cache cleared');
        
        foreach ($cached_tables as $name => $class) {
            if (Input::has('clear_cache') || ! Cache::has($name)) {
                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }

      //  $end = microtime(true) - $start;
      //  Log::error("middleware cost = {$end} ms");
        
        $response = $next($request);

        return $response;
    }
}

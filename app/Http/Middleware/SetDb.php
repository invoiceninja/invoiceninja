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

use App\Libraries\MultiDB;
use App\Models\CompanyToken;
use Closure;

class SetDb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $error = [
            'message' => 'Invalid Token',
            'errors' => new \stdClass,
        ];

        if ($request->header('X-API-TOKEN') && config('ninja.db.multi_db_enabled')) {
            if (! MultiDB::findAndSetDb($request->header('X-API-TOKEN'))) {
                return response()->json($error, 403);
            }
        } elseif (! config('ninja.db.multi_db_enabled')) {
            return $next($request);
        } else {
            return response()->json($error, 403);
        }

        return $next($request);
    }
}

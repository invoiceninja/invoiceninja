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

use App\Libraries\MultiDB;
use Closure;
use Illuminate\Http\Request;
use stdClass;

class SetDomainNameDb
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
                'message' => 'Invalid token',
                'errors' => new stdClass,
            ];
        /*
         * Use the host name to set the active DB
         **/
        if ($request->getSchemeAndHttpHost() && config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByDomain($request->getSchemeAndHttpHost())) {
            if (request()->json) {
                return response()->json($error, 403);
            } else {
                abort(404);
            }
        }

        return $next($request);
    }
}

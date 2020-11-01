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
use Illuminate\Http\Request;
use stdClass;

class SetEmailDb
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
            'message' => 'Email not set or not found',
            'errors' => new stdClass,
        ];

        if ($request->input('email') && config('ninja.db.multi_db_enabled')) {
            info("trying to find db");
            if (! MultiDB::userFindAndSetDb($request->input('email'))) {
                return response()->json($error, 400);
            }
        } 
        // else {
        //     return response()->json($error, 403);
        // }

        return $next($request);
    }
}

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
use Closure;
use Illuminate\Http\Request;
use stdClass;

class SetInviteDb
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
                'message' => 'Invalid URL',
                'errors' => new stdClass,
            ];
        /*
         * Use the host name to set the active DB
         **/
        $entity = null;

        if (! $request->route('entity')) {
            $entity = $request->segment(2);
        } else {
            $entity = $request->route('entity');
        }

        if ($request->getSchemeAndHttpHost() && config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByInvitation($entity, $request->route('invitation_key'))) {
            if (request()->json) {
                return response()->json($error, 403);
            } else {
                abort(404);
            }
        }

        return $next($request);
    }
}

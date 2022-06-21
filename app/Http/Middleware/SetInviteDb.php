<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use Closure;
use Hashids\Hashids;
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
            'message' => 'I could not find the database for this object.',
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

        if ($entity == 'pay') {
            $entity = 'invoice';
        }

        if (! in_array($entity, ['invoice', 'quote', 'credit', 'recurring_invoice', 'purchase_order'])) {
            abort(404, 'I could not find this resource.');
        }

        /* Try and determine the DB from the invitation key STRING*/
        if (config('ninja.db.multi_db_enabled')) {

            // nlog("/ Try and determine the DB from the invitation key /");

            $hashids = new Hashids(config('ninja.hash_salt'), 10);
            $segments = explode('-', $request->route('invitation_key'));
            $hashed_db = false;

            if (is_array($segments)) {
                $hashed_db = $hashids->decode($segments[0]);
            }

            if ($hashed_db && is_array($hashed_db) && ($hashed_db[0] == '01' || $hashed_db[0] == '02')) {
                MultiDB::setDB(MultiDB::DB_PREFIX.str_pad($hashed_db[0], 2, '0', STR_PAD_LEFT));

                return $next($request);
            }
        }

        /* Attempt to set DB from invitatation key*/
        if ($request->getSchemeAndHttpHost() && config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByInvitation($entity, $request->route('invitation_key'))) {
            if (request()->json) {
                return response()->json($error, 403);
            } else {
                abort(404, 'I could not find this resource.');
            }
        }

        return $next($request);
    }
}

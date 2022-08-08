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

/**
 * Class UrlSetDb.
 */
class UrlSetDb
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
        if (config('ninja.db.multi_db_enabled')) {
            $hashids = new Hashids(config('ninja.hash_salt'), 10);

            //parse URL hash and set DB
            $segments = explode('-', $request->route('confirmation_code'));

            if (! is_array($segments)) {
                return response()->json(['message' => 'Invalid confirmation code'], 403);
            }

            $hashed_db = $hashids->decode($segments[0]);

            if (! is_array($hashed_db) || empty($hashed_db)) {
                return response()->json(['message' => 'Invalid confirmation code'], 403);
            }

            MultiDB::setDB(MultiDB::DB_PREFIX.str_pad($hashed_db[0], 2, '0', STR_PAD_LEFT));
        }

        return $next($request);
    }
}

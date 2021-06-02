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

        if(!config('ninja.db.multi_db_enabled'))
            return $next($request);


        if (strpos($request->getHost(), 'invoicing.co') !== false) 
        {
            $subdomain = explode('.', $request->getHost())[0];
            
            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            if($company = MultiDB::findAndSetDbByDomain($query)){
                $request->attributes->add(['account_id' => $company->account_id]);
            }
            else 
            {
                if ($request->json) {
                        return response()->json($error, 403);
                } else {
                    MultiDB::setDb('db-ninja-01');
                    nlog("I could not set the DB - defaulting to DB1");
                    //abort(400, 'Domain not found');
                }
            }

        }
        else {

           $query = [
                'portal_domain' => $request->getSchemeAndHttpHost(),
                'portal_mode' => 'domain',
            ];

            if($company = MultiDB::findAndSetDbByDomain($query)){
                $request->attributes->add(['account_id' => $company->account_id]);
            }
            else
            {
                if ($request->json) {
                        return response()->json($error, 403);
                } else {
                    MultiDB::setDb('db-ninja-01');
                    nlog("I could not set the DB - defaulting to DB1");
                    //abort(400, 'Domain not found');
                }
            }

        }

        // config(['app.url' => $request->getSchemeAndHttpHost()]);

        return $next($request);
    }
}

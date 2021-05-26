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
use App\Models\Account;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

class ContactAccount
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

        if(!Ninja::isHosted()) {

            $account_id = Account::first()->id;
            $request->attributes->add(['account_id' => $account_id]);
            
        }

        return $next($request);
    }
}

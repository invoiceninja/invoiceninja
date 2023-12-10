<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

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
        if (! Ninja::isHosted()) {
            /** @var \App\Models\Account $account */
            $account = Account::first();

            session()->put('account_key', $account->key);
        }

        return $next($request);
    }
}

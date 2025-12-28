<?php

/**
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

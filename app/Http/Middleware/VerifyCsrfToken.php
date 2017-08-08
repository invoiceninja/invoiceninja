<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

/**
 * Class VerifyCsrfToken.
 */
class VerifyCsrfToken extends BaseVerifier
{
    /**
     * @var array
     */
    private $openRoutes = [
        'complete/*',
        'signup/register',
        'api/v1/*',
        'hook/email_opened',
        'hook/email_bounced',
        'reseller_stats',
        'payment_hook/*',
        'buy_now*',
        'hook/bot/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach ($this->openRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}

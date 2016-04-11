<?php namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier {

    private $openRoutes = [
        'complete',
        'signup/register',
        'api/v1/*',
        'api/v1/login',
		'api/v1/clients/*',
        'api/v1/clients',
		'api/v1/invoices/*',
        'api/v1/invoices',
        'api/v1/quotes',
        'api/v1/payments',
        'api/v1/tasks',
        'api/v1/email_invoice',
        'api/v1/hooks',
        'hook/email_opened',
        'hook/email_bounced',
        'reseller_stats',
    ];

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
        foreach($this->openRoutes as $route) {

          if ($request->is($route)) {
            return $next($request);
          }
        }

		return parent::handle($request, $next);
	}

}

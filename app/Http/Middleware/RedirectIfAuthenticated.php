<?php namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Session;
use Closure;
use App\Models\Client;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;

/**
 * Class RedirectIfAuthenticated
 */
class RedirectIfAuthenticated
{

    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard $auth
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->auth->check() && Client::scope()->count() > 0) {
            Session::reflash();

            return new RedirectResponse(url('/dashboard'));
        }

        return $next($request);
    }

}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Class DuplicateSubmissionCheck.
 */
class DuplicateSubmissionCheck
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/*') || $request->is('documents')) {
            return $next($request);
        }

        $path = $request->path();

        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            $lastPage = session(SESSION_LAST_REQUEST_PAGE);
            $lastTime = session(SESSION_LAST_REQUEST_TIME);

            if ($lastPage == $path && (microtime(true) - $lastTime <= 1)) {
                return redirect('/')->with('warning', trans('texts.duplicate_post'));
            }

            session([SESSION_LAST_REQUEST_PAGE => $request->path()]);
            session([SESSION_LAST_REQUEST_TIME => microtime(true)]);
        }

        return $next($request);
    }
}

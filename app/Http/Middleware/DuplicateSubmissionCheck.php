<?php namespace app\Http\Middleware;

use Closure;

class DuplicateSubmissionCheck
{
    // Prevent users from submitting forms twice
    public function handle($request, Closure $next)
    {
        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            $lastPage = session(SESSION_LAST_REQUEST_PAGE);
            $lastTime = session(SESSION_LAST_REQUEST_TIME);
            
            if ($lastPage == $request->path() && (microtime(true) - $lastTime <= 1.5)) {
                return redirect($request->path());
            }

            session([SESSION_LAST_REQUEST_PAGE => $request->path()]);
            session([SESSION_LAST_REQUEST_TIME => microtime(true)]);
        }

        return $next($request);
    }
}
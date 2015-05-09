<?php namespace app\Http\Middleware;

use Closure;

class DuplicateSubmissionCheck
{
    // Prevent users from submitting forms twice
    public function handle($request, Closure $next)
    {
        $path = $request->path();
        
        if (strpos($path, 'charts_and_reports') !== false) {
            return $next($request);
        }

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
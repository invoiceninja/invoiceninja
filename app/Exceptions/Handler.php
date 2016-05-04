<?php namespace App\Exceptions;

use Redirect;
use Utils;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exception\HttpResponseException; 
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Validation\ValidationException;

class Handler extends ExceptionHandler {

	/**
	 * A list of the exception types that should not be reported.
	 *
	 * @var array
	 */
	protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
	];

	/**
	 * Report or log an exception.
	 *
	 * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
	 *
	 * @param  \Exception  $e
	 * @return void
	 */
	public function report(Exception $e)
	{
        // don't show these errors in the logs
        if ($e instanceof HttpResponseException) {
            return false;
        }
        
        if (Utils::isNinja() && ! Utils::isTravis()) {
            Utils::logError(Utils::getErrorString($e));
            return false;
        } else {
            return parent::report($e);
        }
    }

	/**
	 * Render an exception into an HTTP response.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Exception  $e
	 * @return \Illuminate\Http\Response
	 */
	public function render($request, Exception $e)
	{
        if ($e instanceof ModelNotFoundException) {
            return Redirect::to('/');
        } elseif ($e instanceof \Illuminate\Session\TokenMismatchException) {
            // prevent loop since the page auto-submits
            if ($request->path() != 'get_started') {
                // https://gist.github.com/jrmadsen67/bd0f9ad0ef1ed6bb594e
                return redirect()
                        ->back()
                        ->withInput($request->except('password', '_token'))
                        ->with([
                            'warning' => trans('texts.token_expired')
                        ]);
            }
        }

        // In production, except for maintenance mode, we'll show a custom error screen
        if (Utils::isNinjaProd()
            && !Utils::isDownForMaintenance()
            && !($e instanceof HttpResponseException)) {
            $data = [
                'error' => get_class($e),
                'hideHeader' => true,
            ];
            
            return response()->view('error', $data);
        } else {
            return parent::render($request, $e);
        }
	}
}

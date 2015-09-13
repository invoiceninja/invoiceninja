<?php namespace App\Exceptions;

use Redirect;
use Utils;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException; 

class Handler extends ExceptionHandler {

	/**
	 * A list of the exception types that should not be reported.
	 *
	 * @var array
	 */
	protected $dontReport = [
		'Symfony\Component\HttpKernel\Exception\HttpException'
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
        Utils::logError(Utils::getErrorString($e));
        return false;
        
		//return parent::report($e);
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
        }

        if (Utils::isNinjaProd()) {
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

<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\RelationNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    
    public function render($request, Exception $exception)
    {
    
        if ($exception instanceof ModelNotFoundException && $request->expectsJson())
        {
            return response()->json(['message'=>'Record not found'],400);
        }
        else if($exception instanceof ThrottleRequestsException && $request->expectsJson())
        {
            return response()->json(['message'=>'Too many requests'],429);
        }
        else if($exception instanceof FatalThrowableError && $request->expectsJson())
        {
            return response()->json(['message'=>'Fatal error'], 500);
        }
        else if($exception instanceof AuthorizationException)
        {
            return response()->json(['message'=>'You are not authorized to view or perform this action'],401);
        }
        else if ($exception instanceof \Illuminate\Session\TokenMismatchException)
        {
            return redirect()
                    ->back()
                    ->withInput($request->except('password', 'password_confirmation', '_token'))
                    ->with([
                        'message' => ctrans('texts.token_expired'),
                        'message-type' => 'danger']);
        }   
        else if ($exception instanceof NotFoundHttpException && $request->expectsJson()) {
            return response()->json(['message'=>'Route does not exist'],404);
        }
        else if($exception instanceof MethodNotAllowedHttpException && $request->expectsJson()){
            return response()->json(['message'=>'Method not support for this route'],404);
        }
        else if ($exception instanceof ValidationException && $request->expectsJson()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $exception->validator->getMessageBag()], 422);
        }
        else if ($exception instanceof RelationNotFoundException && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        return parent::render($request, $exception);

    }



    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $guard = Arr::get($exception->guards(), 0);

        switch ($guard) {
           case 'contact':
                $login = 'client.login';
                break;
            case 'user':
                $login = 'login';
                break;
            default:
                $login = 'default';
                break;
        }
        
        return redirect()->guest(route($login));
    }
}

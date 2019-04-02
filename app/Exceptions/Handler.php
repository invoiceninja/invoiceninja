<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

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
    
        if ($exception instanceof ModelNotFoundException)
        {
            return response()->json(['error'=>'Record not found'],400);
        }

        return parent::render($request, $exception);

    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $guard = array_get($exception->guards(), 0);

        switch ($guard) {
           case 'contact':
                $login = 'contact.login';
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

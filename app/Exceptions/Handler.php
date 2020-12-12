<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Exceptions;

use App\Models\Account;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDOException;
use Sentry\State\Scope;
use Swift_TransportException;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        PDOException::class,
        //Swift_TransportException::class,
        MaxAttemptsExceededException::class,
        CommandNotFoundException::class,
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
     * @param Throwable $exception
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $exception)
    {
        if (! Schema::hasTable('accounts')) {
            info('account table not found');
            return;
        }

        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->configureScope(function (Scope $scope): void {
                if (auth()->guard('contact') && auth()->guard('contact')->user() && auth()->guard('contact')->user()->company->account->report_errors) {
                    $scope->setUser([
                        'id'    => auth()->guard('contact')->user()->company->account->key,
                        'email' => 'anonymous@example.com',
                        'name'  => 'Anonymous User',
                    ]);
                } elseif (auth()->guard('user') && auth()->guard('user')->user() && auth()->user()->company() && auth()->user()->company()->account->report_errors) {
                    $scope->setUser([
                        'id'    => auth()->user()->account->key,
                        'email' => 'anonymous@example.com',
                        'name'  => 'Anonymous User',
                    ]);
                }
            });

//            app('sentry')->setRelease(config('ninja.app_version'));

             if($this->validException($exception))
                app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    private function validException($exception) 
    {

        if(strpos($exception->getMessage(), 'file_put_contents') === TRUE)
            return FALSE;

        if(strpos($exception->getMessage(), 'Permission denied') === TRUE)
            return FALSE;
        
        if(strpos($exception->getMessage(), 'flock()') === TRUE)
            return FALSE;
        

        return TRUE;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ModelNotFoundException && $request->expectsJson()) {
            return response()->json(['message'=>$exception->getMessage()], 400);
        } elseif ($exception instanceof ThrottleRequestsException && $request->expectsJson()) {
            return response()->json(['message'=>'Too many requests'], 429);
        } elseif ($exception instanceof FatalThrowableError && $request->expectsJson()) {
            return response()->json(['message'=>'Fatal error'], 500);
        } elseif ($exception instanceof AuthorizationException) {
            return response()->json(['message'=>'You are not authorized to view or perform this action'], 401);
        } elseif ($exception instanceof TokenMismatchException) {
            return redirect()
                    ->back()
                    ->withInput($request->except('password', 'password_confirmation', '_token'))
                    ->with([
                        'message' => ctrans('texts.token_expired'),
                        'message-type' => 'danger', ]);
        } elseif ($exception instanceof NotFoundHttpException && $request->expectsJson()) {
            return response()->json(['message'=>'Route does not exist'], 404);
        } elseif ($exception instanceof MethodNotAllowedHttpException && $request->expectsJson()) {
            return response()->json(['message'=>'Method not support for this route'], 404);
        } elseif ($exception instanceof ValidationException && $request->expectsJson()) {
            info(print_r($exception->validator->getMessageBag(), 1));

            return response()->json(['message' => 'The given data was invalid.', 'errors' => $exception->validator->getMessageBag()], 422);
        } elseif ($exception instanceof RelationNotFoundException && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } elseif ($exception instanceof GenericPaymentDriverFailure && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } elseif ($exception instanceof GenericPaymentDriverFailure) {
            $data['message'] = $exception->getMessage();
            //dd($data);
           // return view('errors.layout', $data);
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

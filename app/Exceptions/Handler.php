<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Exceptions;

use Throwable;
use PDOException;
use App\Utils\Ninja;
use Sentry\State\Scope;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Sentry\Laravel\Integration;
use Illuminate\Support\Facades\Schema;
use Aws\Exception\CredentialsException;
use Illuminate\Database\QueryException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Auth\AuthenticationException;
use League\Flysystem\UnableToCreateDirectory;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Queue\MaxAttemptsExceededException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\Process\Exception\RuntimeException;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        // PDOException::class,
        MaxAttemptsExceededException::class,
        CommandNotFoundException::class,
        ValidationException::class,
        // ModelNotFoundException::class,
        NotFoundHttpException::class,
        RelationNotFoundException::class,
        NoNodeAvailableException::class,
    ];

    protected $selfHostDontReport = [
        FilePermissionsFailure::class,
        MaxAttemptsExceededException::class,
        CommandNotFoundException::class,
        ValidationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        UnableToCreateDirectory::class,
        RuntimeException::class,
        InvalidArgumentException::class,
        CredentialsException::class,
        RelationNotFoundException::class,
        QueryException::class,
    ];

    protected $hostedDontReport = [
        MaxAttemptsExceededException::class,
        CommandNotFoundException::class,
        ValidationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        RelationNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<1, string>
     */
    protected $dontFlash = [
        'current_password',
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
        if (Ninja::isHosted()) {

            Integration::configureScope(function (Scope $scope): void {
                $name = 'hosted@invoiceninja.com';

                if (auth()->guard('contact') && auth()->guard('contact')->user()) { // @phpstan-ignore-line
                    $name = 'Contact = '.auth()->guard('contact')->user()->email;
                    $key = auth()->guard('contact')->user()->company->account->key;
                } elseif (auth()->guard('user') && auth()->guard('user')->user()) { // @phpstan-ignore-line

                    $name = 'Admin = '.auth()->guard('user')->user()->email;
                    $key = auth()->user()->account->key;
                } else {
                    $key = 'Anonymous';
                }

                $scope->setUser([
                    'id'    => $key,
                    'email' => 'hosted@invoiceninja.com',
                    'name'  => $name,
                ]);
            });

            if ($this->validException($exception) && $this->sentryShouldReport($exception)) {
                Integration::captureUnhandledException($exception);
            }
        } elseif (app()->bound('sentry')) {
            Integration::configureScope(function (Scope $scope): void {
                if (auth()->guard('contact') && auth()->guard('contact')->user() && auth()->guard('contact')->user()->company->account->report_errors) {// @phpstan-ignore-line

                    $scope->setUser([
                        'id'    => auth()->guard('contact')->user()->company->account->key,
                        'email' => 'anonymous@example.com',
                        'name'  => 'Anonymous User',
                    ]);
                } elseif (auth()->guard('user') && auth()->guard('user')->user() && auth()->user()->companyIsSet() && auth()->user()->company()->account->report_errors) {// @phpstan-ignore-line
                    $scope->setUser([
                        'id'    => auth()->user()->account->key,
                        'email' => 'anonymous@example.com',
                        'name'  => 'Anonymous User',
                    ]);
                }
            });

            if ($this->validException($exception) && $this->sentryShouldReport($exception)) {
                Integration::captureUnhandledException($exception);
            }
        }

        parent::report($exception);

        if (Ninja::isSelfHost() && $exception instanceof MissingAppKeyException) {
            info('To setup the app run: cp .env.example .env');
        }
    }

    private function validException($exception)
    {
        if (strpos($exception->getMessage(), 'file_put_contents') !== false) {
            return false;
        }

        if (strpos($exception->getMessage(), 'Permission denied') !== false) {
            return false;
        }

        if (strpos($exception->getMessage(), 'flock') !== false) {
            return false;
        }

        if (strpos($exception->getMessage(), 'expects parameter 1 to be resource') !== false) {
            return false;
        }

        if (strpos($exception->getMessage(), 'fwrite()') !== false) {
            return false;
        }

        if (strpos($exception->getMessage(), 'LockableFile') !== false) {
            return false;
        }

        return true;
    }


    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function sentryShouldReport(Throwable $e)
    {
        if (Ninja::isHosted()) {
            $dontReport = array_merge($this->hostedDontReport, $this->internalDontReport);
        } else {
            $dontReport = array_merge($this->selfHostDontReport, $this->internalDontReport);
        }

        return is_null(Arr::first($dontReport, fn ($type) => $e instanceof $type));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $exception
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ModelNotFoundException && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } elseif ($exception instanceof InternalPDFFailure && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 500);
        } elseif ($exception instanceof PhantomPDFFailure && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 500);
        } elseif ($exception instanceof FilePermissionsFailure) {
            return response()->json(['message' => $exception->getMessage()], 500);
        } elseif ($exception instanceof ThrottleRequestsException && $request->expectsJson()) {
            return response()->json(['message' => 'Too many requests'], 429);
            // } elseif ($exception instanceof FatalThrowableError && $request->expectsJson()) {
            //     return response()->json(['message'=>'Fatal error'], 500); //@deprecated
        } elseif ($exception instanceof AuthorizationException && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } elseif ($exception instanceof TokenMismatchException) {
            return redirect()
                    ->back()
                    ->withInput($request->except('password', 'password_confirmation', '_token'))
                    ->with([
                        'message' => ctrans('texts.token_expired'),
                        'message-type' => 'danger', ]);
        } elseif ($exception instanceof NotFoundHttpException && $request->expectsJson()) {
            return response()->json(['message' => 'Route does not exist'], 404);
        } elseif ($exception instanceof MethodNotAllowedHttpException && $request->expectsJson()) {
            return response()->json(['message' => 'Method not supported for this route'], 404);
        } elseif ($exception instanceof ValidationException && $request->expectsJson()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $exception->validator->getMessageBag()], 422);
        } elseif ($exception instanceof RelationNotFoundException && $request->expectsJson()) {
            return response()->json(['message' => "Relation `{$exception->relation}` is not a valid include."], 400);
        } elseif ($exception instanceof GenericPaymentDriverFailure && $request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } elseif ($exception instanceof GenericPaymentDriverFailure) {
            return response()->json(['message' => $exception->getMessage()], 400);
        } elseif ($exception instanceof StripeConnectFailure) {
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
            case 'vendor':
                $login = 'vendor.catchall';
                break;
            case 'ronin':
                $login = 'ronin.login';
                break;
            default:
                $login = 'default';
                break;
        }

        return redirect()->guest(route($login));
    }
}

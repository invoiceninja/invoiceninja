<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Foundation\Validation\ValidationException;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Support\Facades\Response;
use Illuminate\Session\TokenMismatchException;
use Redirect;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Utils;
use Request;

/**
 * Class Handler.
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        TokenMismatchException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        \Illuminate\Validation\ValidationException::class,
        //AuthorizationException::class,
        //HttpException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $e
     *
     * @return bool|void
     */
    public function report(Exception $e)
    {
        if (! $this->shouldReport($e)) {
            return false;
        }

        // if these classes don't exist the install is broken, maybe due to permissions
        if (! class_exists('Utils') || ! class_exists('Crawler')) {
            return parent::report($e);
        }

        if (\Crawler::isCrawler()) {
            return false;
        }

        // don't show these errors in the logs
        if ($e instanceof NotFoundHttpException) {
            // The logo can take a few seconds to get synced between servers
            // TODO: remove once we're using cloud storage for logos
            if (Utils::isNinja() && strpos(request()->url(), '/logo/') !== false) {
                return false;
            }
            // Log 404s to a separate file
            $errorStr = date('Y-m-d h:i:s') . ' ' . $e->getMessage() . ' URL:' . request()->url() . "\n" . json_encode(Utils::prepareErrorData('PHP')) . "\n\n";
            if (config('app.log') == 'single') {
                @file_put_contents(storage_path('logs/not-found.log'), $errorStr, FILE_APPEND);
            } else {
                Utils::logError('[not found] ' . $errorStr);
            }
            return false;
        } elseif ($e instanceof HttpResponseException) {
            return false;
        }

        if (! Utils::isTravis()) {
            Utils::logError(Utils::getErrorString($e));
            $stacktrace = date('Y-m-d h:i:s') . ' ' . $e->getMessage() . ': ' . $e->getTraceAsString() . "\n\n";
            if (config('app.log') == 'single') {
                @file_put_contents(storage_path('logs/stacktrace.log'), $stacktrace, FILE_APPEND);
            } else {
                Utils::logError('[stacktrace] ' . $stacktrace);
            }
            return false;
        } else {
            return parent::report($e);
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $e
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        $value = Request::header('X-Ninja-Token');

        if ($e instanceof ModelNotFoundException) {

            if( isset($value) && strlen($value) > 1 ){
                $headers = \App\Libraries\Utils::getApiHeaders();
                $response = json_encode(['message' => 'record does not exist'], JSON_PRETTY_PRINT);

                return Response::make($response, 404, $headers);
            }
            else
                return Redirect::to('/');
        }

        if (! class_exists('Utils')) {
            return parent::render($request, $e);
        }

        if ($e instanceof TokenMismatchException) {
            if (! in_array($request->path(), ['get_started', 'save_sidebar_state'])) {
                // https://gist.github.com/jrmadsen67/bd0f9ad0ef1ed6bb594e
                return redirect()
                        ->back()
                        ->withInput($request->except('password', '_token'))
                        ->with([
                            'warning' => trans('texts.token_expired'),
                        ]);
            }
        }

        if ($this->isHttpException($e)) {
            switch ($e->getStatusCode()) {
                // not found
                case 404:
                    if ($request->header('X-Ninja-Token') != '') {
                        //API request which has hit a route which does not exist

                        $error['error'] = ['message' => 'Route does not exist'];
                        $error = json_encode($error, JSON_PRETTY_PRINT);
                        $headers = Utils::getApiHeaders();

                        return response()->make($error, 404, $headers);
                    }
                    break;

                // internal error
                case '500':
                    if ($request->header('X-Ninja-Token') != '') {
                        //API request which produces 500 error

                        $error['error'] = ['message' => 'Internal Server Error'];
                        $error = json_encode($error, JSON_PRETTY_PRINT);
                        $headers = Utils::getApiHeaders();

                        return response()->make($error, 500, $headers);
                    }
                    break;

            }
        }

        // In production, except for maintenance mode, we'll show a custom error screen
        if (Utils::isNinjaProd()
            && ! Utils::isDownForMaintenance()
            && ! ($e instanceof HttpResponseException)
            && ! ($e instanceof \Illuminate\Validation\ValidationException)
            && ! ($e instanceof ValidationException)) {
            $data = [
                'error' => get_class($e),
                'hideHeader' => true,
            ];

            return response()->view('error', $data, 500);
        } else {
            return parent::render($request, $e);
        }
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $guard = array_get($exception->guards(), 0);

        switch ($guard) {
            case 'client':
                $url = '/client/login';
                break;
            default:
                $url = '/login';
                break;
        }

        return redirect()->guest($url);
    }
}

<?php

namespace App\Exceptions;

use Crawler;
use Exception;
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
        //ModelNotFoundException::class,
        //AuthorizationException::class,
        //HttpException::class,
        //ValidationException::class,
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

        // don't show these errors in the logs
        if ($e instanceof NotFoundHttpException) {
            if (Crawler::isCrawler()) {
                return false;
            }
            // The logo can take a few seconds to get synced between servers
            // TODO: remove once we're using cloud storage for logos
            if (Utils::isNinja() && strpos(request()->url(), '/logo/') !== false) {
                return false;
            }
            // Log 404s to a separate file
            $errorStr = date('Y-m-d h:i:s') . ' ' . request()->url() . "\n" . json_encode(Utils::prepareErrorData('PHP')) . "\n\n";
            @file_put_contents(storage_path('logs/not_found.log'), $errorStr, FILE_APPEND);
            return false;
        } elseif ($e instanceof HttpResponseException) {
            return false;
        }

        if (! Utils::isTravis()) {
            Utils::logError(Utils::getErrorString($e));
            $stacktrace = date('Y-m-d h:i:s') . ' ' . $e->getTraceAsString() . "\n\n";
            @file_put_contents(storage_path('logs/stacktrace.log'), $stacktrace, FILE_APPEND);
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
        if ($e instanceof ModelNotFoundException) {
            return Redirect::to('/');
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
}

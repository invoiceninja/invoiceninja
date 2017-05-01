<?php
namespace App\Http;


use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
        'Illuminate\Cookie\Middleware\EncryptCookies',
        'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse',
        'Illuminate\Session\Middleware\StartSession',
        'Illuminate\View\Middleware\ShareErrorsFromSession',
        'App\Http\Middleware\VerifyCsrfToken',
        'App\Http\Middleware\DuplicateSubmissionCheck',
        'App\Http\Middleware\QueryLogging',
        'App\Http\Middleware\StartupCheck',
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'lookup' => 'App\Http\Middleware\DatabaseLookup',
        'auth' => 'App\Http\Middleware\Authenticate',
        'auth.basic' => 'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth',
        'permissions.required' => 'App\Http\Middleware\PermissionsRequired',
        'guest' => 'App\Http\Middleware\RedirectIfAuthenticated',
        'api' => 'App\Http\Middleware\ApiCheck',
        'cors' => '\Barryvdh\Cors\HandleCors',
    ];
}

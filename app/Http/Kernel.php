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

namespace App\Http;

use App\Http\Middleware\ContactTokenAuth;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \App\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
        //\Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\Cors::class,

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\QueryLogging::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
            'query_logging',
            //\App\Http\Middleware\StartupCheck::class,
            \App\Http\Middleware\Cors::class,
        ],
        'contact' => [
            'throttle:60,1',
            'bindings',
            'query_logging',
        ],
        'client' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            //\App\Http\Middleware\StartupCheck::class,
            \App\Http\Middleware\QueryLogging::class,
        ],
        'shop' => [
            'throttle:60,1',
            'bindings',
            'query_logging',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'query_logging' => \App\Http\Middleware\QueryLogging::class,
        'token_auth' => \App\Http\Middleware\TokenAuth::class,
        'api_secret_check' => \App\Http\Middleware\ApiSecretCheck::class,
        'contact_token_auth' => \App\Http\Middleware\ContactTokenAuth::class,
        'contact_db' => \App\Http\Middleware\ContactSetDb::class,
        'domain_db' => \App\Http\Middleware\SetDomainNameDb::class,
        'email_db' => \App\Http\Middleware\SetEmailDb::class,
        'invite_db' => \App\Http\Middleware\SetInviteDb::class,
        'password_protected' => \App\Http\Middleware\PasswordProtection::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'portal_enabled' => \App\Http\Middleware\ClientPortalEnabled::class,
        'url_db' =>  \App\Http\Middleware\UrlSetDb::class,
        'web_db' => \App\Http\Middleware\SetWebDb::class,
        'api_db' => \App\Http\Middleware\SetDb::class,
        'company_key_db' => \App\Http\Middleware\SetDbByCompanyKey::class,
        'locale' => \App\Http\Middleware\Locale::class,
        'contact.register' => \App\Http\Middleware\ContactRegister::class,
        'shop_token_auth' => \App\Http\Middleware\Shop\ShopTokenAuth::class,
        'phantom_secret' => \App\Http\Middleware\PhantomSecret::class,
        'contact_key_login' => \App\Http\Middleware\ContactKeyLogin::class,
    ];
}

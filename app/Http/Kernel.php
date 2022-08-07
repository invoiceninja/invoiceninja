<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http;

use App\Http\Middleware\ApiSecretCheck;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckClientExistence;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\ClientPortalEnabled;
use App\Http\Middleware\ContactAccount;
use App\Http\Middleware\ContactKeyLogin;
use App\Http\Middleware\ContactRegister;
use App\Http\Middleware\ContactSetDb;
use App\Http\Middleware\ContactTokenAuth;
use App\Http\Middleware\Cors;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\Locale;
use App\Http\Middleware\PasswordProtection;
use App\Http\Middleware\PhantomSecret;
use App\Http\Middleware\QueryLogging;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SessionDomains;
use App\Http\Middleware\SetDb;
use App\Http\Middleware\SetDbByCompanyKey;
use App\Http\Middleware\SetDocumentDb;
use App\Http\Middleware\SetDomainNameDb;
use App\Http\Middleware\SetEmailDb;
use App\Http\Middleware\SetInviteDb;
use App\Http\Middleware\SetWebDb;
use App\Http\Middleware\Shop\ShopTokenAuth;
use App\Http\Middleware\TokenAuth;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\UrlSetDb;
use App\Http\Middleware\UserVerified;
use App\Http\Middleware\VendorContactKeyLogin;
use App\Http\Middleware\VendorLocale;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyHash;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        TrustProxies::class,
        // \Illuminate\Http\Middleware\HandleCors::class,
        Cors::class,

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            SessionDomains::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            QueryLogging::class,
        ],

        'api' => [
            // 'throttle:300,1',
            // 'cors',
            'bindings',
            'query_logging',
        ],
        'contact' => [
            'throttle:60,1',
            'bindings',
            'query_logging',
        ],
        'client' => [
            SessionDomains::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            QueryLogging::class,
        ],
        'shop' => [
            'throttle:120,1',
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
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'bindings' => SubstituteBindings::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'cors' => Cors::class,
        'guest' => RedirectIfAuthenticated::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'query_logging' => QueryLogging::class,
        'token_auth' => TokenAuth::class,
        'api_secret_check' => ApiSecretCheck::class,
        'contact_token_auth' => ContactTokenAuth::class,
        'contact_db' => ContactSetDb::class,
        'contact_account' => ContactAccount::class,
        'domain_db' => SetDomainNameDb::class,
        'email_db' => SetEmailDb::class,
        'invite_db' => SetInviteDb::class,
        'password_protected' => PasswordProtection::class,
        'signed' => ValidateSignature::class,
        'portal_enabled' => ClientPortalEnabled::class,
        'url_db' =>  UrlSetDb::class,
        'web_db' => SetWebDb::class,
        'api_db' => SetDb::class,
        'company_key_db' => SetDbByCompanyKey::class,
        'locale' => Locale::class,
        'vendor_locale' => VendorLocale::class,
        'contact_register' => ContactRegister::class,
        'verify_hash' => VerifyHash::class,
        'shop_token_auth' => ShopTokenAuth::class,
        'phantom_secret' => PhantomSecret::class,
        'contact_key_login' => ContactKeyLogin::class,
        'vendor_contact_key_login' => VendorContactKeyLogin::class,
        'check_client_existence' => CheckClientExistence::class,
        'user_verified' => UserVerified::class,
        'document_db' => SetDocumentDb::class,
        'session_domain' => SessionDomains::class,
    ];

    protected $middlewarePriority = [
        EncryptCookies::class,
        StartSession::class,
        SessionDomains::class,
        Cors::class,
        SetDomainNameDb::class,
        SetDb::class,
        SetWebDb::class,
        UrlSetDb::class,
        ContactSetDb::class,
        SetEmailDb::class,
        SetInviteDb::class,
        SetDbByCompanyKey::class,
        TokenAuth::class,
        ContactTokenAuth::class,
        ContactKeyLogin::class,
        Authenticate::class,
        ShopTokenAuth::class,
        ContactRegister::class,
        PhantomSecret::class,
        CheckClientExistence::class,
        ClientPortalEnabled::class,
        PasswordProtection::class,
        Locale::class,
        SubstituteBindings::class,
        ContactAccount::class,
    ];
}

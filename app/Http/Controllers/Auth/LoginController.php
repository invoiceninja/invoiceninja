<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Auth;

use Google_Client;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Account;
use App\Libraries\MultiDB;
use App\Utils\TruthSource;
use Microsoft\Graph\Model;
use App\Models\CompanyUser;
use App\Models\CompanyToken;
use Illuminate\Http\Request;
use App\Libraries\OAuth\OAuth;
use App\Events\User\UserLoggedIn;
use Illuminate\Http\JsonResponse;
use PragmaRX\Google2FA\Google2FA;
use App\Jobs\Account\CreateAccount;
use App\Events\User\UserLoginFailed;
use Illuminate\Support\Facades\Auth;
use App\Utils\Traits\User\LoginCache;
use Illuminate\Support\Facades\Cache;
use Turbo124\Beacon\Facades\LightLogs;
use App\DataMapper\Analytics\LoginMeta;
use App\Http\Controllers\BaseController;
use App\Jobs\Company\CreateCompanyToken;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Requests\Login\LoginRequest;
use App\Services\Auth\Passkeys\PasskeyService;
use App\Libraries\OAuth\Providers\Google;
use Illuminate\Database\Eloquent\Builder;
use App\DataMapper\Analytics\LoginFailure;
use App\DataMapper\Analytics\LoginSuccess;
use App\Utils\Traits\UserSessionAttributes;
use App\Transformers\CompanyUserTransformer;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends BaseController
{
    use AuthenticatesUsers;
    use UserSessionAttributes;
    use LoginCache;

    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = CompanyUserTransformer::class;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * validateLogin
     *
     * @param  LoginRequest $request
     * @return void
     */
    protected function validateLogin(LoginRequest $request)
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required_without:passkey_challenge_token|string',
        ]);
    }

    /**
     * Once the user is authenticated, we need to set
     * the default company into a session variable.
     *
     * @param Request $request
     * @param User $user
     * @return void
     * @deprecated .1 API ONLY we don't need to set any session variables
     */
    public function authenticated(Request $request, User $user): void
    {
        //$this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);
    }

    /**
     * Login via API.
     *
     * @param LoginRequest $request The request
     * @throws \Illuminate\Validation\ValidationException
     */
    public function apiLogin(LoginRequest $request)
    {
        $this->forced_includes = ['company_users'];

        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->loginErrorResponse('Too many login attempts, you are being throttled', 401);
        }

        $authenticated = $this->attemptPasskeyLogin($request) ?? $this->attemptLogin($request);

        if (!$authenticated) {
            return $this->handleFailedLogin($request);
        }

        $this->logLoginAttempt($request->email, 'success');

        /** @var \App\Models\User $user */
        $user = $this->guard()->user();

        if ($errorResponse = $this->verifyTwoFactor($user, $request)) {
            return $errorResponse;
        }

        return $this->finalizeLogin($user, $request);
    }

    /**
     * Attempt to authenticate the user via WebAuthn passkey credentials.
     *
     * Uses a tri-state return to signal the outcome:
     *  - null  – the request is not a passkey attempt (password present or no challenge token),
     *            so the caller should fall through to password-based authentication.
     *  - true  – passkey authentication succeeded and the user has been logged in via Auth::login().
     *  - false – passkey authentication was attempted but failed (bad credential, expired challenge, etc.).
     *
     * The method resolves the user through MultiDB::hasUser() to support multi-tenant lookups
     * and delegates cryptographic verification to PasskeyService::authenticate().
     *
     * @param  LoginRequest  $request
     * @return bool|null
     */
    private function attemptPasskeyLogin(LoginRequest $request): ?bool
    {
        if ($request->filled('password') || !$request->filled('passkey_challenge_token')) {
            return null;
        }

        $passkeyPayload = $request->input('passkey_authentication');

        if (!is_array($passkeyPayload)) {
            return null;
        }

        $user = MultiDB::hasUser(['email' => $request->input('email'), 'is_deleted' => 0, 'deleted_at' => null]);

        if (!$user) {
            return false;
        }

        try {
            $passkeyService = app(PasskeyService::class);
            $passkeyUser = $passkeyService->authenticate($user, (string) $request->input('passkey_challenge_token'), $passkeyPayload);
            Auth::login($passkeyUser, false);

            return true;
        } catch (\Throwable $e) {

            return false;
        }


    }

    /**
     * Verify the user's TOTP two-factor authentication code when enabled.
     *
     * This check is enforced for every login method (password and passkey alike).
     * If the user has a google_2fa_secret set, a valid one_time_password must be
     * provided in the request; otherwise the login is rejected.
     *
     * Returns null when 2FA is not enabled or the OTP is valid (login may proceed),
     * or a JsonResponse error when verification fails (login must be halted).
     *
     * @param  User          $user     The authenticated user to verify.
     * @param  LoginRequest  $request  The login request containing the optional one_time_password.
     * @return JsonResponse|null       Null to continue login, or an error response to halt.
     */
    private function verifyTwoFactor(User $user, LoginRequest $request): ?JsonResponse
    {
        if (!$user->google_2fa_secret) {
            return null;
        }

        if (!$request->filled('one_time_password')) {
            return $this->loginErrorResponse(ctrans('texts.invalid_one_time_password'), 400);
        }

        $google2fa = new Google2FA();

        if (strlen($request->input('one_time_password')) == 0 || !$google2fa->verifyKey(decrypt($user->google_2fa_secret), $request->input('one_time_password'))) {
            return $this->loginErrorResponse(ctrans('texts.invalid_one_time_password'), 422);
        }

        return null;
    }

    /**
     * Finalize a successful login by hydrating the user's company context and dispatching events.
     *
     * Performs the following steps:
     *  1. Recovers the default company if it is missing from the account.
     *  2. Hydrates all CompanyUser records for the authenticated user.
     *  3. Enforces hosted-plan restrictions (only owners may log in on non-Enterprise plans).
     *  4. Fires the UserLoggedIn event.
     *  5. Returns the time-constrained API response containing company user data.
     *
     * @param  User          $user     The authenticated user.
     * @param  LoginRequest  $request  The original login request.
     * @return \Illuminate\Http\Response|JsonResponse
     */
    private function finalizeLogin(User $user, LoginRequest $request)
    {
        if (!$user->account->default_company) {
            $account = $user->account;
            $account->default_company_id = $user->companies->first()->id;
            $account->save();
            $user = $user->fresh();
        }

        nlog("LOGIN:: {$request->email} - {$user->account_id}");

        /** @var \Illuminate\Database\Eloquent\Builder $cu */
        $cu = $this->hydrateCompanyUser($user);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 401);
        }

        event(new UserLoggedIn($user, $user->account->default_company, Ninja::eventVars($user->id)));

        return $this->timeConstrainedResponse($cu);
    }

    /**
     * Handle a failed login attempt by recording analytics, firing events, and throttling.
     *
     * Logs a LoginFailure metric, records a LoginMeta entry with the client IP,
     * dispatches the UserLoginFailed event for listeners (e.g. lockout notifications),
     * increments the throttle counter, and returns a 401 JSON error response.
     *
     * @param  LoginRequest  $request  The failed login request.
     * @return JsonResponse            A 401 error response with invalid credentials message.
     */
    private function handleFailedLogin(LoginRequest $request): JsonResponse
    {
        LightLogs::create(new LoginFailure())
            ->increment()
            ->batch();

        $ip = $this->resolveClientIp();

        LightLogs::create(new LoginMeta($request->email, $ip, 'failure'))->batch();

        event(new UserLoginFailed($request->email, $ip));

        $this->incrementLoginAttempts($request);

        return $this->loginErrorResponse(ctrans('texts.invalid_credentials'), 400);
    }

    /**
     * Record a successful login attempt in the analytics pipeline.
     *
     * Increments the LoginSuccess counter and writes a LoginMeta entry
     * containing the user's email, resolved client IP, and outcome label.
     *
     * @param  string  $email    The email address used for the login attempt.
     * @param  string  $outcome  A label describing the result (e.g. "success").
     * @return void
     */
    private function logLoginAttempt(string $email, string $outcome): void
    {
        LightLogs::create(new LoginSuccess())
            ->increment()
            ->batch();

        LightLogs::create(new LoginMeta($email, $this->resolveClientIp(), $outcome))
            ->batch();
    }

    /**
     * Resolve the real client IP address from the current request.
     *
     * Checks proxy/CDN headers in priority order: Cf-Connecting-Ip (Cloudflare),
     * X-Forwarded-For (reverse proxies), then falls back to the request's own IP.
     * Returns a single space if no IP can be determined.
     *
     * @return string  The resolved client IP address.
     */
    private function resolveClientIp(): string
    {
        if (request()->hasHeader('Cf-Connecting-Ip')) {
            return (string) request()->header('Cf-Connecting-Ip');
        }

        if (request()->hasHeader('X-Forwarded-For')) {
            return (string) request()->header('X-Forwarded-For');
        }

        return request()->ip() ?: ' ';
    }

    /**
     * Build a standardised JSON error response for login failures.
     *
     * Includes X-App-Version and X-Api-Version headers so the client can
     * detect version mismatches even on failed authentication attempts.
     *
     * @param  string        $message  The human-readable error message.
     * @param  int           $status   The HTTP status code (e.g. 401, 422).
     * @return JsonResponse            The formatted error response.
     */
    private function loginErrorResponse(string $message, int $status): JsonResponse
    {
        return response()
            ->json(['message' => $message], $status)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    public function refreshReact(Request $request)
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyToken()) {
            $company_token = $truth->getCompanyToken();
        } else {
            $company_token = CompanyToken::where('token', $request->header('X-API-TOKEN'))->first();
        }

        $cu = CompanyUser::query()
            ->where('user_id', $company_token->user_id);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        $cu->first()->account->companies->each(function ($company) use ($cu, $request) {
            if ($company->tokens()->where('is_system', true)->count() == 0) {
                (new CreateCompanyToken($company, $cu->first()->user, $request->server('HTTP_USER_AGENT')))->handle();
            }
        });

        if ($request->has('current_company') && $request->input('current_company') == 'true') {
            $cu->where('company_id', $company_token->company_id);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$cu->first()->user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        return $this->refreshReactResponse($cu);
    }

    /**
     * Refreshes the data feed with the current Company User.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function refresh(Request $request)
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyToken()) {
            $company_token = $truth->getCompanyToken();
        } else {
            $company_token = CompanyToken::where('token', $request->header('X-API-TOKEN'))->first();
        }

        $cu = CompanyUser::query()
            ->where('user_id', $company_token->user_id);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        $cu->first()->account->companies->each(function ($company) use ($cu, $request) {
            if ($company->tokens()->where('is_system', true)->count() == 0) {
                (new CreateCompanyToken($company, $cu->first()->user, $request->server('HTTP_USER_AGENT')))->handle();
            }
        });

        if ($request->has('current_company') && $request->input('current_company') == 'true') {
            $cu->where('company_id', $company_token->company_id);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$cu->first()->user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        return $this->refreshResponse($cu);
    }

    /**
     * A client side authentication has taken place.
     * We now digest the token and confirm authentication with
     * the authentication server, the correct user object
     * is returned to us here and we send back the correct
     * user object payload - or error.
     *
     * This can be extended to a create route also - need to pass a ?create query parameter and
     * then process the signup
     *
     * return   User $user
     */
    public function oauthApiLogin()
    {
        $message = 'Provider not supported';
        if (request()->input('provider') == 'google') {
            return $this->handleGoogleOauth();
        } elseif (request()->input('provider') == 'microsoft') {
            return $this->handleMicrosoftOauth();
        } elseif (request()->input('provider') == 'apple') {
            if (request()->has('id_token')) {
                $token = request()->input('id_token');
                return $this->handleSocialiteLogin('apple', $token);
            } else {
                $message = 'Token is missing for the apple login';
            }
        }

        return response()
            ->json(['message' => $message], 400)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function getSocialiteUser(string $provider, string $token)
    {
        return Socialite::driver($provider)->userFromToken($token);
    }

    private function handleSocialiteLogin($provider, $token)
    {
        $user = $this->getSocialiteUser($provider, $token);

        if ($user) {
            return $this->loginOrCreateFromSocialite($user, $provider);
        }

        return response()
            ->json(['message' => ctrans('texts.invalid_credentials')], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function loginOrCreateFromSocialite($user, $provider)
    {
        $query = [
            'oauth_user_id' => $user->id,
            'oauth_provider_id' => $provider,
        ];

        if ($existing_user = MultiDB::hasUser($query)) {
            if (!$existing_user->account) {
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
            }

            Auth::login($existing_user, false);

            /** @var \App\Models\CompanyUser $cu */
            $cu = $this->hydrateCompanyUser($existing_user);

            if ($cu->count() == 0) {
                return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
            }

            if (Ninja::isHosted() && !$cu->first()->is_owner && !$existing_user->account->isEnterprisePaidClient()) {
                return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
            }

            return $this->timeConstrainedResponse($cu);
        }
        //If this is a result user/email combo - lets add their OAuth details details
        if ($existing_login_user = MultiDB::hasUser(['email' => $user->email])) {
            if (!$existing_login_user->account) {
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
            }

            Auth::login($existing_login_user, false);
            /** @var \App\Models\User $user */

            $existing_login_user->update([
                'oauth_user_id' => $user->id,
                'oauth_provider_id' => $provider,
            ]);

            /** @var \App\Models\CompanyUser $cu */
            $cu = $this->hydrateCompanyUser($existing_login_user);

            if ($cu->count() == 0) {
                return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
            }

            if (Ninja::isHosted() && !$cu->first()->is_owner && !$existing_login_user->account->isEnterprisePaidClient()) {
                return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
            }

            return $this->timeConstrainedResponse($cu);
        }

        // nlog("socialite");
        // nlog($user);

        $name = OAuth::splitName($user->name);

        if ($provider == 'apple') {
            $name[0] = request()->has('first_name') ? request()->input('first_name') : $name[0];
            $name[1] = request()->has('last_name') ? request()->input('last_name') : $name[1];
        }

        if ($provider == 'apple' && !$user->email) {
            return response()->json(['message' => 'This signup method is not supported as no email was provided'], 403);
        }

        $new_account = [
            'first_name' => $name[0],
            'last_name' => $name[1],
            'password' => '',
            'email' => $user->email,
            'oauth_user_id' => $user->id,
            'oauth_provider_id' => $provider,
        ];

        MultiDB::setDefaultDatabase();

        $account = (new CreateAccount($new_account, request()->getClientIp()))->handle();

        $account_user = $account->default_company->owner();
        Auth::login($account_user, false);

        // $account_user->email_verified_at = now();
        // $account_user->save();

        /** @var \App\Models\CompanyUser $cu */
        $cu = $this->hydrateCompanyUser($account_user);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$account_user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        return $this->timeConstrainedResponse($cu);
    }

    private function hydrateCompanyUser(User $user): Builder
    {

        // /** @var \App\Models\User $user */
        // $user = auth()->user();

        /** @var Builder $cu */
        $cu = CompanyUser::query()->where('user_id', $user->id);

        if ($cu->count() == 0) {
            return $cu;
        }

        if (CompanyUser::query()->where('user_id', $user->id)->where('company_id', $user->account->default_company_id)->exists()) {
            $set_company = $user->account->default_company;
        } else {
            $set_company = CompanyUser::query()->where('user_id', $user->id)->first()->company;
        }

        $user->setCompany($set_company);

        $this->setLoginCache($user);

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($cu->first());
        $truth->setUser($user);
        $truth->setCompany($set_company);

        //21-03-2024
        $cu->each(function ($cu) {
            /** @var \App\Models\CompanyUser $cu */
            if (CompanyToken::query()->where('company_id', $cu->company_id)->where('user_id', $cu->user_id)->where('is_system', true)->doesntExist()) {
                (new CreateCompanyToken($cu->company, $cu->user, request()->server('HTTP_USER_AGENT')))->handle();
            }
        });

        $truth->setCompanyToken(CompanyToken::where('user_id', $user->id)->where('company_id', $set_company->id)->where('is_system', true)->first());

        return CompanyUser::query()->where('user_id', $user->id);
    }

    private function handleMicrosoftOauth()
    {
        if (request()->has('accessToken')) {
            $accessToken = request()->input('accessToken');
        } elseif (request()->has('access_token')) {
            $accessToken = request()->input('access_token');
        } else {
            return response()->json(['message' => 'Invalid response from oauth server, no access token in response.'], 400);
        }

        $expectedClientId = config('services.microsoft.client_id');

        if ($expectedClientId) {
            $parts = explode('.', $accessToken);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                $tokenClientId = $payload['appid'] ?? $payload['azp'] ?? null;

                if ($tokenClientId !== $expectedClientId) {
                    return response()->json(['message' => 'Invalid Microsoft token: audience mismatch.'], 403);
                }
            }
        }

        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($accessToken);

        $user = $graph->createRequest('GET', '/me')
            ->setReturnType(Model\User::class)
            ->execute();

        if ($user) {
            $account = request()->input('account');

            $email = $user->getUserPrincipalName() ?? false;

            $query = [
                'oauth_user_id' => $user->getId(),
                'oauth_provider_id' => 'microsoft',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {
                if (!$existing_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                return $this->existingOauthUser($existing_user);
            }

            if (MultiDB::hasUser(['email' => $email, 'oauth_provider_id' => null])) {
                return response()->json(['message' => 'User exists, but never authenticated with OAuth, please use your email and password to login.'], 400);
            }

            // Signup!
            if (request()->has('create') && request()->input('create') == 'true') {
                $new_account = [
                    'first_name' => $user->getGivenName() ?: '',
                    'last_name' => $user->getSurname() ?: '',
                    'password' => '',
                    'email' => $email,
                    'oauth_user_id' => $user->getId(),
                    'oauth_provider_id' => 'microsoft',
                ];

                return $this->createNewAccount($new_account);
            }

            return response()->json(['message' => 'User not found. If you believe this is an error, please send an email to contact@invoiceninja.com'], 400);
        }


        return response()->json(['message' => 'Unable to authenticate this user'], 400);
    }

    /**
     * send login response to oauthed users
     *
     * @param \App\Models\User $existing_user
     * @return Response| \Illuminate\Http\JsonResponse | JsonResponse
     */
    private function existingOauthUser($existing_user)
    {
        Auth::login($existing_user, false);

        /** @var \App\Models\CompanyUser $cu */
        $cu = $this->hydrateCompanyUser($existing_user);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$existing_user->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        event(new UserLoggedIn($existing_user, $existing_user->account->default_company, Ninja::eventVars($existing_user->id)));

        return $this->timeConstrainedResponse($cu);
    }

    private function existingLoginUser($user)
    {


        /** @var \App\Models\CompanyUser $cu */
        $cu = $this->hydrateCompanyUser($user);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !auth()->user()->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        event(new UserLoggedIn($user, $user->account->default_company, Ninja::eventVars($user->id)));

        return $this->timeConstrainedResponse($cu);
    }

    private function handleGoogleOauth()
    {
        $user = false;

        $google = new Google();

        if (request()->has('id_token')) {
            $user = $google->getTokenResponse(request()->input('id_token'));
        } elseif (request()->has('access_token')) {
            $user = $google->harvestUser(request()->input('access_token'));
        } else {
            return response()->json(['message' => 'Illegal request'], 403);
        }

        if (is_array($user)) {
            $query = [
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_provider_id' => 'google',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {
                if (!$existing_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                return $this->existingOauthUser($existing_user);
            }

            if (MultiDB::hasUser(['email' => $google->harvestEmail($user), 'oauth_provider_id' => null])) {
                return response()->json(['message' => 'Please use your email and password to login.'], 400);
            }


        }

        if ($user) {
            //check the user doesn't already exist in some form
            if ($existing_login_user = MultiDB::hasUser(['email' => $google->harvestEmail($user), 'oauth_provider_id' => 'google'])) {
                if (!$existing_login_user->account) {
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);
                }

                Auth::login($existing_login_user, false);

                $existing_login_user->update([
                    'oauth_user_id' => $google->harvestSubField($user),
                    'oauth_provider_id' => 'google',
                ]);


                return $this->existingLoginUser($existing_login_user);
            }

            if (request()->has('create') && request()->input('create') == 'true') {
                //user not found anywhere - lets sign them up.
                $name = OAuth::splitName($google->harvestName($user));

                $new_account = [
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => '',
                    'email' => $google->harvestEmail($user),
                    'oauth_user_id' => $google->harvestSubField($user),
                    'oauth_provider_id' => 'google',
                ];

                return $this->createNewAccount($new_account);
            }

            return response()->json(['message' => 'User not found. If you believe this is an error, please send an email to contact@invoiceninja.com'], 400);
        }

        return response()
            ->json(['message' => ctrans('texts.invalid_credentials')], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function createNewAccount($new_account)
    {
        MultiDB::setDefaultDatabase();

        $account = (new CreateAccount($new_account, request()->getClientIp()))->handle();
        if (!$account instanceof Account) {
            return $account;
        }

        $user = $account->default_company->owner();
        // $user->email_verified_at = now();
        // $user->save();

        Auth::login($user, false);

        /** @var \App\Models\CompanyUser $cu */
        $cu = $this->hydrateCompanyUser($user);

        if ($cu->count() == 0) {
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);
        }

        if (Ninja::isHosted() && !$cu->first()->is_owner && !auth()->user()->account->isEnterprisePaidClient()) {
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);
        }

        return $this->timeConstrainedResponse($cu);
    }

    public function redirectToProvider(string $provider)
    {
        $scopes = [];

        $parameters = [];

        if ($provider == 'google') {
            $scopes = ['https://www.googleapis.com/auth/gmail.send', 'email', 'profile', 'openid'];
            $parameters = ['access_type' => 'offline', 'prompt' => 'consent select_account', 'redirect_uri' => config('ninja.app_url') . '/auth/google'];
        }

        if ($provider == 'microsoft') {
            $scopes = ['email', 'Mail.Send', 'offline_access', 'profile', 'User.Read openid'];
            $parameters = ['response_type' => 'code', 'redirect_uri' => config('ninja.app_url') . "/auth/microsoft"];
        }

        if (request()->hasHeader('X-REACT') || request()->query('react')) {
            /**@var \App\Models\User $user */
            $user = auth()->user();
            Cache::put("react_redir:" . $user?->account->key, 'true', 300);
        }

        if (request()->has('code')) {
            return $this->handleProviderCallback($provider);
        } else {
            if (!in_array($provider, ['google', 'microsoft'])) {
                return abort(400, 'Invalid provider');
            }

            return Socialite::driver($provider)->with($parameters)->scopes($scopes)->redirect();
        }
    }

    public function handleProviderCallback(string $provider)
    {
        if ($provider == 'microsoft') {
            return $this->handleMicrosoftProviderCallback();
        }

        $socialite_user = Socialite::driver($provider)->user();

        $oauth_user_token = '';

        if ($socialite_user->refreshToken) {
            $client = new Google_Client();
            $client->setClientId(config('ninja.auth.google.client_id'));
            $client->setClientSecret(config('ninja.auth.google.client_secret'));
            $client->fetchAccessTokenWithRefreshToken($socialite_user->refreshToken);
            $oauth_user_token = $client->getAccessToken();
        }

        if ($user = OAuth::handleAuth($socialite_user, $provider)) {
            nlog('found user and updating their user record');
            $name = OAuth::splitName($socialite_user->getName());

            $update_user = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_provider_id' => $provider,
            ];

            $user->update($update_user);
            $user->oauth_user_token = $oauth_user_token;
            $user->oauth_user_refresh_token = $socialite_user->refreshToken;
            $user->save();

        } else {
            nlog('user not found for oauth');
        }

        $redirect_url = '/#/';

        $request_from_react = Cache::pull("react_redir:" . auth()->user()?->account?->key);

        // if($request_from_react)
        $redirect_url = config('ninja.react_url') . "/#/settings/user_details/connect";

        return redirect($redirect_url);
    }

    public function handleMicrosoftProviderCallback($provider = 'microsoft')
    {
        $socialite_user = Socialite::driver($provider)->user();

        $oauth_user_token = $socialite_user->accessTokenResponseBody['access_token'];

        $oauth_expiry = now()->addSeconds($socialite_user->accessTokenResponseBody['expires_in']) ?: now()->addSeconds(300);

        if ($user = OAuth::handleAuth($socialite_user, $provider)) {
            nlog('found user and updating their user record');
            $name = OAuth::splitName($socialite_user->getName());

            $update_user = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_provider_id' => $provider,
                'oauth_user_token_expiry' => $oauth_expiry,
            ];

            $user->update($update_user);
            $user->oauth_user_refresh_token = $socialite_user->accessTokenResponseBody['refresh_token'];
            $user->oauth_user_token = $oauth_user_token;
            $user->save();

        } else {
            nlog('user not found for oauth');
        }

        $redirect_url = config('ninja.react_url') . "/#/settings/user_details/connect";

        return redirect($redirect_url);

        // return redirect('/#/');
    }
}

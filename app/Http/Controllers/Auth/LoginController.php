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

namespace App\Http\Controllers\Auth;

use App\DataMapper\Analytics\LoginFailure;
use App\DataMapper\Analytics\LoginSuccess;
use App\Events\User\UserLoggedIn;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Login\LoginRequest;
use App\Jobs\Account\CreateAccount;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Libraries\OAuth\OAuth;
use App\Libraries\OAuth\Providers\Google;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\SystemLog;
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\UserSessionAttributes;
use App\Utils\Traits\User\LoginCache;
use App\Utils\TruthSource;
use Google_Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;
use Turbo124\Beacon\Facades\LightLogs;
use Microsoft\Graph\Model;
use Illuminate\Support\Facades\Http;


class LoginController extends BaseController
{
    /**
      * @OA\Tag(
      *     name="login",
      *     description="Authentication",
      *     @OA\ExternalDocumentation(
      *         description="Find out more",
      *         url="http://docs.invoiceninja.com"
      *     )
      * )
      */

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
     * Once the user is authenticated, we need to set
     * the default company into a session variable.
     *
     * @param Request $request
     * @param User $user
     * @return void
     * deprecated .1 API ONLY we don't need to set any session variables
     */
    public function authenticated(Request $request, User $user): void
    {
        //$this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);
    }

    /**
     * Login via API.
     *
     * @param Request $request The request
     *
     * @return     Response|User Process user login.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @OA\Post(
     *      path="/api/v1/login",
     *      operationId="postLogin",
     *      tags={"login"},
     *      summary="Attempts authentication",
     *      description="Returns a CompanyUser object on success",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/include_static"),
     *      @OA\Parameter(ref="#/components/parameters/clear_cache"),
     *      @OA\RequestBody(
     *         description="User credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     description="The user email address",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     example="1234567",
     *                     description="The user password must meet minimum criteria ~ >6 characters",
     *                     type="string"
     *                 )
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Company User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function apiLogin(LoginRequest $request)
    {
        $this->forced_includes = ['company_users'];

        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return response()
                ->json(['message' => 'Too many login attempts, you are being throttled'], 401)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version'));
        }

        if ($this->attemptLogin($request)) {

            LightLogs::create(new LoginSuccess())
                ->increment()
                ->queue();

            $user = $this->guard()->user();

            //2FA
            if($user->google_2fa_secret && $request->has('one_time_password'))
            {
                $google2fa = new Google2FA();

                if(strlen($request->input('one_time_password')) == 0 || !$google2fa->verifyKey(decrypt($user->google_2fa_secret), $request->input('one_time_password')))
                {
                    return response()
                    ->json(['message' => ctrans('texts.invalid_one_time_password')], 401)
                    ->header('X-App-Version', config('ninja.app_version'))
                    ->header('X-Api-Version', config('ninja.minimum_client_version'));
                }

            }
            elseif($user->google_2fa_secret && !$request->has('one_time_password')) {

                    return response()
                    ->json(['message' => ctrans('texts.invalid_one_time_password')], 401)
                    ->header('X-App-Version', config('ninja.app_version'))
                    ->header('X-Api-Version', config('ninja.minimum_client_version'));
            }

            /* If for some reason we lose state on the default company ie. a company is deleted - always make sure we can default to a company*/
            if(!$user->account->default_company){
                $account = $user->account;
                $account->default_company_id = $user->companies->first()->id;
                $account->save();
                $user = $user->fresh();
            }

            $cu = $this->hydrateCompanyUser();

            if($cu->count() == 0)
                return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

            /*On the hosted platform, only owners can login for free/pro accounts*/
            if (Ninja::isHosted() && !$cu->first()->is_owner && !$user->account->isEnterpriseClient())
                return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

            event(new UserLoggedIn($user, $user->account->default_company, Ninja::eventVars($user->id)));

            return $this->timeConstrainedResponse($cu);


        } else {

            LightLogs::create(new LoginFailure())
                ->increment()
                ->queue();

            $this->incrementLoginAttempts($request);

            return response()
                ->json(['message' => ctrans('texts.invalid_credentials')], 401)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version'));

        }
    }

    /**
     * Refreshes the data feed with the current Company User.
     *
     * @param Request $request
     * @return     CompanyUser Refresh Feed.
     *
     *
     * @OA\Post(
     *      path="/api/v1/refresh",
     *      operationId="refresh",
     *      tags={"refresh"},
     *      summary="Refreshes the dataset",
     *      description="Refreshes the dataset",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/include_static"),
     *      @OA\Parameter(ref="#/components/parameters/clear_cache"),
     *      @OA\Response(
     *          response=200,
     *          description="The Company User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function refresh(Request $request)
    {
        $truth = app()->make(TruthSource::class);

        if ($truth->getCompanyToken())
            $company_token = $truth->getCompanyToken();
        else
            $company_token = CompanyToken::where('token', $request->header('X-API-TOKEN'))->first();

        $cu = CompanyUser::query()
            ->where('user_id', $company_token->user_id);

        if ($cu->count() == 0)
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

        $cu->first()->account->companies->each(function ($company) use ($cu, $request) {

            if ($company->tokens()->where('is_system', true)->count() == 0) {
                CreateCompanyToken::dispatchNow($company, $cu->first()->user, $request->server('HTTP_USER_AGENT'));
            }
        });

        if ($request->has('current_company') && $request->input('current_company') == 'true')
            $cu->where("company_id", $company_token->company_id);

        if (Ninja::isHosted() && !$cu->first()->is_owner && !$cu->first()->user->account->isEnterpriseClient())
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

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
        nlog($user);
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

            if (!$existing_user->account)
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

            Auth::login($existing_user, true);

            $cu = $this->hydrateCompanyUser();

            if ($cu->count() == 0)
                return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

            if (Ninja::isHosted() && !$cu->first()->is_owner && !$existing_user->account->isEnterpriseClient())
                return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

            return $this->timeConstrainedResponse($cu);

        }
        //If this is a result user/email combo - lets add their OAuth details details
        if ($existing_login_user = MultiDB::hasUser(['email' => $user->email])) {
            if (!$existing_login_user->account)
                return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

            Auth::login($existing_login_user, true);

            auth()->user()->update([
                'oauth_user_id' => $user->id,
                'oauth_provider_id' => $provider,
            ]);

            $cu = $this->hydrateCompanyUser();

            if ($cu->count() == 0)
                return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

            if (Ninja::isHosted() && !$cu->first()->is_owner && !$existing_login_user->account->isEnterpriseClient())
                return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

            return $this->timeConstrainedResponse($cu);
        }
        $name = OAuth::splitName($user->name);

        $new_account = [
            'first_name' => $name[0],
            'last_name' => $name[1],
            'password' => '',
            'email' => $user->email,
            'oauth_user_id' => $user->id,
            'oauth_provider_id' => $provider,
        ];

        MultiDB::setDefaultDatabase();

        $account = CreateAccount::dispatchNow($new_account, request()->getClientIp());

        Auth::login($account->default_company->owner(), true);
        auth()->user()->email_verified_at = now();
        auth()->user()->save();

        $cu = $this->hydrateCompanyUser();

        if ($cu->count() == 0)
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

        if (Ninja::isHosted() && !$cu->first()->is_owner && !auth()->user()->account->isEnterpriseClient())
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

        return $this->timeConstrainedResponse($cu);
    }


    private function hydrateCompanyUser(): Builder
    {

        $cu = CompanyUser::query()->where('user_id', auth()->user()->id);

        if($cu->count() == 0)
            return $cu;

        if (CompanyUser::query()->where('user_id', auth()->user()->id)->where('company_id', auth()->user()->account->default_company_id)->exists())
            $set_company = auth()->user()->account->default_company;
        else {
            $set_company = $cu->first()->company;
        }

        auth()->user()->setCompany($set_company);

        $this->setLoginCache(auth()->user());

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($cu->first());
        $truth->setUser(auth()->user());
        $truth->setCompany($set_company);

        if($cu->count() == 0)
            return $cu;

        if (auth()->user()->company_users()->count() != auth()->user()->tokens()->distinct('company_id')->count()) {

            auth()->user()->companies->each(function ($company) {

                if (!CompanyToken::where('user_id', auth()->user()->id)->where('company_id', $company->id)->exists()) {

                    CreateCompanyToken::dispatchNow($company, auth()->user(), "Google_O_Auth");

                }

          });

        }

        $truth->setCompanyToken(CompanyToken::where('user_id', auth()->user()->id)->where('company_id', $set_company->id)->first());

        return $cu;

    }

    private function handleMicrosoftOauth()
    {
        if(request()->has('accessToken'))
            $accessToken = request()->input('accessToken');
        elseif(request()->has('access_token'))
            $accessToken = request()->input('access_token');
        else
            return response()->json(['message' => 'Invalid response from oauth server, no access token in response.'], 400);

        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($accessToken);

        $user = $graph->createRequest("GET", "/me")
                      ->setReturnType(Model\User::class)
                      ->execute();

        if($user){

            $email = $user->getMail() ?: $user->getUserPrincipalName();

            $query = [
                'oauth_user_id' => $user->getId(),
                'oauth_provider_id'=> 'microsoft',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {

                if(!$existing_user->account)
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

                return $this->existingOauthUser($existing_user);
            }

            //If this is a result user/email combo - lets add their OAuth details details
            if($existing_login_user = MultiDB::hasUser(['email' => $email]))
            {
                if(!$existing_login_user->account)
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

                Auth::login($existing_login_user, true);

                return $this->existingLoginUser($user->getId(), 'microsoft');
            }

            // Signup!
            $new_account = [
                'first_name' => $user->getGivenName() ?: '',
                'last_name' => $user->getSurname() ?: '' ,
                'password' => '',
                'email' => $email,
                'oauth_user_id' => $user->getId(),
                'oauth_provider_id' => 'microsoft',
            ];

            return $this->createNewAccount($new_account);

        }

        return response()->json(['message' => 'Unable to authenticate this user'], 400);

    }

    private function existingOauthUser($existing_user)
    {
        Auth::login($existing_user, true);

        $cu = $this->hydrateCompanyUser();

        if($cu->count() == 0)
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

        if(Ninja::isHosted() && !$cu->first()->is_owner && !$existing_user->account->isEnterpriseClient())
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

        return $this->timeConstrainedResponse($cu);
    }

    private function existingLoginUser($oauth_user_id, $provider)
    {

        auth()->user()->update([
            'oauth_user_id' => $oauth_user_id,
            'oauth_provider_id'=> $provider,
            ]);

        $cu = $this->hydrateCompanyUser();

        if($cu->count() == 0)
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

        if(Ninja::isHosted() && !$cu->first()->is_owner && !auth()->user()->account->isEnterpriseClient())
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

        return $this->timeConstrainedResponse($cu);

    }

    private function handleGoogleOauth()
    {
        $user = false;

        $google = new Google();

        $user = $google->getTokenResponse(request()->input('id_token'));

        if (is_array($user)) {

            $query = [
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_provider_id'=> 'google',
            ];

            if ($existing_user = MultiDB::hasUser($query)) {

                if(!$existing_user->account)
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

                return $this->existingOauthUser($existing_user);
            }

            //If this is a result user/email combo - lets add their OAuth details details
            if($existing_login_user = MultiDB::hasUser(['email' => $google->harvestEmail($user)]))
            {
                if(!$existing_login_user->account)
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

                Auth::login($existing_login_user, true);

                return $this->existingLoginUser($google->harvestSubField($user), 'google');
            }

        }

        if ($user) {

            //check the user doesn't already exist in some form
            if($existing_login_user = MultiDB::hasUser(['email' => $google->harvestEmail($user)]))
            {
                if(!$existing_login_user->account)
                    return response()->json(['message' => 'User exists, but not attached to any companies! Orphaned user!'], 400);

                Auth::login($existing_login_user, true);

                return $this->existingLoginUser($google->harvestSubField($user), 'google');
            }

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

        return response()
        ->json(['message' => ctrans('texts.invalid_credentials')], 401)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function createNewAccount($new_account)
    {

        MultiDB::setDefaultDatabase();

        $account = CreateAccount::dispatchNow($new_account, request()->getClientIp());

        if(!$account instanceOf Account)
            return $account;

        Auth::login($account->default_company->owner(), true);
        auth()->user()->email_verified_at = now();
        auth()->user()->save();

        $cu = $this->hydrateCompanyUser();

        if($cu->count() == 0)
            return response()->json(['message' => 'User found, but not attached to any companies, please see your administrator'], 400);

        if(Ninja::isHosted() && !$cu->first()->is_owner && !auth()->user()->account->isEnterpriseClient())
            return response()->json(['message' => 'Pro / Free accounts only the owner can log in. Please upgrade'], 403);

        return $this->timeConstrainedResponse($cu);

    }

    public function redirectToProvider(string $provider)
    {

        $scopes = [];

        $parameters = [];

        if($provider == 'google'){

            $scopes = ['https://www.googleapis.com/auth/gmail.send','email','profile','openid'];
            $parameters = ['access_type' => 'offline', "prompt" => "consent select_account", 'redirect_uri' => config('ninja.app_url')."/auth/google"];
        }

        if($provider == 'microsoft'){
            $scopes = ['email', 'Mail.Send', 'offline_access', 'profile', 'User.Read openid'];
            $parameters = ['response_type' => 'code', 'redirect_uri' => config('ninja.app_url')."/auth/microsoft"];
        }

        if (request()->has('code')) {
            return $this->handleProviderCallback($provider);
        } else {

            if(!in_array($provider, ['google','microsoft']))
                return abort(400, 'Invalid provider');

            return Socialite::driver($provider)->with($parameters)->scopes($scopes)->redirect();
        }
    }

    public function handleProviderCallback(string $provider)
    {

        if($provider == 'microsoft')
            return $this->handleMicrosoftProviderCallback();

        $socialite_user = Socialite::driver($provider)->user();

        $oauth_user_token = '';

            if($socialite_user->refreshToken){

                $client = new Google_Client();
                $client->setClientId(config('ninja.auth.google.client_id'));
                $client->setClientSecret(config('ninja.auth.google.client_secret'));
                $client->fetchAccessTokenWithRefreshToken($socialite_user->refreshToken);
                $oauth_user_token = $client->getAccessToken();

            }

        if($user = OAuth::handleAuth($socialite_user, $provider))
        {

            nlog('found user and updating their user record');
            $name = OAuth::splitName($socialite_user->getName());

            $update_user = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_provider_id' => $provider,
                'oauth_user_token' => $oauth_user_token,
                'oauth_user_refresh_token' => $socialite_user->refreshToken
            ];

            $user->update($update_user);

        }
        else {
            nlog("user not found for oauth");
        }

        return redirect('/#/');
    }

    public function handleMicrosoftProviderCallback($provider = 'microsoft')
    {

        $socialite_user = Socialite::driver($provider)->user();
        nlog($socialite_user);

        nlog("refresh token " . $socialite_user->accessTokenResponseBody['refresh_token']);
        nlog("access token " . $socialite_user->accessTokenResponseBody['access_token']);

        $oauth_user_token = $socialite_user->accessTokenResponseBody['access_token'];

        $oauth_expiry = now()->addSeconds($socialite_user->accessTokenResponseBody['expires_in']) ?: now()->addSeconds(300);

        if($user = OAuth::handleAuth($socialite_user, $provider))
        {

            nlog('found user and updating their user record');
            $name = OAuth::splitName($socialite_user->getName());

            $update_user = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_provider_id' => $provider,
                'oauth_user_token' => $oauth_user_token,
                'oauth_user_refresh_token' => $socialite_user->accessTokenResponseBody['refresh_token'],
                'oauth_user_token_expiry' => $oauth_expiry,
            ];

            $user->update($update_user);

        }
        else {
            nlog("user not found for oauth");
        }

        return redirect('/#/');

    }

}

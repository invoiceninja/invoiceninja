<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Libraries\OAuth\OAuth;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
use App\Transformers\UserTransformer;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */
   
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
     * the default company into a session variable
     *
     * @return void
     * deprecated .1 API ONLY we don't need to set any session variables
     */
    public function authenticated(Request $request, User $user) : void
    {
        //$this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);
    }


    /**
     * Login via API
     *
     * @param      \Illuminate\Http\Request  $request  The request
     *
     * @return     Response|User Process user login.
     *
     *
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
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
     *
     */

    public function apiLogin(Request $request)
    {
        $this->forced_includes = ['company_users'];

        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return response()
            ->json(['message' => 'Too many login attempts, you are being throttled'], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.api_version'));
        }

        if ($this->attemptLogin($request)) {
            $user = $this->guard()->user();
            
            $user->setCompany($user->company_user->account->default_company);

            $ct = CompanyUser::whereUserId($user->id)->with('company');

            return $this->listResponse($ct);
        } else {
            $this->incrementLoginAttempts($request);

            return response()
            ->json(['message' => ctrans('texts.invalid_credentials')], 401)
            ->header('X-App-Version', config('ninja.app_version'))
            ->header('X-Api-Version', config('ninja.api_version'));
        }
    }

    /**
     * Refreshes the data feed with the current Company User
     *
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
     *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
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
     *
     */
    public function refresh(Request $request)
    {
        $ct = CompanyUser::whereUserId(auth()->user()->id);
        return $this->listResponse($ct);
    }

    /**
     * Redirect the user to the provider authentication page
     *
     * @return void
     */
    public function redirectToProvider(string $provider)
    {
        //'https://www.googleapis.com/auth/gmail.send','email','profile','openid'
        //
        if (request()->has('code')) {
            return $this->handleProviderCallback($provider);
        } else {
            return Socialite::driver($provider)->scopes('https://www.googleapis.com/auth/gmail.send')->redirect();
        }
    }


    public function redirectToProviderAndCreate(string $provider)
    {
        $redirect_url = config('services.' . $provider . '.redirect') . '/create';

        if (request()->has('code')) {
            return $this->handleProviderCallbackAndCreate($provider);
        } else {
            return Socialite::driver($provider)->scopes('https://www.googleapis.com/auth/gmail.send')->redirectUrl($redirect_url)->redirect();
        }
    }


    
    public function handleProviderCallbackAndCreate(string $provider)
    {
        $redirect_url = config('services.' . $provider . '.redirect') . '/create';

        $socialite_user = Socialite::driver($provider)
                                    ->redirectUrl($redirect_url)
                                    ->stateless()
                                    ->user();

        /* Handle existing users who attempt to create another account with existing OAuth credentials */
        if ($user = OAuth::handleAuth($socialite_user, $provider)) {
            $user->oauth_user_token = $socialite_user->refreshToken;
            $user->save();
            Auth::login($user, true);
            
            return redirect($this->redirectTo);
        } elseif (MultiDB::checkUserEmailExists($socialite_user->getEmail())) {
            Session::flash('error', 'User exists in system, but not with this authentication method'); //todo add translations

            return view('auth.login');
        }
        /** 3. Automagically creating a new account here. */
        else {
            //todo
            $name = OAuth::splitName($socialite_user->getName());

            $new_account = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'password' => '',
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_user_token' => $socialite_user->refreshToken,
                'oauth_provider_id' => $provider
            ];

            MultiDB::setDefaultDatabase();
            
            $account = CreateAccount::dispatchNow($new_account);

            Auth::login($account->default_company->owner(), true);
            
            $cookie = cookie('db', $account->default_company->db);

            return redirect($this->redirectTo)->withCookie($cookie);
        }
    }

    /**
     * We use this function when OAUTHING via the web interface
     *
     * @return redirect
     */
    public function handleProviderCallback(string $provider)
    {
        $redirect_url = config('services.' . $provider . '.redirect');

        $socialite_user = Socialite::driver($provider)
                                    ->redirectUrl($redirect_url)
                                    ->stateless()
                                    ->user();

        if ($user = OAuth::handleAuth($socialite_user, $provider)) {
            $user->oauth_user_token = $socialite_user->token;
            $user->save();
            Auth::login($user, true);
            
            return redirect($this->redirectTo);
        } elseif (MultiDB::checkUserEmailExists($socialite_user->getEmail())) {
            Session::flash('error', 'User exists in system, but not with this authentication method'); //todo add translations

            return view('auth.login');
        }
        /** 3. Automagically creating a new account here. */
        else {
            //todo
            $name = OAuth::splitName($socialite_user->getName());

            $new_account = [
                'first_name' => $name[0],
                'last_name' => $name[1],
                'password' => '',
                'email' => $socialite_user->getEmail(),
                'oauth_user_id' => $socialite_user->getId(),
                'oauth_user_token' => $socialite_user->token,
                'oauth_provider_id' => $provider
            ];

            $account = CreateAccount::dispatchNow($new_account);

            Auth::login($account->default_company->owner(), true);
            
            $cookie = cookie('db', $account->default_company->db);

            return redirect($this->redirectTo)->withCookie($cookie);
        }
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
        $user = false;

        $oauth = new OAuth();

        $user = $oauth->getProvider(request()->input('provider'))->getTokenResponse(request()->input('token'));

        if ($user) {
            $ct = CompanyUser::whereUserId($user);
            return $this->listResponse($ct);
        //  return $this->itemResponse($user);
        } else {
            return $this->errorResponse(['message' => 'Invalid credentials'], 401);
        }
    }
}

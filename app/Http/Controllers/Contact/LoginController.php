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

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Libraries\OAuth\OAuth;
use App\Models\ClientContact;
use App\Models\User;
use App\Transformers\ClientContactLoginTransformer;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    use AuthenticatesUsers;
    use UserSessionAttributes;

    protected $entity_type = ClientContact::class;

    protected $entity_transformer = ClientContactLoginTransformer::class;

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
     * Login via API.
     *
     * @param Request $request The request
     *
     * @return     Response|User Process user login.
     * @throws \Illuminate\Validation\ValidationException
     */
    public function apiLogin(Request $request)
    {
        Auth::shouldUse('contact');

        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return response()->json(['message' => 'Too many login attempts, you are being throttled']);
        }

        if ($this->attemptLogin($request)) {
            return $this->itemResponse($this->guard()->user());
        } else {
            $this->incrementLoginAttempts($request);

            return response()->json(['message' => ctrans('texts.invalid_credentials')]);
        }
    }

    /**
     * Redirect the user to the provider authentication page.
     *
     * @param string $provider
     * @return void
     */
    public function redirectToProvider(string $provider)
    {
        //'https://www.googleapis.com/auth/gmail.send','email','profile','openid'
        $scopes = [];

        if ($provider == 'google') {
            $scopes = ['https://www.googleapis.com/auth/gmail.send', 'email', 'profile', 'openid'];
        }

        if (request()->has('code')) {
            return $this->handleProviderCallback($provider);
        } else {
            return Socialite::driver($provider)->scopes($scopes)->redirect();
        }
    }

    public function redirectToProviderAndCreate(string $provider)
    {
        $redirect_url = config('services.'.$provider.'.redirect').'/create';

        if (request()->has('code')) {
            return $this->handleProviderCallbackAndCreate($provider);
        } else {
            return Socialite::driver($provider)->redirectUrl($redirect_url)->redirect();
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
            return $this->itemResponse($user);
        } else {
            return $this->errorResponse(['message' => 'Invalid credentials'], 401);
        }
    }
}

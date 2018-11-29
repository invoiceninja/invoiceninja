<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Libraries\OAuth;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
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
        $this->middleware('guest:user')->except('logout');
    }

    /**
     * Once the user is authenticated, we need to set
     * the default company into a session variable
     *
     * @return void
     */
    public function authenticated(Request $request, User $user) : void
    {
        $this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);
    }

    /**
     * Redirect the user to the provider authentication page
     *
     * @return void
     */
    public function redirectToProvider(string $provider) 
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Received the returning object from the provider
     * which we will use to resolve the user
     *
     * @return redirect
     */
    public function handleProviderCallback(string $provider) 
    {
        $socialite_user = Socialite::driver($provider)->user();

        if($user = OAuth::handleAuth($socialite_user, $provider))
        {
            Auth::login($user, true);
            
            return redirect($this->redirectTo);
        }

        //throw error

    }
}

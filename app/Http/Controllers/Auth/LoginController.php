<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Libraries\OAuth;
use App\Models\User;
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

    use AuthenticatesUsers;
    use UserSessionAttributes;

    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

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
     //   $this->middleware('guest:user')->except('logout');
    }

    /**
     * Once the user is authenticated, we need to set
     * the default company into a session variable
     *
     * @return void
     */
    public function authenticated(Request $request, User $user) : void
    {
        //$this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);
    }

    public function apiLogin(Request $request)
    {
        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return response()->json(['message' => 'Too many login attempts, you are being throttled']);
        }

        if ($this->attemptLogin($request))
            return $this->itemResponse($this->guard()->user());
        else {

            $this->incrementLoginAttempts($request);

            return response()->json(['message' => ctrans('texts.invalid_credentials')]);
        }

    }

    /**
     * Redirect the user to the provider authentication page
     *
     * @return void
     */
    public function redirectToProvider(string $provider) 
    {
        if(request()->has('code'))
            return $this->handleProviderCallback($provider);
        else
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
        $socialite_user = Socialite::driver($provider)->stateless()->user();

        if($user = OAuth::handleAuth($socialite_user, $provider))
        {
            //Auth::login($user, true);
            return $this->itemResponse($user);
            //return redirect($this->redirectTo); //todo return USERACCOUNT json
        }
        else if(MultiDB::checkUserEmailExists($socialite_user->getEmail()))
        {

            return $this->errorResponse(['message'=>'User exists in system, but not with this authentication method'], 400);

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
            ];

            $account = CreateAccount::dispatchNow($new_account);

            return $this->itemResponse($account->default_company->owner());
        }


    }
}

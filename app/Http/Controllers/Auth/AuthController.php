<?php namespace App\Http\Controllers\Auth;

use Auth;
use Event;
use Utils;
use Session;
use Illuminate\Http\Request;
use App\Models\User;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Ninja\Repositories\AccountRepository;
use App\Services\AuthService;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers;

    /**
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * Create a new authentication controller instance.
     *
     * @param AccountRepository $repo
     * @param AuthService $authService
     * @internal param \Illuminate\Contracts\Auth\Guard $auth
     * @internal param \Illuminate\Contracts\Auth\Registrar $registrar
     */
    public function __construct(AccountRepository $repo, AuthService $authService)
    {
        $this->accountRepo = $repo;
        $this->authService = $authService;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array $data
     *
     * @return User
     */
    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    /**
     * @param $provider
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authLogin($provider, Request $request)
    {
        return $this->authService->execute($provider, $request->has('code'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authUnlink()
    {
        $this->accountRepo->unlinkUserFromOauth(Auth::user());

        Session::flash('message', trans('texts.updated_settings'));
        return redirect()->to('/settings/' . ACCOUNT_USER_DETAILS);
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function getLoginWrapper()
    {
        if (!Utils::isNinja() && !User::count()) {
            return redirect()->to('invoice_now');
        }

        return self::getLogin();
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postLoginWrapper(Request $request)
    {

        $userId = Auth::check() ? Auth::user()->id : null;
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user && $user->failed_logins >= MAX_FAILED_LOGINS) {
            Session::flash('error', trans('texts.invalid_credentials'));
            return redirect()->to('login');
        }

        $response = self::postLogin($request);

        if (Auth::check()) {
            Event::fire(new UserLoggedIn());

            $users = false;
            // we're linking a new account
            if ($request->link_accounts && $userId && Auth::user()->id != $userId) {
                $users = $this->accountRepo->associateAccounts($userId, Auth::user()->id);
                Session::flash('message', trans('texts.associated_accounts'));
                // check if other accounts are linked
            } else {
                $users = $this->accountRepo->loadAccounts(Auth::user()->id);
            }
            Session::put(SESSION_USER_ACCOUNTS, $users);

        } elseif ($user) {
            $user->failed_logins = $user->failed_logins + 1;
            $user->save();
        }

        return $response;
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function getLogoutWrapper()
    {
        if (Auth::check() && !Auth::user()->registered) {
            $account = Auth::user()->account;
            $this->accountRepo->unlinkAccount($account);
            if ($account->company->accounts->count() == 1) {
                $account->company->forceDelete();
            }
            $account->forceDelete();
        }

        $response = self::getLogout();

        Session::flush();

        return $response;
    }
}

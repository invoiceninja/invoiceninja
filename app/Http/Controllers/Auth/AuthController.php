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
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Registrar;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class AuthController extends Controller {

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

    protected $loginPath = '/login';
    protected $redirectTo = '/dashboard';
    protected $accountRepo;

	/**
	 * Create a new authentication controller instance.
	 *
	 * @param  \Illuminate\Contracts\Auth\Guard  $auth
	 * @param  \Illuminate\Contracts\Auth\Registrar  $registrar
	 * @return void
	 */
	public function __construct(Guard $auth, Registrar $registrar, AccountRepository $repo)
	{
		$this->auth = $auth;
		$this->registrar = $registrar;
        $this->accountRepo = $repo;

		//$this->middleware('guest', ['except' => 'getLogout']);
	}

    public function getLoginWrapper()
    {
        if (!Utils::isNinja() && !User::count()) {
            return redirect()->to('invoice_now');
        }

        return self::getLogin();
    }

    public function postLoginWrapper(Request $request)
    {
        $userId = Auth::check() ? Auth::user()->id : null;
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user && $user->failed_logins >= 3) {
            Session::flash('error', 'These credentials do not match our records.');
            return redirect()->to('login');
        }

        $response = self::postLogin($request);

        if (Auth::check()) {
            Event::fire(new UserLoggedIn());

            $users = false;
            // we're linking a new account
            if ($userId && Auth::user()->id != $userId) {
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

    public function getLogoutWrapper()
    {
        if (Auth::check() && !Auth::user()->registered) {
            $account = Auth::user()->account;
            $this->accountRepo->unlinkAccount($account);
            $account->forceDelete();
        }

        $response = self::getLogout();

        Session::flush();

        return $response;
    }
}

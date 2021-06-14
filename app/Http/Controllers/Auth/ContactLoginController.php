<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Auth;

use App\Events\Contact\ContactLoggedIn;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Route;

class ContactLoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/client/dashboard';

    public function __construct()
    {
        $this->middleware('guest:contact', ['except' => ['logout']]);
    }

    public function showLoginForm(Request $request)
    {
        if ($request->subdomain) {
            $company = Company::where('subdomain', $request->subdomain)->first();
        } elseif (Ninja::isSelfHost()) {
            $company = Account::first()->default_company;
        } else {
            $company = null;
        }

        $account_id = $request->get('account_id');
        $account = Account::find($account_id);

        return $this->render('auth.login', ['account' => $account, 'company' => $company]);

    }

    public function login(Request $request)
    {
        Auth::shouldUse('contact');

        $this->validateLogin($request);
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }
        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }
        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    public function authenticated(Request $request, ClientContact $client)
    {
        Auth::guard('contact')->login($client, true);

        event(new ContactLoggedIn($client, $client->company, Ninja::eventVars()));

        if (session()->get('url.intended')) {
            return redirect(session()->get('url.intended'));
        }

        return redirect(route('client.dashboard'));
    }

    public function logout()
    {
        Auth::guard('contact')->logout();

        return redirect('/client/login');
    }
}

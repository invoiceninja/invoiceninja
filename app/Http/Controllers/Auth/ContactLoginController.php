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

use App\Events\Contact\ContactLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\ViewComposers\PortalComposer;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Route;

class ContactLoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/client/invoices';

    public function __construct()
    {
        $this->middleware('guest:contact', ['except' => ['logout']]);
    }

    public function showLoginForm(Request $request, $company_key = false)
    {
        $company = false;
        $account = false;

        if ($request->session()->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->session()->get('company_key'));
            $company = Company::where('company_key', $request->input('company_key'))->first();
        }
        elseif($request->has('company_key')){
             MultiDB::findAndSetDbByCompanyKey($request->input('company_key'));
            $company = Company::where('company_key', $request->input('company_key'))->first();
        }elseif($company_key){
             MultiDB::findAndSetDbByCompanyKey($company_key);
            $company = Company::where('company_key', $company_key)->first();
        }

        if ($company) {
            $account = $company->account;
        } elseif (! $company && strpos($request->getHost(), 'invoicing.co') !== false) {
            $subdomain = explode('.', $request->getHost())[0];
            MultiDB::findAndSetDbByDomain(['subdomain' => $subdomain]);
            $company = Company::where('subdomain', $subdomain)->first();
        } elseif (Ninja::isHosted()) {
            MultiDB::findAndSetDbByDomain(['portal_domain' => $request->getSchemeAndHttpHost()]);

            $company = Company::where('portal_domain', $request->getSchemeAndHttpHost())->first();
        } elseif (Ninja::isSelfHost()) {
            $account = Account::first();
            $company = $account->default_company;
        } else {
            $company = null;
        }

        if (! $account) {
            $account_id = $request->get('account_id');
            $account = Account::find($account_id);
        }

        return $this->render('auth.login', ['account' => $account, 'company' => $company]);
    }

    public function login(Request $request)
    {
        Auth::shouldUse('contact');

        if (Ninja::isHosted() && $request->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->input('company_key'));
        }

        $this->validateLogin($request);
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (Ninja::isHosted() && $request->has('password') && $company = Company::where('company_key', $request->input('company_key'))->first()) {
            $contact = ClientContact::where(['email' => $request->input('email'), 'company_id' => $company->id])
                                     ->whereHas('client', function ($query) {
                                         $query->where('is_deleted', 0);
                                     })->first();

            if (! $contact) {
                return $this->sendFailedLoginResponse($request);
            }

            if (Hash::check($request->input('password'), $contact->password)) {
                return $this->authenticated($request, $contact);
            }
        } elseif ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }
        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        $this->setRedirectPath();

        return $request->wantsJson()
                    ? new JsonResponse([], 204)
                    : redirect()->intended($this->redirectPath());
    }

    public function authenticated(Request $request, ClientContact $client)
    {
        auth()->guard('contact')->loginUsingId($client->id, true);

        event(new ContactLoggedIn($client, $client->company, Ninja::eventVars()));

        if (session()->get('url.intended')) {
            return redirect(session()->get('url.intended'));
        }

        $this->setRedirectPath();

        return redirect($this->redirectTo);
    }

    public function logout()
    {
        Auth::guard('contact')->logout();
        request()->session()->invalidate();

        return redirect('/client/login');
    }

    private function setRedirectPath()
    {
        if (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES) {
            $this->redirectTo = '/client/invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES) {
            $this->redirectTo = '/client/recurring_invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_QUOTES) {
            $this->redirectTo = '/client/quotes';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS) {
            $this->redirectTo = '/client/credits';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_TASKS) {
            $this->redirectTo = '/client/tasks';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_EXPENSES) {
            $this->redirectTo = '/client/expenses';
        }
    }
}

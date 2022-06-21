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

use App\Http\Controllers\Controller;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/client/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest:contact');
    }

    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param Request $request
     * @param  string|null  $token
     * @return Factory|View
     */
    public function showResetForm(Request $request, $token = null)
    {
        if ($request->session()->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->session()->get('company_key'));
            $company = Company::where('company_key', $request->session()->get('company_key'))->first();
            $db = $company->db;
            $account = $company->account;
        } else {
            $account_key = $request->session()->has('account_key') ? $request->session()->get('account_key') : false;

            if ($account_key) {
                MultiDB::findAndSetDbByAccountKey($account_key);
                $account = Account::where('key', $account_key)->first();
                $db = $account->companies->first()->db;
                $company = $account->companies->first();
            } else {
                $account = Account::first();
                $db = $account->companies->first()->db;
                $company = $account->companies->first();
            }
        }

        return $this->render('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email, 'account' => $account, 'db' => $db, 'company' => $company]
        );
    }

    public function reset(Request $request)
    {
        if ($request->session()->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->session()->get('company_key'));
        }

        $request->validate($this->rules(), $this->validationErrorMessages());

        $user = ClientContact::where($request->only(['email', 'token']))->first();

        if (! $user) {
            return $this->sendResetFailedResponse($request, PASSWORD::INVALID_USER);
        }

        $hashed_password = Hash::make($request->input('password'));

        ClientContact::where('email', $user->email)->update([
            'password' => $hashed_password,
            'remember_token' => Str::random(60),
        ]);

        event(new PasswordReset($user));

        auth()->login($user, true);

        $response = Password::PASSWORD_RESET;

        // Added this because it collides the session between
        // client & main portal giving unlimited redirects.
        auth()->logout();

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $response == Password::PASSWORD_RESET
                    ? $this->sendResetResponse($request, $response)
                    : $this->sendResetFailedResponse($request, $response);
    }

    protected function guard()
    {
        return Auth::guard('contact');
    }

    public function broker()
    {
        return Password::broker('contacts');
    }
}

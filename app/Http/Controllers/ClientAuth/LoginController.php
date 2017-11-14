<?php

namespace App\Http\Controllers\ClientAuth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

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

    /**
     * Where to redirect users after login.
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
        $this->middleware('guest:client', ['except' => 'logout']);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return auth()->guard('client');
    }

    /**
     * @return mixed
     */
    public function showLoginForm()
    {
        if (! session('contact_key')) {
            return redirect('/client/session_expired');
        }

        $data = [
			'clientauth' => true,
		];

        return view('clientauth.login')->with($data);
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    protected function credentials(Request $request)
    {
        $credentials = $request->only('password');
        $credentials['id'] = null;

        $contactKey = session('contact_key');
        if ($contactKey) {
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && ! $contact->is_deleted) {
                $credentials['id'] = $contact->id;
            }
        }

        return $credentials;
    }

    /**
     * Validate the user login request - don't require the email
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            'password' => 'required',
        ]);
    }

    /**
     * @return mixed
     */
    public function getSessionExpired()
    {
        return view('clientauth.sessionexpired')->with(['clientauth' => true]);
    }

}

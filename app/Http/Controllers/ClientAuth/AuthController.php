<?php

namespace App\Http\Controllers\ClientAuth;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Session;

class AuthController extends Controller
{
    use AuthenticatesUsers;

    /**
     * @var string
     */
    protected $guard = 'client';

    /**
     * @var string
     */
    protected $redirectTo = '/client/dashboard';

    /**
     * @return mixed
     */
    public function showLoginForm()
    {
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
    protected function getCredentials(Request $request)
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
     * Validate the user login request.
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

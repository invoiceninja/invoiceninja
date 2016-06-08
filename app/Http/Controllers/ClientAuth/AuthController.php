<?php namespace App\Http\Controllers\ClientAuth;

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
use App\Models\Contact;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class AuthController extends Controller {

	protected $guard = 'client';
    protected $redirectTo = '/client/dashboard';

	use AuthenticatesUsers;

	public function showLoginForm()
	{
        $data = array();
        
        $contactKey = session('contact_key');
        if($contactKey){
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && !$contact->is_deleted) {
                $account = $contact->account;

                $data['account'] = $account;
                $data['clientFontUrl'] = $account->getFontsUrl();
            }
        }
        
		return view('clientauth.login')->with($data);
	}

	/**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getCredentials(Request $request)
    {
        $credentials = $request->only('password');
        $credentials['id'] = null;

        $contactKey = session('contact_key');
        if($contactKey){
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && !$contact->is_deleted) {
                $credentials['id'] = $contact->id;
            }
        }
        
        return $credentials;
    }
	
	/**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            'password' => 'required',
        ]);
    }

    public function getSessionExpired()
    {
        return view('clientauth.sessionexpired');
    }
}

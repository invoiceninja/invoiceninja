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
use App\Models\Invitation;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class AuthController extends Controller {

	protected $guard = 'client';
    protected $redirectTo = '/client/dashboard';

	use AuthenticatesUsers;

	public function showLoginForm()
	{
        $data = array(
        );
        
        $invitation_key = session('invitation_key');
        if($invitation_key){
            $invitation = Invitation::where('invitation_key', '=', $invitation_key)->first();
            if ($invitation && !$invitation->is_deleted) {
                $invoice = $invitation->invoice;
                $client = $invoice->client;
                $account = $client->account;
                
                $data['hideLogo'] = $account->hasFeature(FEATURE_WHITE_LABEL);
                $data['clientViewCSS'] = $account->clientViewCSS();
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
        
        $invitation_key = session('invitation_key');
        if($invitation_key){
            $invitation = Invitation::where('invitation_key', '=', $invitation_key)->first();
            if ($invitation && !$invitation->is_deleted) {
                $credentials['id'] = $invitation->contact_id;
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
}

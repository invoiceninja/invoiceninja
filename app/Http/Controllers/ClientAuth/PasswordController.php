<?php namespace App\Http\Controllers\ClientAuth;

use Config;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Password;
use App\Models\Invitation;


class PasswordController extends Controller {

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

    protected $redirectTo = '/client/dashboard';
    
	/**
	 * Create a new password controller instance.
	 *
	 * @param  \Illuminate\Contracts\Auth\Guard  $auth
	 * @param  \Illuminate\Contracts\Auth\PasswordBroker  $passwords
	 * @return void
	 */
	public function __construct()
	{
		$this->middleware('guest');
        Config::set("auth.defaults.passwords","client");
	}

	public function showLinkRequestForm()
	{
        $data = array();
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
        
		return view('clientauth.password')->with($data);
	}
	
	/**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendResetLinkEmail(Request $request)
    {
        $broker = $this->getBroker();

        $contact_id = null;
        $invitation_key = session('invitation_key');
        if($invitation_key){
            $invitation = Invitation::where('invitation_key', '=', $invitation_key)->first();
            if ($invitation && !$invitation->is_deleted) {
                $contact_id = $invitation->contact_id;
            }
        }
        
        $response = Password::broker($broker)->sendResetLink(array('id'=>$contact_id), function (Message $message) {
            $message->subject($this->getEmailSubject());
        });

        switch ($response) {
            case Password::RESET_LINK_SENT:
                return $this->getSendResetLinkEmailSuccessResponse($response);

            case Password::INVALID_USER:
            default:
                return $this->getSendResetLinkEmailFailureResponse($response);
        }
    }
    
    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $invitation_key
     * @param  string|null  $token
     * @return \Illuminate\Http\Response
     */
    public function showResetForm(Request $request, $invitation_key = null, $token = null)
    {
        if (is_null($token)) {
            return $this->getEmail();
        }
        
        $data = compact('token', 'invitation_key');
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

        return view('clientauth.reset')->with($data);
    }
    
    

    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $invitation_key
     * @param  string|null  $token
     * @return \Illuminate\Http\Response
     */
    public function getReset(Request $request, $invitation_key = null, $token = null)
    {
        return $this->showResetForm($request, $invitation_key, $token);
    }
    
    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function reset(Request $request)
    {
        $this->validate($request, $this->getResetValidationRules());

        $credentials = $request->only(
            'password', 'password_confirmation', 'token'
        );
        
        $credentials['id'] = null;
        
        $invitation_key = $request->input('invitation_key');
        if($invitation_key){
            $invitation = Invitation::where('invitation_key', '=', $invitation_key)->first();
            if ($invitation && !$invitation->is_deleted) {
                $credentials['id'] = $invitation->contact_id;
            }
        }

        $broker = $this->getBroker();

        $response = Password::broker($broker)->reset($credentials, function ($user, $password) {
            $this->resetPassword($user, $password);
        });

        switch ($response) {
            case Password::PASSWORD_RESET:
                return $this->getResetSuccessResponse($response);

            default:
                return $this->getResetFailureResponse($request, $response);
        }
    }
    
    /**
     * Get the password reset validation rules.
     *
     * @return array
     */
    protected function getResetValidationRules()
    {
        return [
            'token' => 'required',
            'password' => 'required|confirmed|min:6',
        ];
    }
}

<?php namespace App\Http\Controllers\ClientAuth;

use Config;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Password;
use App\Models\Contact;
use App\Models\Invitation;

class PasswordController extends Controller
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
     * @var string
     */
    protected $redirectTo = '/client/dashboard';

    /**
     * Create a new password controller instance.
     *
     * @internal param \Illuminate\Contracts\Auth\Guard $auth
     * @internal param \Illuminate\Contracts\Auth\PasswordBroker $passwords
     */
    public function __construct()
    {
        $this->middleware('guest');
        Config::set('auth.defaults.passwords', 'client');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showLinkRequestForm()
    {
        $data = [];
        $contactKey = session('contact_key');
        if ($contactKey) {
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && !$contact->is_deleted) {
                $account = $contact->account;
                $data['account'] = $account;
                $data['clientFontUrl'] = $account->getFontsUrl();
            }
        } else {
            return \Redirect::to('/client/sessionexpired');
        }

        return view('clientauth.password')->with($data);
    }

    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request $request
     * 
     * @return \Illuminate\Http\Response
     */
    public function sendResetLinkEmail(Request $request)
    {
        $broker = $this->getBroker();

        $contactId = null;
        $contactKey = session('contact_key');
        if ($contactKey) {
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && !$contact->is_deleted) {
                $contactId = $contact->id;
            }
        }

        $response = Password::broker($broker)->sendResetLink(['id' => $contactId], function (Message $message) {
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
     * @param  \Illuminate\Http\Request $request
     * @param  string|null $key
     * @param  string|null $token
     * @return \Illuminate\Http\Response
     */
    public function showResetForm(Request $request, $key = null, $token = null)
    {
        if (is_null($token)) {
            return $this->getEmail();
        }

        $data = compact('token');
        if ($key) {
            $contact = Contact::where('contact_key', '=', $key)->first();
            if ($contact && !$contact->is_deleted) {
                $account = $contact->account;
                $data['contact_key'] = $contact->contact_key;
            } else {
                // Maybe it's an invitation key
                $invitation = Invitation::where('invitation_key', '=', $key)->first();
                if ($invitation && !$invitation->is_deleted) {
                    $account = $invitation->account;
                    $data['contact_key'] = $invitation->contact->contact_key;
                }
            }

            if (!empty($account)) {
                $data['account'] = $account;
                $data['clientFontUrl'] = $account->getFontsUrl();
            } else {
                return \Redirect::to('/client/sessionexpired');
            }
        }

        return view('clientauth.reset')->with($data);
    }


    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string|null $key
     * @param  string|null $token
     * @return \Illuminate\Http\Response
     */
    public function getReset(Request $request, $key = null, $token = null)
    {
        return $this->showResetForm($request, $key, $token);
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function reset(Request $request)
    {
        $this->validate($request, $this->getResetValidationRules());

        $credentials = $request->only(
            'password', 'password_confirmation', 'token'
        );

        $credentials['id'] = null;

        $contactKey = session('contact_key');
        if ($contactKey) {
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && !$contact->is_deleted) {
                $credentials['id'] = $contact->id;
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

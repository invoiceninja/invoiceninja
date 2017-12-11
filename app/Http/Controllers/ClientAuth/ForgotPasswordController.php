<?php

namespace App\Http\Controllers\ClientAuth;

use Password;
use Config;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest:client');

        //Config::set('auth.defaults.passwords', 'client');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function showLinkRequestForm()
    {
        $data = [
        	'clientauth' => true,
		];

        if (! session('contact_key')) {
            return \Redirect::to('/client/session_expired');
        }

        return view('clientauth.passwords.email')->with($data);
    }

    /**
     * Send a reset link to the given user.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResetLinkEmail(Request $request)
    {
        $contactId = null;
        $contactKey = session('contact_key');
        if ($contactKey) {
            $contact = Contact::where('contact_key', '=', $contactKey)->first();
            if ($contact && ! $contact->is_deleted && $contact->email) {
                $contactId = $contact->id;
            }
        }

        $response = $this->broker()->sendResetLink(['id' => $contactId], function (Message $message) {
            $message->subject($this->getEmailSubject());
        });

        return $response == Password::RESET_LINK_SENT
                    ? $this->sendResetLinkResponse($response)
                    : $this->sendResetLinkFailedResponse($request, $response);
    }

    protected function broker()
    {
        return Password::broker('clients');
    }
}

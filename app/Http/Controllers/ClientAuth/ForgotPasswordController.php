<?php

namespace App\Http\Controllers\ClientAuth;

use Password;
use Config;
use Utils;
use App\Models\Contact;
use App\Models\Account;
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
        // resolve the email to a contact/account
        $account = false;
        if (! Utils::isNinja() && Account::count() == 1) {
            $account = Account::first();
        } elseif ($accountKey = request()->account_key) {
            $account = Account::whereAccountKey($accountKey)->first();
        } else {
            $subdomain = Utils::getSubdomain(\Request::server('HTTP_HOST'));
            if ($subdomain && $subdomain != 'app') {
                $account = Account::whereSubdomain($subdomain)->first();
            }
        }

        if (! $account || ! request()->email) {
            return $this->sendResetLinkFailedResponse($request, Password::INVALID_USER);
        }

        $contact = Contact::where('email', '=', request()->email)
            ->where('account_id', '=', $account->id)
            ->first();

        if ($contact) {
            $contactId = $contact->id;
        } else {
            return $this->sendResetLinkFailedResponse($request, Password::INVALID_USER);
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

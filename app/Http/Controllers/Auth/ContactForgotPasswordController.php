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
use App\Http\Requests\ClientPortal\Contact\ContactPasswordResetRequest;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContactForgotPasswordController extends Controller
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
        $this->middleware('guest:contact');
    }

    /**
     * Show the reset email form.
     *
     * @return Factory|View
     */
    public function showLinkRequestForm(Request $request)
    {
        $account = false;

        if (Ninja::isHosted() && $request->session()->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->session()->get('company_key'));
            $company = Company::where('company_key', $request->session()->get('company_key'))->first();
            $account = $company->account;
        }

        if (! $account) {
            $account = Account::first();
            $company = $account->companies->first();
        }

        return $this->render('auth.passwords.request', [
            'title' => 'Client Password Reset',
            'passwordEmailRoute' => 'client.password.email',
            'account' => $account,
            'company' => $company,
        ]);
    }

    protected function guard()
    {
        return Auth::guard('contact');
    }

    public function broker()
    {
        return Password::broker('contacts');
    }

    public function sendResetLinkEmail(ContactPasswordResetRequest $request)
    {
        if (Ninja::isHosted() && $request->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->input('company_key'));
        }

        $this->validateEmail($request);

        if (Ninja::isHosted() && $company = Company::where('company_key', $request->input('company_key'))->first()) {
            $contact = ClientContact::where(['email' => $request->input('email'), 'company_id' => $company->id])
                                    ->whereHas('client', function ($query) {
                                        $query->where('is_deleted', 0);
                                    })->first();
        } else {
            $contact = ClientContact::where(['email' => $request->input('email')])
                                    ->whereHas('client', function ($query) {
                                        $query->where('is_deleted', 0);
                                    })->first();
        }

        $response = false;

        if ($contact) {

            /* Update all instances of the client */
            $token = Str::random(60);
            ClientContact::where('email', $contact->email)->update(['token' => $token]);
            $contact->sendPasswordResetNotification($token);
            $response = Password::RESET_LINK_SENT;
        } else {
            return $this->sendResetLinkFailedResponse($request, Password::INVALID_USER);
        }

        if ($request->ajax()) {
            if ($response == Password::RESET_THROTTLED) {
                return response()->json(['message' => ctrans('passwords.throttled'), 'status' => false], 429);
            }

            return $response == Password::RESET_LINK_SENT
                ? response()->json(['message' => 'Reset link sent to your email.', 'status' => true], 201)
                : response()->json(['message' => 'Email not found', 'status' => false], 401);
        }

        return $response == Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }
}

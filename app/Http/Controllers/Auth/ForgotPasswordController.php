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
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

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
        $this->middleware('guest');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function sendResetLinkEmail(Request $request)
    {
        MultiDB::userFindAndSetDb($request->input('email'));
        $user = MultiDB::hasUser(['email' => $request->input('email')]);

        $this->validateEmail($request);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

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

    public function showLinkRequestForm(Request $request)
    {
        if ($request->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->input('company_key'));
            $company = Company::where('company_key', $request->input('company_key'))->first();
            $account = $company->account;
        } else {
            $account_id = $request->get('account_id');
            $account = Account::find($account_id);
        }

        return $this->render('auth.passwords.request', ['root' => 'themes', 'account' => $account]);
    }
}

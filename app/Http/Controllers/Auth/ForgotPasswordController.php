<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
     * Password Reset.
     *
     *
     * @OA\Post(
     *      path="/api/v1/reset_password",
     *      operationId="reset_password",
     *      tags={"reset_password"},
     *      summary="Attempts to reset the users password",
     *      description="Resets a users email password",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *         description="Password reset email",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     description="The user email address",
     *                     type="string",
     *                 )
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=201,
     *          description="The Reset response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(
     *              @OA\Items(
     *              type="string",
     *              example="Reset link send to your email.",
     *              )
     *          ),
     *       ),
     *       @OA\Response(
     *          response=401,
     *          description="Validation error",
     *          @OA\JsonContent(
     *          @OA\Items(
     *              type="string",
     *              example="Unable to send password reset link",
     *              ),
     *          ),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
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

            if($response == Password::RESET_THROTTLED)
                return response()->json(['message' => ctrans('passwords.throttled'), 'status' => false], 429);

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
        $account_id = $request->get('account_id');
        $account = Account::find($account_id);
        
        return $this->render('auth.passwords.request', ['root' => 'themes', 'account' => $account]);
    }
}

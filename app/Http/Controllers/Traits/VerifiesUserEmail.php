<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Support\Facades\Auth;

/**
 * Class VerifiesUserEmail
 * @package App\Http\Controllers\Traits
 */
trait VerifiesUserEmail
{
    use UserSessionAttributes;

    /**
     * @param $code
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirm()
    {
        if ($user = User::whereRaw("BINARY `confirmation_code`= ?", request()->route('confirmation_code'))->first()) {
            $user->email_verified_at = now();
            $user->confirmation_code = null;
            $user->save();

            return response()->json(['message' => ctrans('texts.security_confirmation')]);
            //return redirect()->route('dashboard.index')->with('message', ctrans('texts.security_confirmation'));
        }

        return response()->json(['message' => ctrans('texts.wrong_confirmation')]);

        //return redirect()->route('login')->with('message', ctrans('texts.wrong_confirmation'));
    }
}

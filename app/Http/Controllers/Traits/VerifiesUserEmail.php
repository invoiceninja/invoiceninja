<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Class VerifiesUserEmail.
 */
trait VerifiesUserEmail
{
    use UserSessionAttributes;

    /**
     * @return RedirectResponse
     */
    public function confirm()
    {
        $user = User::where('confirmation_code', request()->confirmation_code)->first();

        // if ($user = User::whereRaw("BINARY `confirmation_code`= ?", request()->input('confirmation_code'))->first()) {

        if (! $user) {
            return $this->render('auth.confirmed', ['root' => 'themes', 'message' => ctrans('texts.wrong_confirmation')]);
        }

        if (is_null($user->password) || empty($user->password)) {
            return $this->render('auth.confirmation_with_password', ['root' => 'themes']);
        }

        $user->email_verified_at = now();
        $user->confirmation_code = null;
        $user->save();

        return $this->render('auth.confirmed', [
            'root' => 'themes',
            'message' => ctrans('texts.security_confirmation'),
        ]);
    }

    public function confirmWithPassword()
    {
        $user = User::where('confirmation_code', request()->confirmation_code)->first();

        if (! $user) {
            return $this->render('auth.confirmed', ['root' => 'themes', 'message' => ctrans('texts.wrong_confirmation')]);
        }

        request()->validate([
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $user->password = Hash::make(request()->password);

        $user->email_verified_at = now();
        $user->confirmation_code = null;
        $user->save();

        return $this->render('auth.confirmed', [
            'root' => 'themes',
            'message' => ctrans('texts.security_confirmation'),
        ]);
    }
}

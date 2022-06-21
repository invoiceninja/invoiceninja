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

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Class VerifiesUserEmail.
 */
trait VerifiesUserEmail
{
    use UserSessionAttributes;
    use MakesHash;

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

        $user->email_verified_at = now();
        $user->confirmation_code = null;
        $user->save();

        if (isset($user->oauth_user_id)) {
            return $this->render('auth.confirmed', [
                'root' => 'themes',
                'message' => ctrans('texts.security_confirmation'),
            ]);
        }

        if (is_null($user->password) || empty($user->password) || Hash::check('', $user->password)) {
            return $this->render('auth.confirmation_with_password', ['root' => 'themes', 'user_id' => $user->hashed_id]);
        }

        return $this->render('auth.confirmed', [
            'root' => 'themes',
            'message' => ctrans('texts.security_confirmation'),
        ]);
    }

    public function confirmWithPassword()
    {
        $user = User::where('id', $this->decodePrimaryKey(request()->user_id))->firstOrFail();

        request()->validate([
            'password' => ['required', 'min:6'],
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

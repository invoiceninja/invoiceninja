<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;

trait VerifiesUserEmail
{

    public function confirm($code)
    {
        $user = User::where('confirmation_code', $code)->first();

        if ($user) {

            $user->email_verified_at = now();
            $user->confirmation_code = null;
            $user->save();

            redirect()->route('user.dashboard')->with('message', trans('texts.security_confirmation'));

        }

        redirect()->route('login')->with('message', trans('texts.wrong_confirmation'));

    }
}
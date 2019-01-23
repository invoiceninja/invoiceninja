<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Support\Facades\Auth;

trait VerifiesUserEmail
{
    use UserSessionAttributes;

    public function confirm($code)
    {
        $user = User::where('confirmation_code', $code)->first();

        if ($user) {

            $user->email_verified_at = now();
            $user->confirmation_code = null;
            $user->save();

            $this->setCurrentCompanyId($user->companies()->first()->account->default_company_id);

            Auth::loginUsingId($user->id, true);

            return redirect()->route('dashboard.index')->with('message', ctrans('texts.security_confirmation'));

        }

        return redirect()->route('login')->with('message', ctrans('texts.wrong_confirmation'));

    }
}
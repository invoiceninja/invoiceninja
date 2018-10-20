<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{



    public function confirm($code)
    {
        $user = User::where('confirmation_code', '=', $code)->get()->first();

        if ($user) {

            $user->email_verified_at = now();
            $user->confirmation_code = null;
            $user->save();

            redirect('user.dashboard')->with('message', trans('texts.security_confirmation'));

        } else {

            return Redirect::to('/login')->with('error', trans('texts.wrong_confirmation'));
        }
    }
}

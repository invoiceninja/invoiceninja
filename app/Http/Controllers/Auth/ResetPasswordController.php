<?php

namespace App\Http\Controllers\Auth;

use Event;
use Illuminate\Http\Request;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        sendResetResponse as protected traitSendResetResponse;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function sendResetResponse($response)
    {
        $user = auth()->user();

        if ($user->google_2fa_secret) {
            auth()->logout();
            session(['2fa:user:id' => $user->id]);
            return redirect('/validate_two_factor/' . $user->account->account_key);
        } else {
            Event::fire(new UserLoggedIn());
            return $this->traitSendResetResponse($response);
        }
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with([
            'token' => $token,
            'url' => '/password/reset'
        ]);
    }
}

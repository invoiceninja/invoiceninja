<?php

namespace App\Http\Controllers\Auth;

use Event;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;

class PasswordController extends Controller
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
        getResetSuccessResponse as protected traitGetResetSuccessResponse;
    }

    /**
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new password controller instance.
     *
     * @internal param \Illuminate\Contracts\Auth\Guard $auth
     * @internal param \Illuminate\Contracts\Auth\PasswordBroker $passwords
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Display the form to request a password reset link.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEmailWrapper()
    {
        if (auth()->check()) {
            return redirect('/');
        }

        return $this->getEmail();
    }

    protected function getResetSuccessResponse($response)
    {
        $user = auth()->user();

        if ($user->google_2fa_secret) {
            auth()->logout();
            session(['2fa:user:id' => $user->id]);
            return redirect('/validate_two_factor/' . $user->account->account_key);
        } else {
            Event::fire(new UserLoggedIn());
            return $this->traitGetResetSuccessResponse($response);
        }
    }
}

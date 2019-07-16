<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Route;

class ContactLoginController extends Controller
{
   
    use AuthenticatesUsers;

    protected $redirectTo = '/client/dashboard';

    public function __construct()
    {
      $this->middleware('guest:contact', ['except' => ['logout']]);
    }
    
    public function showLoginForm()
    {
      return view('auth.contact_login');
    }
    
    public function login(Request $request)
    {

      Auth::shouldUse('contact');

        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return response()->json(['message' => 'Too many login attempts, you are being throttled']);
        }

        if ($this->attemptLogin($request))
          return redirect()->intended(route('client.dashboard'));
        else {

            $this->incrementLoginAttempts($request);

            return redirect()->back()->withInput($request->only('email', 'remember'));
        }


    }
    
    public function logout()
    {

        Auth::guard('contact')->logout();

        return redirect('/client/login');
    }
}
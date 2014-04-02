<?php
/*
|--------------------------------------------------------------------------
| Confide Controller Template
|--------------------------------------------------------------------------
|
| This is the default Confide controller template for controlling user
| authentication. Feel free to change to your needs.
|
*/

class UserController extends BaseController {

    public function setTheme()
    {
        $user = User::find(Auth::user()->id);
        $user->theme_id = Input::get('theme_id');
        $user->save();
        
        return Redirect::to(Input::get('path'));
    }

    public function forcePDFJS()
    {
        $user = Auth::user();
        $user->force_pdfjs = true;
        $user->save();

        Session::flash('message', trans('texts.confide.updated_settings'));

        return Redirect::to('/dashboard'); 
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create()
    {
        return View::make(Config::get('confide::signup_form'));
    }

    /**
     * Stores new account
     *
     */
    public function store()
    {
        $user = new User;

        $user->username = Input::get( 'username' );
        $user->email = Input::get( 'email' );
        $user->password = Input::get( 'password' );

        // The password confirmation will be removed from model
        // before saving. This field will be used in Ardent's
        // auto validation.
        $user->password_confirmation = Input::get( 'password_confirmation' );

        // Save if valid. Password field will be hashed before save
        $user->save();

        if ( $user->id )
        {
            // Redirect with success message, You may replace "Lang::get(..." for your custom message.
                        return Redirect::action('UserController@login')
                            ->with( 'notice', Lang::get('confide::confide.alerts.account_created') );
        }
        else
        {
            // Get validation errors (see Ardent package)
            $error = $user->errors()->all(':message');

                        return Redirect::action('UserController@create')
                            ->withInput(Input::except('password'))
                ->with( 'error', $error );
        }
    }

    /**
     * Displays the login form
     *
     */
    public function login()
    {
        if( Confide::user() )
        {
            Event::fire('user.login'); 
            Session::reflash();

            $invoice = Invoice::scope()->orderBy('id', 'desc')->first();
            
            if ($invoice)
            {
                return Redirect::to('/invoices/' . $invoice->public_id);
            }
            else
            {
                return Redirect::to('/dashboard');
            }
        }
        else
        {
            return View::make(Config::get('confide::login_form'));
        }
    }

    /**
     * Attempt to do login
     *
     */
    public function do_login()
    {
        $input = array(
            'email'    => Input::get( 'login_email' ), // May be the username too
            'username' => Input::get( 'login_email' ), // so we have to pass both
            'password' => Input::get( 'login_password' ),
            'remember' => true,
        );

        // If you wish to only allow login from confirmed users, call logAttempt
        // with the second parameter as true.
        // logAttempt will check if the 'email' perhaps is the username.
        // Get the value from the config file instead of changing the controller
        if ( Input::get( 'login_email' ) && Confide::logAttempt( $input, false ) ) 
        {            
            Event::fire('user.login');
            // Redirect the user to the URL they were trying to access before
            // caught by the authentication filter IE Redirect::guest('user/login').
            // Otherwise fallback to '/'
            // Fix pull #145
            return Redirect::intended('/dashboard'); // change it to '/admin', '/dashboard' or something
        }
        else
        {
            //$user = new User;

            // Check if there was too many login attempts
            if( Confide::isThrottled( $input ) )
            {
                $err_msg = trans('texts.confide.too_many_attempts');
            }
            /*
            elseif( $user->checkUserExists( $input ) and ! $user->isConfirmed( $input ) )
            {
                $err_msg = Lang::get('confide::confide.alerts.not_confirmed');
            }
            */
            else
            {
                $err_msg = trans('texts.confide.wrong_credentials');
            }

            return Redirect::action('UserController@login')
                    ->withInput(Input::except('login_password'))
                    ->with( 'error', $err_msg );
        }
    }

    /**
     * Attempt to confirm account with code
     *
     * @param  string  $code
     */
    public function confirm( $code )
    {
        if ( Confide::confirm( $code ) )
        {
            $notice_msg = trans('texts.confide.confirmation');
            return Redirect::action('UserController@login')->with( 'message', $notice_msg );
        }
        else
        {
            $error_msg = trans('texts.confide.wrong_confirmation');
            return Redirect::action('UserController@login')->with( 'error', $error_msg );
        }
    }

    /**
     * Displays the forgot password form
     *
     */
    public function forgot_password()
    {
        return View::make(Config::get('confide::forgot_password_form'));
    }

    /**
     * Attempt to send change password link to the given email
     *
     */
    public function do_forgot_password()
    {
        Confide::forgotPassword( Input::get( 'email' ) );

        $notice_msg = trans('texts.confide.password_forgot');
        return Redirect::action('UserController@login')
            ->with( 'notice', $notice_msg );


        /*
        if( Confide::forgotPassword( Input::get( 'email' ) ) )
        {
            $notice_msg = Lang::get('confide::confide.alerts.password_forgot');
                        return Redirect::action('UserController@login')
                            ->with( 'notice', $notice_msg );
        }
        else
        {
            $error_msg = Lang::get('confide::confide.alerts.wrong_password_forgot');
                        return Redirect::action('UserController@forgot_password')
                            ->withInput()
                ->with( 'error', $error_msg );
        }
        */
    }

    /**
     * Shows the change password form with the given token
     *
     */
    public function reset_password( $token )
    {
        return View::make(Config::get('confide::reset_password_form'))
                ->with('token', $token);
    }

    /**
     * Attempt change password of the user
     *
     */
    public function do_reset_password()
    {
        $input = array(
            'token'=>Input::get( 'token' ),
            'password'=>Input::get( 'password' ),
            'password_confirmation'=>Input::get( 'password_confirmation' ),
        );
        
        // By passing an array with the token, password and confirmation
        if( Confide::resetPassword( $input ) )
        {
            $notice_msg = trans('texts.confide.password_reset');
            return Redirect::action('UserController@login')
                ->with( 'notice', $notice_msg );
        }
        else
        {
            $error_msg = trans('texts.confide.wrong_password_reset');
            return Redirect::action('UserController@reset_password', array('token'=>$input['token']))
                ->withInput()
                ->with( 'error', $error_msg );
        }
    }

    /**
     * Log the user out of the application.
     *
     */
    public function logout()
    {
        if (Auth::check())
        {
            if (!Auth::user()->registered)
            {
                $account = Auth::user()->account;
                $account->forceDelete();
            }
        }

        Confide::logout();        
        
        return Redirect::to('/')->with('clearGuestKey', true);
    }
}

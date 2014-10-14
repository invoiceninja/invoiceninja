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

use ninja\repositories\AccountRepository;
use ninja\mailers\ContactMailer;
use ninja\mailers\UserMailer;

class UserController extends BaseController {

    protected $accountRepo;
    protected $contactMailer;
    protected $userMailer;

    public function __construct(AccountRepository $accountRepo, ContactMailer $contactMailer, UserMailer $userMailer)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->contactMailer = $contactMailer;
        $this->userMailer = $userMailer;
    }   

    public function getDatatable()
    {
      $query = DB::table('users')
                  ->where('users.account_id', '=', Auth::user()->account_id)
                  ->where('users.deleted_at', '=', null)
                  ->where('users.public_id', '>', 0)
                  ->select('users.public_id', 'users.first_name', 'users.last_name', 'users.email', 'users.confirmed', 'users.public_id');


      return Datatable::query($query)
        ->addColumn('first_name', function($model) { return link_to('users/' . $model->public_id . '/edit', $model->first_name . ' ' . $model->last_name); })
        ->addColumn('email', function($model) { return $model->email; })
        ->addColumn('confirmed', function($model) { return $model->confirmed ? trans('texts.active') : trans('texts.pending'); })
        ->addColumn('dropdown', function($model) 
        { 
          return '<div class="btn-group tr-action" style="visibility:hidden;">
              <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                '.trans('texts.select').' <span class="caret"></span>
              </button>
              <ul class="dropdown-menu" role="menu">
              <li><a href="' . URL::to('users/'.$model->public_id) . '/edit">'.uctrans('texts.edit_user').'</a></li>
              <li class="divider"></li>
              <li><a href="javascript:deleteUser(' . $model->public_id. ')">'.uctrans('texts.delete_user').'</a></li>              
            </ul>
          </div>';
        })       
        ->orderColumns(['first_name', 'email', 'confirmed'])
        ->make();           
    }

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

    public function edit($publicId)
    {
        $user = User::where('account_id', '=', Auth::user()->account_id)
                        ->where('public_id', '=', $publicId)->firstOrFail();

        $data = [
            'showBreadcrumbs' => false,
            'user' => $user,
            'method' => 'PUT', 
            'url' => 'users/' . $publicId, 
            'title' => trans('texts.edit_user')
        ];

        return View::make('users.edit', $data);   
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create()
    {
        if (!Auth::user()->confirmed)
        {
            Session::flash('error', trans('texts.register_to_add_user'));            
            return Redirect::to('company/advanced_settings/user_management');
        }

        if (Utils::isNinja())
        {
            $count = User::where('account_id', '=', Auth::user()->account_id)->count();
            if ($count >= MAX_NUM_USERS)
            {
                Session::flash('error', trans('texts.limit_users'));
                return Redirect::to('company/advanced_settings/user_management');        
            }        
        }

        $data = [
          'showBreadcrumbs' => false,
          'user' => null,
          'method' => 'POST',
          'url' => 'users', 
          'title' => trans('texts.add_user')
        ];
        
        return View::make('users.edit', $data);
    }

    public function delete()
    {
        $userPublicId = Input::get('userPublicId');
        $user = User::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $userPublicId)->firstOrFail();

        $user->delete();

        Session::flash('message', trans('texts.deleted_user'));
        return Redirect::to('company/advanced_settings/user_management');        
    }

    /**
     * Stores new account
     *
     */
    public function save($userPublicId = false)
    {
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
        ];

        if ($userPublicId)
        {
            $user = User::where('account_id', '=', Auth::user()->account_id)
                        ->where('public_id', '=', $userPublicId)->firstOrFail();

            $rules['email'] = 'required|email|unique:users,email,' . $user->id . ',id';
        }
        else
        {
            $rules['email'] = 'required|email|unique:users';
        }

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails())
        {
            return Redirect::to($userPublicId ? 'users/edit' : 'users/create')->withInput()->withErrors($validator);
        }        

        if ($userPublicId)
        {
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->username = trim(Input::get('email'));
            $user->email = trim(Input::get('email'));
        }
        else
        {
            $lastUser = User::withTrashed()->where('account_id', '=', Auth::user()->account_id)
                        ->orderBy('public_id', 'DESC')->first();

            $user = new User;
            $user->account_id = Auth::user()->account_id;
            $user->first_name = trim(Input::get('first_name'));
            $user->last_name = trim(Input::get('last_name'));
            $user->username = trim(Input::get('email'));
            $user->email = trim(Input::get('email'));
            $user->registered = true;
            $user->password = str_random(RANDOM_KEY_LENGTH);
            $user->password_confirmation = $user->password;
            $user->public_id = $lastUser->public_id + 1;            
        }

        $user->save();

        if (!$user->confirmed)
        {
            $this->userMailer->sendConfirmation($user, Auth::user());        
            $message = trans('texts.sent_invite');
        }
        else
        {
            $message = trans('texts.updated_user');
        }

        Session::flash('message', $message);

        return Redirect::to('company/advanced_settings/user_management');
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

            return Redirect::to('/dashboard');
            
            /*
            $invoice = Invoice::scope()->orderBy('id', 'desc')->first();
            
            if ($invoice)
            {
                return Redirect::to('/invoices/' . $invoice->public_id);
            }
            else
            {
                return Redirect::to('/dashboard');
            }
            */
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
            
            $user = User::where('confirmation_code', '=', $code)->get()->first();
            $user->confirmation_code = '';
            $user->save();

            if ($user->public_id)
            {
                Auth::login($user);

                return Redirect::to('user/reset');
            }
            else
            {
                if (Session::has(REQUESTED_PRO_PLAN))
                {
                    Session::forget(REQUESTED_PRO_PLAN);                

                    if ($invoice = $this->accountRepo->enableProPlan())
                    {
                        $this->contactMailer->sendInvoice($invoice);
                        $notice_msg = trans('texts.pro_plan_success');
                    }
                }

                return Redirect::action('UserController@login')->with( 'message', $notice_msg );
            }
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
    public function reset_password( $token = false )
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
        if (Auth::check())
        {
            $rules = [
                'password' => 'required|between:4,11|confirmed',
                'password_confirmation' => 'between:4,11',
            ];
            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails())
            {
                return Redirect::to('user/reset')->withInput()->withErrors($validator);
            }        

            $user = Auth::user();
            $user->password = Input::get('password');
            $user->save();

            Session::flash('message', trans('texts.confide.password_reset'));
            return Redirect::to('/dashboard');
        }
        else
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

        Session::forget('news_feed_id');
        Session::forget('news_feed_message');

        Confide::logout();

        return Redirect::to('/')->with('clearGuestKey', true);
    }
}

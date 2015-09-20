<?php namespace App\Http\Controllers;

use Auth;
use Config;
use Datatable;
use DB;
use Event;
use Input;
use View;
use Request;
use Redirect;
use Session;
use URL;
use Utils;
use Validator;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use App\Models\User;
use App\Http\Requests;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Mailers\UserMailer;

class UserController extends BaseController
{
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
                  ->where('users.account_id', '=', Auth::user()->account_id);

        if (!Session::get('show_trash:user')) {
            $query->where('users.deleted_at', '=', null);
        }

        $query->where('users.public_id', '>', 0)
              ->select('users.public_id', 'users.first_name', 'users.last_name', 'users.email', 'users.confirmed', 'users.public_id', 'users.deleted_at');

        return Datatable::query($query)
        ->addColumn('first_name', function ($model) { return link_to('users/'.$model->public_id.'/edit', $model->first_name.' '.$model->last_name); })
        ->addColumn('email', function ($model) { return $model->email; })
        ->addColumn('confirmed', function ($model) { return $model->deleted_at ? trans('texts.deleted') : ($model->confirmed ? trans('texts.active') : trans('texts.pending')); })
        ->addColumn('dropdown', function ($model) {
          $actions = '<div class="btn-group tr-action" style="visibility:hidden;">
              <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                '.trans('texts.select').' <span class="caret"></span>
              </button>
              <ul class="dropdown-menu" role="menu">';

          if ($model->deleted_at) {
              $actions .= '<li><a href="'.URL::to('restore_user/'.$model->public_id).'">'.uctrans('texts.restore_user').'</a></li>';
          } else {
              $actions .= '<li><a href="'.URL::to('users/'.$model->public_id).'/edit">'.uctrans('texts.edit_user').'</a></li>';

              if (!$model->confirmed) {
                  $actions .= '<li><a href="'.URL::to('send_confirmation/'.$model->public_id).'">'.uctrans('texts.send_invite').'</a></li>';
              }

              $actions .= '<li class="divider"></li>
                <li><a href="javascript:deleteUser('.$model->public_id.')">'.uctrans('texts.delete_user').'</a></li>';
          }

           $actions .= '</ul>
          </div>';

          return $actions;
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

        Session::flash('message', trans('texts.updated_settings'));

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
            'url' => 'users/'.$publicId,
            'title' => trans('texts.edit_user'),
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
        if (!Auth::user()->registered) {
            Session::flash('error', trans('texts.register_to_add_user'));
            return Redirect::to('company/advanced_settings/user_management');
        }        
        if (!Auth::user()->confirmed) {
            Session::flash('error', trans('texts.confirmation_required'));
            return Redirect::to('company/advanced_settings/user_management');
        }

        if (Utils::isNinja()) {
            $count = User::where('account_id', '=', Auth::user()->account_id)->count();
            if ($count >= MAX_NUM_USERS) {
                Session::flash('error', trans('texts.limit_users'));

                return Redirect::to('company/advanced_settings/user_management');
            }
        }

        $data = [
          'showBreadcrumbs' => false,
          'user' => null,
          'method' => 'POST',
          'url' => 'users',
          'title' => trans('texts.add_user'),
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

    public function restoreUser($userPublicId)
    {
        $user = User::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $userPublicId)
                    ->withTrashed()->firstOrFail();

        $user->restore();

        Session::flash('message', trans('texts.restored_user'));

        return Redirect::to('company/advanced_settings/user_management');
    }

    /**
     * Stores new account
     *
     */
    public function save($userPublicId = false)
    {
        if (Auth::user()->account->isPro()) {
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
            ];

            if ($userPublicId) {
                $user = User::where('account_id', '=', Auth::user()->account_id)
                            ->where('public_id', '=', $userPublicId)->firstOrFail();

                $rules['email'] = 'required|email|unique:users,email,'.$user->id.',id';
            } else {
                $rules['email'] = 'required|email|unique:users';
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to($userPublicId ? 'users/edit' : 'users/create')->withInput()->withErrors($validator);
            }

            if ($userPublicId) {
                $user->first_name = trim(Input::get('first_name'));
                $user->last_name = trim(Input::get('last_name'));
                $user->username = trim(Input::get('email'));
                $user->email = trim(Input::get('email'));
            } else {
                $lastUser = User::withTrashed()->where('account_id', '=', Auth::user()->account_id)
                            ->orderBy('public_id', 'DESC')->first();

                $user = new User();
                $user->account_id = Auth::user()->account_id;
                $user->first_name = trim(Input::get('first_name'));
                $user->last_name = trim(Input::get('last_name'));
                $user->username = trim(Input::get('email'));
                $user->email = trim(Input::get('email'));
                $user->registered = true;
                $user->password = str_random(RANDOM_KEY_LENGTH);
                $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
                $user->public_id = $lastUser->public_id + 1;
            }

            $user->save();

            if (!$user->confirmed) {
                $this->userMailer->sendConfirmation($user, Auth::user());
                $message = trans('texts.sent_invite');
            } else {
                $message = trans('texts.updated_user');
            }

            Session::flash('message', $message);
        }
        
        return Redirect::to('company/advanced_settings/user_management');
    }

    public function sendConfirmation($userPublicId)
    {
        $user = User::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $userPublicId)->firstOrFail();

        $this->userMailer->sendConfirmation($user, Auth::user());
        Session::flash('message', trans('texts.sent_invite'));

        return Redirect::to('company/advanced_settings/user_management');
    }


    /**
     * Attempt to confirm account with code
     *
     * @param string $code
     */
    public function confirm($code, TokenRepositoryInterface $tokenRepo)
    {
        $user = User::where('confirmation_code', '=', $code)->get()->first();
            
        if ($user) {
            $notice_msg = trans('texts.security.confirmation');

            $user->confirmed = true;
            $user->confirmation_code = '';
            $user->save();

            if ($user->public_id) {
                //Auth::login($user);
                $token = $tokenRepo->create($user);

                return Redirect::to("/password/reset/{$token}");
            } else {
                if (Session::has(REQUESTED_PRO_PLAN)) {
                    Session::forget(REQUESTED_PRO_PLAN);
                    $invitation = $this->accountRepo->enableProPlan();

                    return Redirect::to($invitation->getLink());
                } else {
                    return Redirect::to(Auth::check() ? '/dashboard' : '/login')->with('message', $notice_msg);
                }
            }
        } else {
            $error_msg = trans('texts.security.wrong_confirmation');

            return Redirect::to('/login')->with('error', $error_msg);
        }
    }

    /**
     * Log the user out of the application.
     *
     */
    /*
    public function logout()
    {
        if (Auth::check()) {
            if (!Auth::user()->registered) {
                $account = Auth::user()->account;
                $this->accountRepo->unlinkAccount($account);
                $account->forceDelete();
            }
        }

        Auth::logout();
        Session::flush();

        return Redirect::to('/')->with('clearGuestKey', true);
    }
    */
    
    public function changePassword()
    {
        // check the current password is correct
        if (!Auth::validate([
            'email' => Auth::user()->email,
            'password' => Input::get('current_password')
        ])) {
            return trans('texts.password_error_incorrect');
        }

        // validate the new password
        $password = Input::get('new_password');
        $confirm = Input::get('confirm_password');

        if (strlen($password) < 6 || $password != $confirm) {
            return trans('texts.password_error_invalid');
        }

        // save the new password
        $user = Auth::user();
        $user->password = bcrypt($password);
        $user->save();

        return RESULT_SUCCESS;
    }

    public function switchAccount($newUserId)
    {
        $oldUserId = Auth::user()->id;
        $referer = Request::header('referer');
        $account = $this->accountRepo->findUserAccounts($newUserId, $oldUserId);
        
        if ($account) {
            if ($account->hasUserId($newUserId) && $account->hasUserId($oldUserId)) {
                Auth::loginUsingId($newUserId);
                Auth::user()->account->loadLocalizationSettings();

                // regenerate token to prevent open pages
                // from saving under the wrong account
                Session::put('_token', str_random(40));
            }
        }
        
        return Redirect::to($referer);
    }

    public function unlinkAccount($userAccountId, $userId)
    {
        $this->accountRepo->unlinkUser($userAccountId, $userId);
        $referer = Request::header('referer');

        $users = $this->accountRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USER_ACCOUNTS, $users);

        Session::flash('message', trans('texts.unlinked_account'));
        return Redirect::to('/dashboard');
    }

    public function manageCompanies()
    {
        return View::make('users.account_management');
    }

}

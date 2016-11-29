<?php namespace App\Http\Controllers;

use Auth;
use Input;
use View;
use Request;
use Redirect;
use Session;
use URL;
use Password;
use Utils;
use Validator;
use App\Models\User;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Mailers\UserMailer;
use App\Services\UserService;

class UserController extends BaseController
{
    protected $accountRepo;
    protected $contactMailer;
    protected $userMailer;
    protected $userService;

    public function __construct(AccountRepository $accountRepo, ContactMailer $contactMailer, UserMailer $userMailer, UserService $userService)
    {
        //parent::__construct();

        $this->accountRepo = $accountRepo;
        $this->contactMailer = $contactMailer;
        $this->userMailer = $userMailer;
        $this->userService = $userService;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
    }

    public function getDatatable()
    {
        return $this->userService->getDatatable(Auth::user()->account_id);
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
                        ->where('public_id', '=', $publicId)
                        ->withTrashed()
                        ->firstOrFail();

        $data = [
            'user' => $user,
            'method' => 'PUT',
            'url' => 'users/'.$publicId,
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
        if ( ! Auth::user()->registered) {
            Session::flash('error', trans('texts.register_to_add_user'));
            return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
        }

        if ( ! Auth::user()->confirmed) {
            Session::flash('error', trans('texts.confirmation_required'));
            return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
        }

        if (Utils::isNinja() && ! Auth::user()->caddAddUsers()) {
            Session::flash('error', trans('texts.max_users_reached'));
            return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
        }

        $data = [
          'user' => null,
          'method' => 'POST',
          'url' => 'users',
        ];

        return View::make('users.edit', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $id = Input::get('bulk_public_id');

        $user = User::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $id)
                    ->withTrashed()
                    ->firstOrFail();

        if ($action === 'archive') {
            $user->delete();
        } else {
            if ( ! Auth::user()->caddAddUsers()) {
                return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT)
                    ->with('error', trans('texts.max_users_reached'));
            }

            $user->restore();
        }

        Session::flash('message', trans("texts.{$action}d_user"));

        return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
    }

    /**
     * Stores new account
     *
     */
    public function save($userPublicId = false)
    {
        if (Auth::user()->hasFeature(FEATURE_USERS)) {
            $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
            ];

            if ($userPublicId) {
                $user = User::where('account_id', '=', Auth::user()->account_id)
                            ->where('public_id', '=', $userPublicId)
                            ->withTrashed()
                            ->firstOrFail();

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
                if (Auth::user()->hasFeature(FEATURE_USER_PERMISSIONS)) {
                    $user->is_admin = boolval(Input::get('is_admin'));
                    $user->permissions = Input::get('permissions');
                }
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
                if (Auth::user()->hasFeature(FEATURE_USER_PERMISSIONS)) {
                    $user->is_admin = boolval(Input::get('is_admin'));
                    $user->permissions = Input::get('permissions');
                }
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

        return Redirect::to('users/' . $user->public_id . '/edit');
    }

    public function sendConfirmation($userPublicId)
    {
        $user = User::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $userPublicId)->firstOrFail();

        $this->userMailer->sendConfirmation($user, Auth::user());
        Session::flash('message', trans('texts.sent_invite'));

        return Redirect::to('settings/' . ACCOUNT_USER_MANAGEMENT);
    }


    /**
     * Attempt to confirm account with code
     *
     * @param string $code
     */
    public function confirm($code)
    {
        $user = User::where('confirmation_code', '=', $code)->get()->first();

        if ($user) {
            $notice_msg = trans('texts.security.confirmation');

            $user->confirmed = true;
            $user->confirmation_code = '';
            $user->save();

            if ($user->public_id) {
                Auth::logout();
                $token = Password::getRepository()->create($user);
                return Redirect::to("/password/reset/{$token}");
            } else {
                if (Auth::check()) {
                    if (Session::has(REQUESTED_PRO_PLAN)) {
                        Session::forget(REQUESTED_PRO_PLAN);
                        $url = '/settings/account_management?upgrade=true';
                    } else {
                        $url = '/dashboard';
                    }
                } else {
                    $url = '/login';
                }
                return Redirect::to($url)->with('message', $notice_msg);
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
                if ($account->company->accounts->count() == 1) {
                    $account->company->forceDelete();
                }
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

        // If the user is looking at an entity redirect to the dashboard
        preg_match('/\/[0-9*][\/edit]*$/', $referer, $matches);
        if (count($matches)) {
            return Redirect::to('/dashboard');
        } else {
            return Redirect::to($referer);
        }
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

    public function saveSidebarState()
    {
        if (Input::has('show_left')) {
            Session::put(SESSION_LEFT_SIDEBAR, boolval(Input::get('show_left')));
        }

        if (Input::has('show_right')) {
            Session::put(SESSION_RIGHT_SIDEBAR, boolval(Input::get('show_right')));
        }

        return RESULT_SUCCESS;
    }
}

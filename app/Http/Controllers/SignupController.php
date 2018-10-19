<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\Account;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Hash;

/**
 * Class SignupController
 * @package App\Http\Controllers
 */
class SignupController extends Controller
{

    /**
     * SignupController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function signup()
    {
        return view('signup.index');
    }

    /**
     * @param SignupRequest $request
     */
    public function processSignup(SignupRequest $request)
    {
        //dd($request->validated());

        //created new account

        $ac = new Account();
        $ac->name = $request->first_name. ' ' .$request->last_name;
        $ac->account_key = strtolower(str_random(RANDOM_KEY_LENGTH));
        $ac->ip = $request->ip();
        $ac->save();

        $user = new User();
        $user->password = Hash::make($request->input('password'));
        $user->accepted_terms_version = NINJA_TERMS_VERSION;
        $user->db = config('database.default');
        $user->fill($request->all());
        $user->save();

        $user_account = new UserAccount();
        $user_account->user_id = $user->id;
        $user_account->account_id = $ac->id;
        $user_account->is_owner = TRUE;
        $user_account->is_admin = TRUE;
        $user_account->is_default = TRUE;
        $user_account->is_locked = FALSE;
        $user_account->permissions = '';
        $user_account->save();

        dd($user);
        //log user in

        //fire account created job

        //redirect to localization setup workflow
    }

}
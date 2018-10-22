<?php

namespace App\Jobs;

use App\Events\UserSignedUp;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Models\UserCompany;
use Illuminate\Support\Facades\Hash;

class RegisterNewAccount
{

    use Dispatchable;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $ac = new Account();
        $ac->utm_source = $this->request->input('utm_source');
        $ac->utm_medium = $this->request->input('utm_medium');
        $ac->utm_campaign = $this->request->input('utm_campaign');
        $ac->utm_term = $this->request->input('utm_term');
        $ac->utm_content = $this->request->input('utm_content');
        $ac->save();

        $company = new Company();
        $company->account_id = $ac->id;
        $company->name = $this->request->first_name . ' ' . $this->request->last_name;
        $company->company_key = strtolower(str_random(RANDOM_KEY_LENGTH));
        $company->ip = $this->request->ip();
        $company->save();

        $user = new User();
        $user->account_id = $ac->id;
        $user->password = Hash::make($this->request->input('password'));
        $user->accepted_terms_version = NINJA_TERMS_VERSION;
        $user->confirmation_code = strtolower(str_random(RANDOM_KEY_LENGTH));
        $user->db = config('database.default');
        $user->fill($this->request->all());
        $user->save();

        $user_account = new UserCompany();
        $user_account->user_id = $user->id;
        $user_account->account_id = $ac->id;
        $user_account->company_id = $company->id;
        $user_account->is_owner = TRUE;
        $user_account->is_admin = TRUE;
        $user_account->permissions = '';
        $user_account->save();

        $ac->default_company_id = $ac->id;
        $ac->save();

        //fire account created job
        event(new UserSignedUp($user));
        
        return $user;
    }
}
